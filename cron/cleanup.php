<?php

declare(strict_types=1);

/**
 * FORT (Fast Short) Cleanup Cron Job
 *
 * Run via cronjob:
 *   * * * * * php /path/to/project/cron/cleanup.php
 *
 * Or via systemd timer on Linux.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use PDO;

Env::load(dirname(__DIR__));

$db = Database::connection();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
$now = date('Y-m-d H:i:s');
$log = [];

echo '[FORT Cleanup] Starting at ' . $now . PHP_EOL;

// ----------------------------------------------------------------
// 1. Disable/Delete expired links
// ----------------------------------------------------------------
$expiredDays = (int) Env::get('CLEANUP_EXPIRED_LINKS_DAYS', '30');
$cutoff = date('Y-m-d H:i:s', strtotime("-{$expiredDays} days"));

$stmt = $db->prepare("
    UPDATE links
    SET is_active = 0, updated_at = :now
    WHERE is_active = 1
      AND expires_at IS NOT NULL
      AND expires_at < :cutoff
");
$stmt->execute([':now' => $now, ':cutoff' => $cutoff]);
$disabledCount = $stmt->rowCount();
$log[] = "Disabled {$disabledCount} expired links (older than {$expiredDays} days)";

$stmt = $db->prepare("
    DELETE FROM links
    WHERE is_active = 0
      AND expires_at IS NOT NULL
      AND expires_at < :cutoff
      AND (
          SELECT COUNT(*) FROM link_clicks WHERE link_id = links.id
      ) = 0
");
$stmt->execute([':cutoff' => $cutoff]);
$deletedCount = $stmt->rowCount();
$log[] = "Deleted {$deletedCount} expired links with no clicks";

echo "  - Expired links: {$disabledCount} disabled, {$deletedCount} deleted" . PHP_EOL;

// ----------------------------------------------------------------
// 2. Purge old audit logs
// ----------------------------------------------------------------
$auditDays = (int) Env::get('PURGE_AUDIT_LOGS_DAYS', '365');
$auditCutoff = date('Y-m-d H:i:s', strtotime("-{$auditDays} days"));

$stmt = $db->prepare("DELETE FROM audit_logs WHERE created_at < :cutoff");
$stmt->execute([':cutoff' => $auditCutoff]);
$purgedAudit = $stmt->rowCount();
$log[] = "Purged {$purgedAudit} audit log entries (older than {$auditDays} days)";

echo "  - Audit logs purged: {$purgedAudit}" . PHP_EOL;

// ----------------------------------------------------------------
// 3. Purge unverified users
// ----------------------------------------------------------------
$unverifiedDays = (int) Env::get('PURGE_UNVERIFIED_USERS_DAYS', '7');
$unverifiedCutoff = date('Y-m-d H:i:s', strtotime("-{$unverifiedDays} days"));

$stmt = $db->prepare("
    DELETE FROM users
    WHERE email_verified_at IS NULL
      AND created_at < :cutoff
");
$stmt->execute([':cutoff' => $unverifiedCutoff]);
$purgedUsers = $stmt->rowCount();
$log[] = "Purged {$purgedUsers} unverified users (older than {$unverifiedDays} days)";

echo "  - Unverified users purged: {$purgedUsers}" . PHP_EOL;

// ----------------------------------------------------------------
// 4. Clean up expired password reset tokens
// ----------------------------------------------------------------
$stmt = $db->prepare("DELETE FROM password_resets WHERE expires_at < :now");
$stmt->execute([':now' => $now]);
$purgedResets = $stmt->rowCount();
$log[] = "Purged {$purgedResets} expired password reset tokens";

echo "  - Expired password resets purged: {$purgedResets}" . PHP_EOL;

// ----------------------------------------------------------------
// 5. Log cleanup activity
// ----------------------------------------------------------------
$logEntry = json_encode([
    'timestamp' => $now,
    'actions' => $log,
    'driver' => $driver,
]);

$logFile = dirname(__DIR__) . '/storage/logs/cleanup.log';
$handle = @fopen($logFile, 'a');
if ($handle) {
    fwrite($handle, $logEntry . PHP_EOL);
    fclose($handle);
    echo "  - Cleanup activity logged to {$logFile}" . PHP_EOL;
} else {
    echo "  - WARNING: Could not write to log file {$logFile}" . PHP_EOL;
}

echo '[FORT Cleanup] Completed at ' . date('Y-m-d H:i:s') . PHP_EOL;
echo '---' . PHP_EOL;

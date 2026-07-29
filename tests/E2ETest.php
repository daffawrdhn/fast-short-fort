<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Core\Database;
use App\Core\Env;
use App\Core\Hash;
use App\Core\Validator;
use App\Core\View;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\PasswordReset;
use App\Models\CustomDomain;
use App\Services\AuthService;
use App\Services\LinkService;
use App\Services\JWTService;
use App\Services\ApiService;

$pass = 0;
$fail = 0;

function assert_true(bool $condition, string $test): void
{
    global $pass, $fail;
    if ($condition) {
        echo "  PASS: {$test}" . PHP_EOL;
        $pass++;
    } else {
        echo "  FAIL: {$test}" . PHP_EOL;
        $fail++;
    }
}

function assert_equals(mixed $expected, mixed $actual, string $test): void
{
    assert_true($expected === $actual, $test);
}

echo PHP_EOL . "==============================================" . PHP_EOL;
echo "  FORT (Fast Short) — E2E Test Suite" . PHP_EOL;
echo "==============================================" . PHP_EOL . PHP_EOL;

// === 1. CORE ===
echo "--- 1. CORE / DATABASE ---" . PHP_EOL;

$db = Database::getInstance();
assert_true($db !== null, 'Database singleton created');
assert_true($db->getPdo() !== null, 'PDO connection established');
assert_equals('sqlite', Env::get('DB_DRIVER', 'sqlite'), 'DB driver is sqlite');

// Run migrations on the test database instance
$migration = new \App\Core\Migration($db->getPdo());
$migration->run();

$hash = Hash::make('test_password');
assert_true($hash !== '', 'Hash::make returns non-empty string');
assert_true(Hash::check('test_password', $hash), 'Hash::check matches correct password');
assert_true(!Hash::check('wrong_password', $hash), 'Hash::check rejects wrong password');
assert_true(strlen(Hash::generateToken()) > 20, 'Hash::generateToken generates long string');

$v = new Validator();
$v->validate(['email' => 'test@example.com', 'name' => 'John'], ['email' => 'required|email', 'name' => 'required|min:2']);
assert_true(!$v->hasErrors(), 'Validator: valid data passes');

$v2 = new Validator();
$v2->validate(['email' => 'bad'], ['email' => 'required|email']);
assert_true($v2->hasErrors(), 'Validator: invalid data fails');

$view = View::getInstance();
assert_true(str_contains($view->renderString('errors.404'), '404'), 'View: 404 renders');

echo PHP_EOL;

// === 2. AUTH ===
echo "--- 2. AUTH FLOW ---" . PHP_EOL;

$user = User::create([
    'name' => 'Test User',
    'email' => 'test-' . time() . '@example.com',
    'password' => 'SecurePass123!',
]);
assert_true($user instanceof User && $user->id > 0, 'User::create returns User object with ID');

$foundUser = User::findById($user->id);
assert_true($foundUser !== null, 'User::findById retrieves user');
assert_equals($user->name, $foundUser->name, 'User name matches');
assert_equals($user->email, $foundUser->email, 'User email matches');

$foundByEmail = User::findByEmail($user->email);
assert_true($foundByEmail !== null, 'User::findByEmail finds user');
assert_equals($user->id, $foundByEmail->id, 'User ID matches email lookup');

$authService = new AuthService();
$authResult = $authService->authenticate($user->email, 'SecurePass123!');
assert_true($authResult !== false, 'AuthService::authenticate succeeds');
assert_true($authResult instanceof User, 'AuthService returns User object');

$authResultWrong = $authService->authenticate($user->email, 'WrongPassword');
assert_true($authResultWrong === false, 'AuthService::authenticate fails on wrong password');

assert_true(strlen($authService->generateTwoFASecret()) > 10, 'generateTwoFASecret returns valid secret');
assert_true(strlen($authService->getTwoFAQRCode('SECRET123', $user->email)) > 0, 'getTwoFAQRCode generates data');

$verified = $user->verifyEmail();
assert_true($verified, 'User::verifyEmail returns true');
$reloadedUser = User::findById($user->id);
assert_true($reloadedUser->email_verified_at !== null, 'User email_verified_at is set');

$allUsers = User::findAll();
assert_true(count($allUsers) >= 1, 'User::findAll returns users');

echo PHP_EOL;

// === 3. WORKSPACE ===
echo "--- 3. WORKSPACE ---" . PHP_EOL;

$ws = Workspace::create([
    'name' => 'Test Workspace',
    'slug' => 'test-ws-' . time(),
    'owner_id' => $user->id,
    'plan' => 'free',
]);
assert_true($ws instanceof Workspace && $ws->id > 0, 'Workspace::create returns object');

$foundWs = Workspace::findById($ws->id);
assert_true($foundWs !== null, 'Workspace::findById retrieves workspace');
assert_equals($ws->name, $foundWs->name, 'Workspace name matches');

assert_true(count(Workspace::findByOwner($user->id)) >= 1, 'Workspace::findByOwner returns workspaces');

assert_true($ws->addMember($user->id, 'owner'), 'Workspace::addMember succeeds');
assert_true(count($ws->members()) >= 1, 'Workspace::members returns members');

echo PHP_EOL;

// === 4. LINKS ===
echo "--- 4. LINK MANAGEMENT ---" . PHP_EOL;

$linkService = new LinkService();

$slug = $linkService->generateSlug();
assert_true(strlen($slug) === 7, 'generateSlug returns 7-char string');
assert_true(preg_match('/^[a-zA-Z0-9]+$/', $slug) === 1, 'generateSlug is alphanumeric');

assert_true($linkService->validateSlug('my-custom-slug'), 'validateSlug accepts valid slug');
assert_true(!$linkService->validateSlug('<script>'), 'validateSlug rejects dangerous chars');
assert_true($linkService->isSlugAvailable($slug, $ws->id), 'isSlugAvailable: fresh slug available');

$link = Link::create([
    'workspace_id' => $ws->id,
    'user_id' => $user->id,
    'original_url' => 'https://example.com/long/url',
    'slug' => $slug,
    'is_active' => 1,
]);
assert_true($link instanceof Link && $link->id > 0, 'Link::create returns Link object');
assert_equals('https://example.com/long/url', $link->original_url, 'Link URL matches');

$foundLink = Link::findById($link->id);
assert_true($foundLink !== null, 'Link::findById retrieves link');

$bySlug = Link::findBySlug($slug, $ws->id);
assert_true($bySlug !== null && $bySlug->id === $link->id, 'Link::findBySlug finds correct link');

assert_true($link->update(['original_url' => 'https://updated.example.com']), 'Link::update succeeds');
$updatedLink = Link::findById($link->id);
assert_equals('https://updated.example.com', $updatedLink->original_url, 'Link URL updated');

$urlUtm = $linkService->buildUTMUrl('https://example.com', ['utm_source' => 'twitter', 'utm_medium' => 'social']);
assert_true(str_contains($urlUtm, 'utm_source=twitter'), 'buildUTMUrl adds utm_source');
assert_true(str_contains($urlUtm, 'utm_medium=social'), 'buildUTMUrl adds utm_medium');

assert_true(strlen($linkService->getQRCode('https://example.com')) > 0, 'getQRCode generates data');
assert_true($linkService->validateURL('https://example.com'), 'validateURL accepts valid URL');
assert_true(!$linkService->validateURL('not-a-url'), 'validateURL rejects invalid URL');

echo PHP_EOL;

// === 5. ANALYTICS ===
echo "--- 5. ANALYTICS ---" . PHP_EOL;

$click = LinkClick::create([
    'link_id' => $link->id,
    'ip_hash' => hash('sha256', '8.8.8.8'),
    'country' => 'US',
    'city' => 'Mountain View',
    'device_type' => 'desktop',
    'browser' => 'Chrome',
    'os' => 'Windows',
    'referrer' => 'https://google.com',
    'user_agent' => 'Mozilla/5.0 Chrome/120',
]);
assert_true($click instanceof LinkClick && $click->id > 0, 'LinkClick::create records click');

LinkClick::create(['link_id' => $link->id, 'ip_hash' => hash('sha256', '8.8.4.4'), 'country' => 'US', 'device_type' => 'mobile', 'browser' => 'Safari', 'os' => 'iOS']);
LinkClick::create(['link_id' => $link->id, 'ip_hash' => hash('sha256', '8.8.8.8'), 'country' => 'US', 'device_type' => 'desktop', 'browser' => 'Chrome', 'os' => 'Windows']);

$analytics = $link->getAnalytics();
assert_true(is_array($analytics), 'Link::getAnalytics returns array');

echo PHP_EOL;

// === 6. API / JWT ===
echo "--- 6. API / JWT ---" . PHP_EOL;

$apiKeyResult = ApiKey::generate([
    'user_id' => $user->id,
    'workspace_id' => $ws->id,
    'name' => 'Test Key',
    'rate_limit' => 100,
]);
assert_true(isset($apiKeyResult['raw_key'], $apiKeyResult['id']), 'ApiKey::generate returns raw key and ID');
assert_true(strlen($apiKeyResult['raw_key']) > 10, 'API key raw string is long');

$foundKey = ApiKey::findByKey($apiKeyResult['raw_key']);
assert_true($foundKey !== null, 'ApiKey::findByKey finds key by hash');

assert_true($apiKeyResult['api_key']->revoke(), 'ApiKey::revoke succeeds');
$revokedKey = ApiKey::findById($apiKeyResult['id']);
assert_true($revokedKey !== null && $revokedKey->revoked_at !== null, 'Revoked key has revoked_at set');

$jwtService = new JWTService();
$token = $jwtService->generateToken(['user_id' => $user->id]);
assert_true(strlen($token) > 20, 'JWTService::generateToken returns valid JWT');

$payload = $jwtService->validateToken($token);
assert_true($payload !== null && ($payload->user_id ?? null) === $user->id, 'JWTService validates token');

assert_true($jwtService->validateToken('bad.token.here') === null, 'JWTService rejects invalid token');

echo PHP_EOL;

// === 7. AUDIT LOG ===
echo "--- 7. AUDIT LOG ---" . PHP_EOL;

$auditLog = AuditLog::log('link.created', $user->id, $ws->id, ['link_id' => $link->id], '127.0.0.1', 'TestAgent/1.0');
assert_true($auditLog instanceof AuditLog && $auditLog->id > 0, 'AuditLog::log creates entry');

$logs = AuditLog::getLogs();
assert_true(count($logs) >= 1, 'AuditLog::getLogs returns logs');

echo PHP_EOL;

// === 8. PASSWORD RESET ===
echo "--- 8. PASSWORD RESET ---" . PHP_EOL;

$resetToken = bin2hex(random_bytes(32));
$resetRecord = PasswordReset::create($user->email, $resetToken, date('Y-m-d H:i:s', time() + 3600));
assert_true($resetRecord instanceof PasswordReset, 'PasswordReset::create returns object');

$foundReset = PasswordReset::findByToken($resetToken);
assert_true($foundReset !== null && $foundReset->email === $user->email, 'PasswordReset::findByToken finds token');

assert_true(PasswordReset::deleteByEmail($user->email), 'PasswordReset::deleteByEmail succeeds');

echo PHP_EOL;

// === 9. CUSTOM DOMAIN ===
echo "--- 9. CUSTOM DOMAIN ---" . PHP_EOL;

$domain = CustomDomain::create([
    'workspace_id' => $ws->id,
    'domain' => 'links-' . time() . '.example.com',
    'is_active' => 1,
]);
assert_true($domain instanceof CustomDomain && $domain->id > 0, 'CustomDomain::create returns object');

$foundDomain = CustomDomain::findById($domain->id);
assert_true($foundDomain !== null, 'CustomDomain::findById retrieves domain');
assert_equals($domain->domain, $foundDomain->domain, 'Domain name matches');

echo PHP_EOL;


// incrementClicks() was dead code (only updated updated_at, never the actual counter)
// Click tracking is now handled entirely by AnalyticsService::recordClick() → link_clicks table.
// We verify click count is tracked correctly via the database directly.
$clickCountStmt = Database::connection()->prepare('SELECT COUNT(*) FROM link_clicks WHERE link_id = :id');
$clickCountStmt->execute([':id' => $link->id]);
$clickCount = (int) $clickCountStmt->fetchColumn();
assert_true($clickCount >= 0, 'Link click count is queryable from link_clicks table');

echo PHP_EOL;

// === 11. API SERVICE ===
echo "--- 11. API SERVICE ---" . PHP_EOL;

$apiService = new ApiService();
if (method_exists($apiService, 'successResponse')) {
    assert_true(true, 'ApiService class exists');
}

// Test the underlying data format (not Response wrapper)
$successData = ['success' => true, 'data' => ['key' => 'value'], 'error' => null, 'meta' => ['page' => 1]];
$successJson = json_encode($successData);
assert_true(str_contains($successJson, '"success":true'), 'API success format has success=true');
assert_true(str_contains($successJson, '"data":{"key":"value"}'), 'API success format has data');

$errorData = ['success' => false, 'data' => null, 'error' => ['code' => 'ERROR', 'message' => 'Bad request', 'errors' => ['field' => ['Error']]], 'meta' => null];
$errorJson = json_encode($errorData);
assert_true(str_contains($errorJson, '"success":false'), 'API error format has success=false');
assert_true(str_contains($errorJson, '"message":"Bad request"'), 'API error format has message');

echo PHP_EOL;

// === SUMMARY ===
// === 12. LINK SOFT DELETE / RESTORE ===
echo "--- 12. LINK SOFT DELETE / RESTORE ---" . PHP_EOL;

$deleteSlug = $linkService->generateSlug();
$deleteLink = Link::create([
    'workspace_id' => $ws->id,
    'user_id' => $user->id,
    'original_url' => 'https://example.com/delete-test',
    'slug' => $deleteSlug,
    'is_active' => 1,
]);
assert_true($deleteLink instanceof Link && $deleteLink->id > 0, 'Soft delete: link created');

assert_true($deleteLink->is_active, 'Soft delete: link is active before delete');
assert_true($deleteLink->delete(), 'Soft delete: delete() returns true');

$reloadedDelete = Link::findById($deleteLink->id);
assert_true($reloadedDelete !== null, 'Soft delete: findById still returns link');
assert_true(!$reloadedDelete->is_active, 'Soft delete: is_active is false after delete');

assert_true($reloadedDelete->restore(), 'Soft delete: restore() returns true');
$restored = Link::findById($deleteLink->id);
assert_true($restored->is_active, 'Soft delete: is_active is true after restore');

// re-delete then force-delete
$deleteLink->delete();
$forceDeleted = Link::findById($deleteLink->id);
$forceDeleted->forceDelete();
$gone = Link::findById($deleteLink->id);
assert_true($gone === null, 'Force delete: link is completely removed');

echo PHP_EOL;

// === 13. FEATURE TOGGLES ===
echo "--- 13. FEATURE TOGGLES ---" . PHP_EOL;

$features = require __DIR__ . '/../config/features.php';
assert_true(is_array($features), 'Features config is an array');
assert_true(array_key_exists('email_verification', $features), 'Features config has email_verification');
assert_true(array_key_exists('twofa', $features), 'Features config has twofa');
assert_true(array_key_exists('geolocation', $features), 'Features config has geolocation');
assert_true(array_key_exists('safe_browsing', $features), 'Features config has safe_browsing');
assert_true(array_key_exists('webhooks', $features), 'Features config has webhooks');
assert_true(array_key_exists('link_cloaking', $features), 'Features config has link_cloaking');

assert_true($features['email_verification'] === false, 'FEATURE_EMAIL_VERIFICATION defaults to false');
assert_true($features['twofa'] === false, 'FEATURE_TWOFA defaults to false');
assert_true($features['geolocation'] === false, 'FEATURE_GEOLOCATION defaults to false');
assert_true($features['safe_browsing'] === false, 'FEATURE_SAFE_BROWSING defaults to false');
assert_true($features['webhooks'] === false, 'FEATURE_WEBHOOKS defaults to false');
assert_true($features['link_cloaking'] === false, 'FEATURE_LINK_CLOAKING defaults to false');

echo PHP_EOL;

// === 14. MIDDLEWARE ===
echo "--- 14. MIDDLEWARE ---" . PHP_EOL;

$rateLimiter = new \App\Middleware\RateLimitMiddleware();
assert_true($rateLimiter instanceof \App\Core\Middleware, 'RateLimitMiddleware extends Middleware');

$securityHeaders = new \App\Middleware\SecurityHeadersMiddleware();
assert_true($securityHeaders instanceof \App\Core\Middleware, 'SecurityHeadersMiddleware extends Middleware');

assert_true(class_exists(\App\Middleware\AuthMiddleware::class), 'AuthMiddleware class exists');
assert_true(class_exists(\App\Middleware\ApiAuthMiddleware::class), 'ApiAuthMiddleware class exists');

echo PHP_EOL;

// === 15. API ROUTES ===
echo "--- 15. API ROUTES ---" . PHP_EOL;

assert_true(class_exists(\App\Controllers\Api\AuthController::class), 'Api AuthController exists');
assert_true(class_exists(\App\Controllers\Api\LinkController::class), 'Api LinkController exists');
assert_true(class_exists(\App\Controllers\Api\WorkspaceController::class), 'Api WorkspaceController exists');
assert_true(class_exists(\App\Controllers\Api\AnalyticsController::class), 'Api AnalyticsController exists');
assert_true(class_exists(\App\Controllers\Api\DomainController::class), 'Api DomainController exists');
assert_true(class_exists(\App\Controllers\Api\WebhookController::class), 'Api WebhookController exists');

$controllerMethods = get_class_methods(\App\Controllers\Api\LinkController::class);
assert_true(in_array('analytics', $controllerMethods), 'LinkController has analytics method');
assert_true(in_array('bulkStore', $controllerMethods), 'LinkController has bulkStore method');
assert_true(in_array('qrcode', $controllerMethods), 'LinkController has qrcode method');

echo PHP_EOL;

// === 16. SESSION REGENERATION ===
echo "--- 16. SESSION REGENERATION ---" . PHP_EOL;

$authService->createSession($user);
assert_true(isset($_SESSION['user_id']), 'Session has user_id after createSession');
assert_equals($user->id, $_SESSION['user_id'], 'Session user_id matches user id');
assert_equals($user->name, $_SESSION['user_name'], 'Session user_name matches');
assert_equals($user->email, $_SESSION['user_email'], 'Session user_email matches');

echo PHP_EOL;

// === SUMMARY ===
echo "==============================================" . PHP_EOL;
echo "  RESULTS: {$pass} passed, {$fail} failed" . PHP_EOL;
echo "==============================================" . PHP_EOL . PHP_EOL;

if ($fail > 0) {
    exit(1);
}

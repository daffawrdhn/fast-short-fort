<div class="admin-page-header">
  <h1>System Health Check</h1>
  <p class="text-muted">Overview of system status and configuration</p>
</div>

<div class="health-grid">
  <div class="card">
    <div class="card-header">
      <h2>PHP Version</h2>
    </div>
    <div class="card-body">
      <div class="health-item">
        <span class="health-label">Version</span>
        <span class="health-value"><?= htmlspecialchars($phpVersion ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        <span class="health-indicator <?= ($phpOk ?? false) ? 'status-ok' : 'status-error' ?>">
          <?= ($phpOk ?? false) ? '&#x2714; OK' : '&#x2718; Minimum 8.2 required' ?>
        </span>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>PHP Extensions</h2>
    </div>
    <div class="card-body">
      <?php foreach (($extensions ?? []) as $ext => $loaded): ?>
      <div class="health-item">
        <span class="health-label"><?= htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="health-indicator <?= $loaded ? 'status-ok' : 'status-error' ?>">
          <?= $loaded ? '&#x2714; Loaded' : '&#x2718; Missing' ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Directory Permissions</h2>
    </div>
    <div class="card-body">
      <?php foreach (($directories ?? []) as $dir => $writable): ?>
      <div class="health-item">
        <span class="health-label"><?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="health-indicator <?= $writable ? 'status-ok' : 'status-error' ?>">
          <?= $writable ? '&#x2714; Writable' : '&#x2718; Not Writable' ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Database</h2>
    </div>
    <div class="card-body">
      <div class="health-item">
        <span class="health-label">Connection</span>
        <span class="health-indicator <?= !empty($dbStatus['connected']) ? 'status-ok' : 'status-error' ?>">
          <?= !empty($dbStatus['connected']) ? '&#x2714; Connected' : '&#x2718; Disconnected' ?>
        </span>
      </div>
      <?php if (!empty($dbStatus['driver'])): ?>
      <div class="health-item">
        <span class="health-label">Driver</span>
        <span class="health-value"><?= htmlspecialchars($dbStatus['driver'], ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($dbStatus['error'])): ?>
      <div class="health-item">
        <span class="health-label">Error</span>
        <span class="health-value text-error"><?= htmlspecialchars($dbStatus['error'], ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Disk Usage</h2>
    </div>
    <div class="card-body">
      <div class="health-item">
        <span class="health-label">Total</span>
        <span class="health-value"><?= htmlspecialchars($diskTotal ?? '0', ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="health-item">
        <span class="health-label">Used</span>
        <span class="health-value"><?= htmlspecialchars($diskUsed ?? '0', ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)($diskPercent ?? '0'), ENT_QUOTES, 'UTF-8') ?>%)</span>
      </div>
      <div class="health-item">
        <span class="health-label">Free</span>
        <span class="health-value"><?= htmlspecialchars($diskFree ?? '0', ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="progress-bar">
        <div class="progress-fill <?= ($diskPercent ?? 0) > 90 ? 'progress-danger' : (($diskPercent ?? 0) > 75 ? 'progress-warning' : 'progress-ok') ?>" style="width: <?= min(100, $diskPercent ?? 0) ?>%" role="progressbar" aria-valuenow="<?= $diskPercent ?? 0 ?>" aria-valuemin="0" aria-valuemax="100"></div>
      </div>
    </div>
  </div>
</div>

<div class="admin-page-header">
  <h1>Admin Dashboard</h1>
  <p class="text-muted">System overview and management</p>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-value"><?= htmlspecialchars((string)($stats['total_users'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="stat-label">Total Users</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= htmlspecialchars((string)($stats['total_workspaces'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="stat-label">Total Workspaces</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= htmlspecialchars((string)($stats['total_links'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="stat-label">Total Links</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= htmlspecialchars((string)($stats['total_clicks'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="stat-label">Total Clicks</div>
  </div>
</div>

<div class="admin-grid">
  <div class="card">
    <div class="card-header">
      <h2>System Health</h2>
    </div>
    <div class="card-body">
      <div class="health-item">
        <span class="health-label">Database</span>
        <span class="health-status <?= !empty($dbStatus['connected']) ? 'status-ok' : 'status-error' ?>">
          <?= !empty($dbStatus['connected']) ? 'Connected (' . htmlspecialchars($dbStatus['driver'] ?? '', ENT_QUOTES, 'UTF-8') . ')' : 'Disconnected' ?>
        </span>
      </div>
      <div class="health-item">
        <span class="health-label">Disk Usage</span>
        <span class="health-status"><?= htmlspecialchars($diskUsage['percent'] ?? '0', ENT_QUOTES, 'UTF-8') ?>% (<?= htmlspecialchars($diskUsage['used'] ?? '0', ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars($diskUsage['total'] ?? '0', ENT_QUOTES, 'UTF-8') ?>)</span>
      </div>
      <div class="health-item">
        <span class="health-label">PHP Extensions</span>
        <span class="health-status">
          <?php $allLoaded = true; foreach (($extensions ?? []) as $ext => $loaded): ?>
            <?php if (!$loaded) { $allLoaded = false; break; } ?>
          <?php endforeach; ?>
          <span class="<?= $allLoaded ? 'status-ok' : 'status-error' ?>"><?= $allLoaded ? 'All required loaded' : 'Some missing' ?></span>
        </span>
      </div>
    </div>
    <div class="card-footer">
      <a href="/admin/health" class="btn btn-sm">View Details</a>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Recent Registrations</h2>
    </div>
    <div class="card-body">
      <?php if (!empty($recentUsers)): ?>
      <table class="table table-compact">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentUsers as $u): ?>
          <tr>
            <td><?= htmlspecialchars($u['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($u['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p class="text-muted">No recent registrations.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="quick-links">
  <h2>Quick Links</h2>
  <div class="quick-links-grid">
    <a href="/admin/users" class="quick-link-card">
      <span class="ql-icon">&#x1F465;</span>
      <span class="ql-title">User Management</span>
      <span class="ql-desc">Create, edit, and manage users</span>
    </a>
    <a href="/admin/settings" class="quick-link-card">
      <span class="ql-icon">&#x2699;&#xFE0F;</span>
      <span class="ql-title">Settings</span>
      <span class="ql-desc">Configure application settings</span>
    </a>
    <a href="/admin/health" class="quick-link-card">
      <span class="ql-icon">&#x1F3E5;</span>
      <span class="ql-title">Health Check</span>
      <span class="ql-desc">System status and diagnostics</span>
    </a>
    <a href="/admin/blocklist" class="quick-link-card">
      <span class="ql-icon">&#x1F6AB;</span>
      <span class="ql-title">Blocklist</span>
      <span class="ql-desc">Manage URL blocklist</span>
    </a>
  </div>
</div>

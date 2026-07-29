<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['theme'] ?? 'light', ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars($title ?? 'Admin - FORT', ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="icon" href="/favicon.ico" type="image/x-icon">
  <script src="/assets/js/app.js" defer></script>
</head>
<body class="admin-page">
  <a href="#main-content" class="skip-link">Skip to main content</a>

  <div class="admin-wrapper">
    <aside class="admin-sidebar" role="navigation" aria-label="Admin navigation">
      <div class="sidebar-header">
        <a href="/admin" class="sidebar-logo" aria-label="Admin Dashboard">FORT Admin</a>
        <button class="sidebar-toggle" aria-label="Toggle sidebar" aria-expanded="false">
          <span class="hamburger"></span>
        </button>
      </div>

      <ul class="sidebar-nav">
        <li><a href="/admin" class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>" aria-label="Dashboard"><span class="nav-icon" aria-hidden="true">&#x1F4CA;</span> Dashboard</a></li>
        <li><a href="/admin/users" class="nav-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>" aria-label="Users"><span class="nav-icon" aria-hidden="true">&#x1F465;</span> Users</a></li>
        <li><a href="/admin/workspaces" class="nav-link <?= ($activeNav ?? '') === 'workspaces' ? 'active' : '' ?>" aria-label="Workspaces"><span class="nav-icon" aria-hidden="true">&#x1F4E6;</span> Workspaces</a></li>
        <li><a href="/admin/settings" class="nav-link <?= ($activeNav ?? '') === 'settings' ? 'active' : '' ?>" aria-label="Settings"><span class="nav-icon" aria-hidden="true">&#x2699;&#xFE0F;</span> Settings</a></li>
        <li><a href="/admin/health" class="nav-link <?= ($activeNav ?? '') === 'health' ? 'active' : '' ?>" aria-label="Health"><span class="nav-icon" aria-hidden="true">&#x1F3E5;</span> Health</a></li>
        <li><a href="/admin/blocklist" class="nav-link <?= ($activeNav ?? '') === 'blocklist' ? 'active' : '' ?>" aria-label="Blocklist"><span class="nav-icon" aria-hidden="true">&#x1F6AB;</span> Blocklist</a></li>
        <li><a href="/admin/logs" class="nav-link <?= ($activeNav ?? '') === 'logs' ? 'active' : '' ?>" aria-label="Logs"><span class="nav-icon" aria-hidden="true">&#x1F4DD;</span> Logs</a></li>
      </ul>

      <div class="sidebar-footer">
        <a href="/dashboard" class="nav-link" aria-label="Back to app">&#x2190; Back to App</a>
        <form method="post" action="/logout" class="inline-form">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="nav-link logout-btn" aria-label="Logout">&#x1F6AA; Logout</button>
        </form>
      </div>
    </aside>

    <main id="main-content" class="admin-main" role="main">
      <?php if (!empty($flash)): ?>
      <div class="flash-container">
        <?php foreach ($flash as $type => $message): ?>
        <div class="alert alert-<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" role="alert">
          <span class="alert-text"><?= htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') ?></span>
          <button class="alert-dismiss" aria-label="Dismiss">&times;</button>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?= $content ?? '' ?>
    </main>
  </div>
</body>
</html>

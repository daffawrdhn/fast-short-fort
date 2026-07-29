<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['theme'] ?? 'light', ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars($title ?? 'FORT (Fast Short)', ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="icon" href="/favicon.ico" type="image/x-icon">
  <script src="/assets/js/app.js" defer></script>
</head>
<body>
  <a href="#main-content" class="skip-link">Skip to main content</a>

  <nav class="navbar" role="navigation" aria-label="Main navigation">
    <div class="navbar-brand">
      <a href="/dashboard" class="navbar-logo" aria-label="FORT Home">FORT</a>
      <button class="navbar-toggle" aria-label="Toggle navigation menu" aria-expanded="false">
        <span class="hamburger"></span>
      </button>
    </div>

    <div class="navbar-menu" role="menubar">
      <ul class="navbar-nav">
        <li><a href="/dashboard" role="menuitem" class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
        <li><a href="/links" role="menuitem" class="nav-link <?= ($activeNav ?? '') === 'links' ? 'active' : '' ?>">Links</a></li>
        <li><a href="/analytics" role="menuitem" class="nav-link <?= ($activeNav ?? '') === 'analytics' ? 'active' : '' ?>">Analytics</a></li>
        <li><a href="/workspace" role="menuitem" class="nav-link <?= ($activeNav ?? '') === 'workspace' ? 'active' : '' ?>">Workspace</a></li>
        <?php if (!empty($_SESSION['user_is_admin'])): ?>
        <li><a href="/admin" role="menuitem" class="nav-link <?= ($activeNav ?? '') === 'admin' ? 'active' : '' ?>">Admin</a></li>
        <?php endif; ?>
      </ul>

      <div class="navbar-actions">
        <button class="theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">
          <span class="theme-icon" aria-hidden="true"></span>
        </button>
        <div class="user-dropdown" data-dropdown>
          <button class="dropdown-trigger" aria-haspopup="true" aria-expanded="false">
            <?php $sessName = $_SESSION['user_name'] ?? 'User'; ?>
            <span class="user-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper($sessName[0]), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="user-name"><?= htmlspecialchars($sessName, ENT_QUOTES, 'UTF-8') ?></span>
          </button>
          <ul class="dropdown-menu" role="menu">
            <li><a href="/profile" role="menuitem">Profile</a></li>
            <?php if (!empty($_SESSION['user_is_admin'])): ?>
            <li><a href="/admin/settings" role="menuitem">Settings</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form id="logout-form" action="/logout" method="POST" style="display: none;">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(session()->csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
              </form>
              <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" role="menuitem" class="dropdown-danger">Logout</a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </nav>

  <main id="main-content" role="main" class="container">
    <?php if (!empty($flash)): ?>
    <div class="flash-container">
      <?php foreach ($flash as $type => $message): ?>
      <div class="alert alert-<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" role="alert">
        <span class="alert-text"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
        <button class="alert-dismiss" aria-label="Dismiss">&times;</button>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?= $this->getSection('content', $content ?? '') ?>
  </main>

  <footer class="footer" role="contentinfo">
    <p>&copy; <?= date('Y') ?> FORT (Fast Short). All rights reserved.</p>
  </footer>
</body>
</html>

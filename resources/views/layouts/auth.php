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
<body class="auth-page">
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-header">
        <a href="/" class="auth-logo" aria-label="FORT Home">FORT</a>
        <button class="theme-toggle auth-theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">
          <span class="theme-icon" aria-hidden="true"></span>
        </button>
      </div>

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

      <?= $content ?? '' ?>
    </div>
  </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['theme'] ?? 'light', ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Install - FORT (Fast Short)</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="icon" href="/favicon.ico" type="image/x-icon">
</head>
<body class="install-page">
  <div class="install-container">
    <div class="install-card">
      <div class="install-header">
        <div class="install-logo">
          <span class="logo-icon" aria-hidden="true">&#x26A1;</span>
          <h1>FORT</h1>
          <p class="logo-subtitle">Fast Short</p>
        </div>
        <p class="install-desc">Enterprise-Grade Open-Source URL Shortener</p>
      </div>

      <?php if (!empty($flash)): ?>
      <?php foreach ($flash as $type => $message): ?>
      <div class="alert alert-<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" role="alert">
        <?= htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

      <div class="install-body">
        <div class="install-steps">
          <div class="step active">
            <span class="step-number">1</span>
            <span class="step-label">Welcome</span>
          </div>
          <div class="step">
            <span class="step-number">2</span>
            <span class="step-label">Requirements</span>
          </div>
          <div class="step">
            <span class="step-number">3</span>
            <span class="step-label">Database</span>
          </div>
          <div class="step">
            <span class="step-number">4</span>
            <span class="step-label">Configuration</span>
          </div>
          <div class="step">
            <span class="step-number">5</span>
            <span class="step-label">Install</span>
          </div>
        </div>

        <div class="install-content">
          <h2>Welcome to FORT Installation</h2>
          <p>Thank you for choosing FORT (Fast Short) - the enterprise-grade open-source URL shortener.</p>
          <p>This installer will guide you through the setup process:</p>
          <ul>
            <li>Check PHP requirements</li>
            <li>Configure database connection (SQLite or PostgreSQL)</li>
            <li>Set up your application</li>
            <li>Create your admin account</li>
          </ul>
        </div>
      </div>

      <div class="install-footer">
        <a href="/install/requirements" class="btn btn-primary btn-lg">Start Installation</a>
      </div>
    </div>
  </div>
</body>
</html>

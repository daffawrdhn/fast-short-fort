<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['theme'] ?? 'light', ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Complete - Install - FORT</title>
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
        </div>
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
          <div class="step completed"><span class="step-number">&#x2714;</span><span class="step-label">Welcome</span></div>
          <div class="step completed"><span class="step-number">&#x2714;</span><span class="step-label">Requirements</span></div>
          <div class="step completed"><span class="step-number">&#x2714;</span><span class="step-label">Database</span></div>
          <div class="step completed"><span class="step-number">&#x2714;</span><span class="step-label">Configuration</span></div>
          <div class="step completed active"><span class="step-number">&#x2714;</span><span class="step-label">Complete</span></div>
        </div>

        <div class="install-content text-center">
          <div class="success-icon" aria-hidden="true">&#x2705;</div>
          <h2>Installation Complete!</h2>
          <p class="install-success-text">FORT (Fast Short) has been installed successfully.</p>

          <div class="alert alert-warning" role="alert">
            <strong>Security Reminders:</strong>
            <ul>
              <li>Delete or restrict access to the <code>/install</code> route/files to prevent re-installation.</li>
              <li>Ensure <code>APP_DEBUG</code> is set to <code>false</code> in production.</li>
              <li>Set up HTTPS for your application.</li>
              <li>Review and adjust email settings in the <code>.env</code> file.</li>
            </ul>
          </div>

          <div class="install-actions">
            <a href="/admin" class="btn btn-primary btn-lg">Go to Admin Panel</a>
            <a href="/login" class="btn btn-ghost btn-lg">Go to Login</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

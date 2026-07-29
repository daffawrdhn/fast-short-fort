<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['theme'] ?? 'light', ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Requirements - Install - FORT</title>
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
          <div class="step completed">
            <span class="step-number">&#x2714;</span>
            <span class="step-label">Welcome</span>
          </div>
          <div class="step active">
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
          <h2>System Requirements</h2>
          <p>Checking if your server meets the minimum requirements.</p>

          <div class="requirements-list">
            <?php foreach (($checks ?? []) as $key => $check): ?>
            <div class="requirement-item <?= $check['pass'] ? 'pass' : 'fail' ?>">
              <span class="req-icon"><?= $check['pass'] ? '&#x2714;' : '&#x2718;' ?></span>
              <span class="req-label"><?= htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="req-value"><?= htmlspecialchars($check['value'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="install-footer">
        <?php if ($allPass ?? false): ?>
        <a href="/install/database" class="btn btn-primary btn-lg">Next: Database Configuration</a>
        <?php else: ?>
        <button class="btn btn-primary btn-lg" disabled aria-disabled="true">Next: Fix Requirements First</button>
        <p class="text-error text-sm">Please fix the failing requirements before proceeding.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>

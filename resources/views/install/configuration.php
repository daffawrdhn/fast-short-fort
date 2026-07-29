<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['theme'] ?? 'light', ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configuration - Install - FORT</title>
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
          <div class="step active"><span class="step-number">4</span><span class="step-label">Configuration</span></div>
          <div class="step"><span class="step-number">5</span><span class="step-label">Install</span></div>
        </div>

        <div class="install-content">
          <h2>Application Configuration</h2>
          <p>Configure your application and create the admin account.</p>

          <form method="post" action="/install/run">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="db_driver" value="<?= htmlspecialchars($_GET['db_driver'] ?? 'sqlite', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="db_host" value="<?= htmlspecialchars($_GET['db_host'] ?? '127.0.0.1', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="db_port" value="<?= htmlspecialchars($_GET['db_port'] ?? '5432', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="db_name" value="<?= htmlspecialchars($_GET['db_name'] ?? 'fort', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="db_user" value="<?= htmlspecialchars($_GET['db_user'] ?? 'fort', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="db_password" value="<?= htmlspecialchars($_GET['db_password'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="card">
              <div class="card-header">
                <h3>Application Settings</h3>
              </div>
              <div class="card-body">
                <div class="form-group">
                  <label for="app_name">Application Name</label>
                  <input type="text" id="app_name" name="app_name" value="FORT (Fast Short)" required aria-required="true">
                </div>
                <div class="form-group">
                  <label for="app_url">Application URL</label>
                  <input type="url" id="app_url" name="app_url" value="http://localhost" placeholder="https://example.com" required aria-required="true">
                  <p class="form-help">The full URL where this application will be accessible.</p>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <h3>Admin Account</h3>
              </div>
              <div class="card-body">
                <div class="form-group">
                  <label for="admin_name">Name</label>
                  <input type="text" id="admin_name" name="admin_name" required aria-required="true">
                </div>
                <div class="form-group">
                  <label for="admin_email">Email</label>
                  <input type="email" id="admin_email" name="admin_email" required aria-required="true">
                </div>
                <div class="form-group">
                  <label for="admin_password">Password</label>
                  <input type="password" id="admin_password" name="admin_password" required minlength="8" aria-required="true">
                  <p class="form-help">At least 8 characters.</p>
                </div>
              </div>
            </div>

            <div class="install-footer">
              <a href="/install/database" class="btn btn-ghost">Back</a>
              <button type="submit" class="btn btn-primary btn-lg">Install FORT</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

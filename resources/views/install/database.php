<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['theme'] ?? 'light', ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Database - Install - FORT</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="icon" href="/favicon.ico" type="image/x-icon">
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var driverRadios = document.querySelectorAll('input[name="db_driver"]');
      var pgsqlFields = document.getElementById('pgsql-fields');
      var sqliteInfo = document.getElementById('sqlite-info');
      var testBtn = document.getElementById('test-connection-btn');
      var testResult = document.getElementById('test-result');

      function toggleDriver(driver) {
        if (pgsqlFields) pgsqlFields.style.display = driver === 'pgsql' ? 'block' : 'none';
        if (sqliteInfo) sqliteInfo.style.display = driver === 'sqlite' ? 'block' : 'none';
      }

      driverRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
          toggleDriver(this.value);
        });
      });

      toggleDriver(document.querySelector('input[name="db_driver"]:checked').value);

      if (testBtn) {
        testBtn.addEventListener('click', function() {
          var driver = document.querySelector('input[name="db_driver"]:checked').value;
          var data = { driver: driver };
          if (driver === 'pgsql') {
            data.host = document.getElementById('db_host').value;
            data.port = document.getElementById('db_port').value;
            data.database = document.getElementById('db_name').value;
            data.username = document.getElementById('db_user').value;
            data.password = document.getElementById('db_password').value;
          }
          testBtn.disabled = true;
          testBtn.textContent = 'Testing...';
          testResult.innerHTML = '';
          testResult.className = '';

          var formData = new FormData();
          formData.append('driver', data.driver);
          if (data.host) formData.append('host', data.host);
          if (data.port) formData.append('port', data.port);
          if (data.database) formData.append('database', data.database);
          if (data.username) formData.append('username', data.username);
          if (data.password) formData.append('password', data.password);

          fetch('/install/test-connection', {
            method: 'POST',
            body: formData
          })
          .then(function(r) { return r.json(); })
          .then(function(res) {
            testResult.textContent = res.message;
            testResult.className = res.success ? 'alert alert-success' : 'alert alert-error';
          })
          .catch(function() {
            testResult.textContent = 'Connection test failed.';
            testResult.className = 'alert alert-error';
          })
          .finally(function() {
            testBtn.disabled = false;
            testBtn.textContent = 'Test Connection';
          });
        });
      }
    });
  </script>
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
          <div class="step active"><span class="step-number">3</span><span class="step-label">Database</span></div>
          <div class="step"><span class="step-number">4</span><span class="step-label">Configuration</span></div>
          <div class="step"><span class="step-number">5</span><span class="step-label">Install</span></div>
        </div>

        <div class="install-content">
          <h2>Database Configuration</h2>
          <p>Select your database driver and configure the connection.</p>

          <form method="get" action="/install/configuration" id="db-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
              <label>Database Driver</label>
              <div class="radio-group">
                <label class="radio-label">
                  <input type="radio" name="db_driver" value="sqlite" <?= ($driver ?? 'sqlite') === 'sqlite' ? 'checked' : '' ?>>
                  SQLite (Simple, no server needed)
                </label>
                <label class="radio-label">
                  <input type="radio" name="db_driver" value="pgsql" <?= ($driver ?? '') === 'pgsql' ? 'checked' : '' ?>>
                  PostgreSQL (Production)
                </label>
              </div>
            </div>

            <div id="sqlite-info">
              <div class="alert alert-info" role="alert">
                SQLite database will be created at: <code>storage/fort.sqlite</code>
              </div>
            </div>

            <div id="pgsql-fields" style="display:none;">
              <div class="form-group">
                <label for="db_host">Host</label>
                <input type="text" id="db_host" name="db_host" value="127.0.0.1">
              </div>
              <div class="form-group">
                <label for="db_port">Port</label>
                <input type="number" id="db_port" name="db_port" value="5432">
              </div>
              <div class="form-group">
                <label for="db_name">Database Name</label>
                <input type="text" id="db_name" name="db_name" value="fort">
              </div>
              <div class="form-group">
                <label for="db_user">Username</label>
                <input type="text" id="db_user" name="db_user" value="fort">
              </div>
              <div class="form-group">
                <label for="db_password">Password</label>
                <input type="password" id="db_password" name="db_password">
              </div>
            </div>

            <div class="form-group">
              <button type="button" id="test-connection-btn" class="btn btn-ghost">Test Connection</button>
              <div id="test-result" role="alert"></div>
            </div>
          </form>
        </div>
      </div>

      <div class="install-footer">
        <a href="/install/requirements" class="btn btn-ghost">Back</a>
        <button type="submit" form="db-form" class="btn btn-primary btn-lg">Next: Configuration</button>
      </div>
    </div>
  </div>
</body>
</html>

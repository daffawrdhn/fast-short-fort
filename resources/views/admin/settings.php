<div class="admin-page-header">
  <h1>Global Settings</h1>
  <p class="text-muted">Configure application-wide settings</p>
</div>

<form method="post" action="/admin/settings" class="settings-form">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">

  <div class="card">
    <div class="card-header">
      <h2>Application</h2>
    </div>
    <div class="card-body">
      <div class="form-group">
        <label for="app_name">Application Name</label>
        <input type="text" id="app_name" name="APP_NAME" value="<?= htmlspecialchars($config['APP_NAME'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="form-group">
        <label for="app_url">Application URL</label>
        <input type="url" id="app_url" name="APP_URL" value="<?= htmlspecialchars($config['APP_URL'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://example.com">
        <p class="form-help">The full URL where the application is hosted.</p>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Rate Limiting</h2>
    </div>
    <div class="card-body">
      <div class="form-group">
        <label for="rate_limit_global">Global Rate Limit (requests/minute)</label>
        <input type="number" id="rate_limit_global" name="RATE_LIMIT_GLOBAL" value="<?= htmlspecialchars($config['RATE_LIMIT_GLOBAL'] ?? '100', ENT_QUOTES, 'UTF-8') ?>" min="1">
      </div>
      <div class="form-group">
        <label for="rate_limit_login">Login Rate Limit (requests/minute)</label>
        <input type="number" id="rate_limit_login" name="RATE_LIMIT_LOGIN" value="<?= htmlspecialchars($config['RATE_LIMIT_LOGIN'] ?? '10', ENT_QUOTES, 'UTF-8') ?>" min="1">
      </div>
      <div class="form-group">
        <label for="rate_limit_create_link">Link Creation Rate Limit (requests/minute)</label>
        <input type="number" id="rate_limit_create_link" name="RATE_LIMIT_CREATE_LINK" value="<?= htmlspecialchars($config['RATE_LIMIT_CREATE_LINK'] ?? '30', ENT_QUOTES, 'UTF-8') ?>" min="1">
      </div>
      <div class="form-group">
        <label for="rate_limit_api">API Rate Limit (requests/minute)</label>
        <input type="number" id="rate_limit_api" name="RATE_LIMIT_API" value="<?= htmlspecialchars($config['RATE_LIMIT_API'] ?? '60', ENT_QUOTES, 'UTF-8') ?>" min="1">
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Registration & Users</h2>
    </div>
    <div class="card-body">
      <div class="form-group">
        <label class="checkbox-label">
          <input type="checkbox" name="REGISTRATION_ENABLED" value="true" <?= ($config['REGISTRATION_ENABLED'] ?? 'true') === 'true' ? 'checked' : '' ?>>
          Enable Registration
        </label>
        <p class="form-help">Allow new users to register.</p>
      </div>
      <div class="form-group">
        <label class="checkbox-label">
          <input type="checkbox" name="EMAIL_VERIFICATION_REQUIRED" value="true" <?= ($config['EMAIL_VERIFICATION_REQUIRED'] ?? 'false') === 'true' ? 'checked' : '' ?>>
          Require Email Verification
        </label>
        <p class="form-help">New users must verify their email before accessing the app.</p>
      </div>
      <div class="form-group">
        <label for="default_user_plan">Default User Plan</label>
        <select id="default_user_plan" name="DEFAULT_USER_PLAN" aria-label="Default plan for new users">
          <option value="free" <?= ($config['DEFAULT_USER_PLAN'] ?? 'free') === 'free' ? 'selected' : '' ?>>Free</option>
          <option value="pro" <?= ($config['DEFAULT_USER_PLAN'] ?? 'free') === 'pro' ? 'selected' : '' ?>>Pro</option>
          <option value="business" <?= ($config['DEFAULT_USER_PLAN'] ?? 'free') === 'business' ? 'selected' : '' ?>>Business</option>
          <option value="enterprise" <?= ($config['DEFAULT_USER_PLAN'] ?? 'free') === 'enterprise' ? 'selected' : '' ?>>Enterprise</option>
        </select>
      </div>
    </div>
  </div>

  <div class="form-submit">
    <button type="submit" class="btn btn-primary btn-lg">Save Settings</button>
  </div>
</form>

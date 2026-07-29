<?php $this->extend('layouts.app'); ?>

<?php $this->section('content'); ?>
<div class="link-form-container">
  <div class="page-header">
    <h1 class="page-title">Edit Link</h1>
    <a href="/links" class="btn btn-outline" aria-label="Back to links">&larr; Back</a>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="current-short-url">
        <span class="form-label">Current Short URL</span>
        <code class="preview-box readonly-url"><?= $this->escape($shortUrl ?? '') ?></code>
      </div>

      <form action="/links/<?= $this->escape((string) $link->id) ?>/edit" method="POST" class="link-form" role="form" aria-label="Edit link form" novalidate>
        <input type="hidden" name="_csrf" value="<?= $this->escape($csrf ?? session()->csrfToken()) ?>">

        <div class="form-group">
          <label for="original_url" class="form-label required">Original URL</label>
          <input type="url" id="original_url" name="original_url" class="form-control" required value="<?= $this->escape($link->original_url ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="slug" class="form-label">Custom Alias (Slug)</label>
          <div class="input-group">
            <input type="text" id="slug" name="slug" class="form-control" value="<?= $this->escape($link->slug ?? '') ?>" minlength="3" maxlength="50" pattern="[a-zA-Z0-9\-_]{3,50}">
            <button type="button" class="btn btn-outline" id="check-availability" aria-label="Check slug availability">Check</button>
          </div>
          <span id="availability-result" class="form-text" aria-live="polite"></span>
        </div>

        <div class="form-row">
          <div class="form-group form-group-half">
            <label for="expires_at" class="form-label">Expiration Date &amp; Time</label>
            <input type="datetime-local" id="expires_at" name="expires_at" class="form-control" value="<?= $link->expires_at ? $this->escape(date('Y-m-d\TH:i', strtotime($link->expires_at))) : '' ?>">
          </div>
          <div class="form-group form-group-half">
            <label for="click_limit" class="form-label">Click Limit</label>
            <input type="number" id="click_limit" name="click_limit" class="form-control" min="1" value="<?= $this->escape((string) ($link->click_limit ?? '')) ?>" placeholder="e.g. 1000">
          </div>
        </div>

        <div class="form-group">
          <div class="form-toggle">
            <input type="checkbox" id="password_enabled" name="password_enabled" class="toggle-input" <?= $link->password_hash ? 'checked' : '' ?>>
            <label for="password_enabled" class="toggle-label">Change Password</label>
          </div>
          <div id="password-field-wrapper" class="<?= $link->password_hash ? '' : 'hidden' ?>">
            <input type="password" id="password" name="password" class="form-control" placeholder="New password (min 6 chars)" minlength="6">
            <?php if ($link->password_hash): ?><small class="form-text text-muted">Leave blank to keep current password.</small><?php endif; ?>
          </div>
        </div>

        <div class="form-group">
          <label for="link_type" class="form-label">Link Type</label>
          <select id="link_type" name="link_type" class="form-control">
            <option value="direct" <?= $link->link_type === 'direct' ? 'selected' : '' ?>>Direct (301 Redirect)</option>
            <option value="interstitial" <?= $link->link_type === 'interstitial' ? 'selected' : '' ?>>Interstitial Page</option>
            <option value="deep_link" <?= $link->link_type === 'deep_link' ? 'selected' : '' ?>>Deep Link</option>
          </select>
        </div>

        <div class="form-group <?= $link->link_type === 'deep_link' ? '' : 'hidden' ?>" id="deep-link-wrapper">
          <label for="deep_link_scheme" class="form-label">Deep Link Scheme</label>
          <input type="text" id="deep_link_scheme" name="deep_link_scheme" class="form-control" value="<?= $this->escape($link->deep_link_scheme ?? '') ?>" placeholder="myapp://path/to/content">
        </div>

        <div class="form-group">
          <div class="form-toggle">
            <input type="checkbox" id="is_cloaked" name="is_cloaked" class="toggle-input" <?= $link->is_cloaked ? 'checked' : '' ?>>
            <label for="is_cloaked" class="toggle-label">Enable Cloaking</label>
          </div>
          <p class="form-text text-warning">When enabled, the original URL is hidden and the short domain is shown in the address bar. This may conflict with some websites.</p>
        </div>

        <div class="card card-nested">
          <div class="card-header">
            <h3 class="card-title">UTM Parameters</h3>
            <button type="button" class="btn btn-sm btn-ghost" id="toggle-utm" aria-label="Toggle UTM builder">Toggle</button>
          </div>
          <div class="card-body hidden" id="utm-builder">
            <div class="utm-grid">
              <div class="form-group">
                <label for="utm_source" class="form-label">Source</label>
                <input type="text" id="utm_source" name="utm_source" class="form-control" value="<?= $this->escape($link->utm_source ?? '') ?>" placeholder="google">
              </div>
              <div class="form-group">
                <label for="utm_medium" class="form-label">Medium</label>
                <input type="text" id="utm_medium" name="utm_medium" class="form-control" value="<?= $this->escape($link->utm_medium ?? '') ?>" placeholder="cpc">
              </div>
              <div class="form-group">
                <label for="utm_campaign" class="form-label">Campaign</label>
                <input type="text" id="utm_campaign" name="utm_campaign" class="form-control" value="<?= $this->escape($link->utm_campaign ?? '') ?>" placeholder="spring_sale">
              </div>
              <div class="form-group">
                <label for="utm_term" class="form-label">Term</label>
                <input type="text" id="utm_term" name="utm_term" class="form-control" value="<?= $this->escape($link->utm_term ?? '') ?>" placeholder="running+shoes">
              </div>
              <div class="form-group">
                <label for="utm_content" class="form-label">Content</label>
                <input type="text" id="utm_content" name="utm_content" class="form-control" value="<?= $this->escape($link->utm_content ?? '') ?>" placeholder="hero_banner">
              </div>
            </div>
            <div class="utm-preview">
              <label class="form-label">URL Preview</label>
              <code id="url-preview" class="preview-box"><?= $this->escape($link->original_url ?? '') ?></code>
            </div>
          </div>
        </div>

        <div class="form-group">
          <div class="form-toggle">
            <input type="checkbox" id="is_active" name="is_active" class="toggle-input" value="1" <?= $link->is_active ? 'checked' : '' ?>>
            <label for="is_active" class="toggle-label">Active</label>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Update Link</button>
          <a href="/links" class="btn btn-ghost">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var passwordToggle = document.getElementById('password_enabled');
  var passwordWrapper = document.getElementById('password-field-wrapper');
  if (passwordToggle && passwordWrapper) {
    passwordToggle.addEventListener('change', function() {
      passwordWrapper.classList.toggle('hidden', !this.checked);
    });
  }

  var linkType = document.getElementById('link_type');
  var deepLinkWrapper = document.getElementById('deep-link-wrapper');
  if (linkType && deepLinkWrapper) {
    linkType.addEventListener('change', function() {
      deepLinkWrapper.classList.toggle('hidden', this.value !== 'deep_link');
    });
  }

  var checkBtn = document.getElementById('check-availability');
  var slugInput = document.getElementById('slug');
  var resultSpan = document.getElementById('availability-result');
  if (checkBtn && slugInput && resultSpan) {
    checkBtn.addEventListener('click', function() {
      var slug = slugInput.value.trim();
      if (slug.length < 3) { resultSpan.textContent = 'Slug must be at least 3 characters.'; resultSpan.className = 'form-text text-error'; return; }
      resultSpan.textContent = 'Checking...'; resultSpan.className = 'form-text';
      fetch('/check-slug?slug=' + encodeURIComponent(slug)).then(function(r) { return r.json(); }).then(function(d) {
        if (d.available) { resultSpan.textContent = 'Slug is available!'; resultSpan.className = 'form-text text-success'; }
        else { resultSpan.textContent = 'Slug is already taken.'; resultSpan.className = 'form-text text-error'; }
      }).catch(function() { resultSpan.textContent = 'Could not check availability.'; resultSpan.className = 'form-text text-error'; });
    });
  }

  var toggleUtm = document.getElementById('toggle-utm');
  var utmBuilder = document.getElementById('utm-builder');
  if (toggleUtm && utmBuilder) {
    toggleUtm.addEventListener('click', function() { utmBuilder.classList.toggle('hidden'); });
  }

  var utmFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
  var urlInput = document.getElementById('original_url');
  var urlPreview = document.getElementById('url-preview');
  function updatePreview() {
    var baseUrl = (urlInput ? urlInput.value.trim() : '') || 'https://example.com';
    var params = {};
    utmFields.forEach(function(f) {
      var el = document.getElementById(f);
      if (el && el.value.trim()) params[f] = el.value.trim();
    });
    if (Object.keys(params).length > 0) {
      var sep = baseUrl.indexOf('?') >= 0 ? '&' : '?';
      urlPreview.textContent = baseUrl + sep + new URLSearchParams(params).toString();
    } else {
      urlPreview.textContent = baseUrl;
    }
  }
  if (urlPreview) {
    utmFields.forEach(function(f) {
      var el = document.getElementById(f);
      if (el) el.addEventListener('input', updatePreview);
    });
    if (urlInput) urlInput.addEventListener('input', updatePreview);
  }
});
</script>
<?php $this->endSection(); ?>

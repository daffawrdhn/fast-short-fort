<?php $this->extend('layouts.app'); ?>

<?php $this->section('content'); ?>
<div class="password-container">
  <div class="password-card">
    <div class="password-header">
      <h1 class="password-title">Password Required</h1>
      <p class="password-subtitle text-muted">
        This link is password protected. Please enter the password to continue.
      </p>
      <p class="password-slug">
        Accessing: <code><?= $this->escape($slug ?? '') ?></code>
      </p>
    </div>
    <div class="password-body">
      <form action="/links/password/<?= $this->escape($slug ?? '') ?>/verify" method="POST" class="password-form" role="form" aria-label="Password verification form">
        <input type="hidden" name="_csrf" value="<?= $this->escape($csrf ?? session()->csrfToken()) ?>">

        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <input type="password" id="password" name="password" class="form-control form-control-lg" required placeholder="Enter link password" autocomplete="off" aria-describedby="password-help">
          <small id="password-help" class="form-text text-muted">Enter the password set by the link owner.</small>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Unlock Link</button>
      </form>
    </div>
    <div class="password-footer">
      <a href="/" class="btn btn-ghost">&larr; Go Home</a>
    </div>
  </div>
</div>
<?php $this->endSection(); ?>

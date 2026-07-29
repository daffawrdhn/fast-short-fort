<?php $this->extend('layouts.auth') ?>

<div class="auth-form">
  <h1 class="auth-title">Two-Factor Authentication</h1>
  <p class="auth-subtitle">Enter the 6-digit code from your authenticator app.</p>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-error" role="alert">
    <?php foreach ($errors as $field => $messages): ?>
      <?php foreach ($messages as $message): ?>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="/twofa/verify">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
      <label for="code">Authentication Code</label>
      <input type="text" id="code" name="code" class="form-input form-input-code" placeholder="000000" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" autofocus>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Verify</button>
  </form>

  <div class="auth-links">
    <a href="/login">Back to Sign In</a>
  </div>
</div>

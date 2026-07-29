<?php $this->extend('layouts.auth') ?>

<div class="auth-form">
  <h1 class="auth-title">Reset Password</h1>
  <p class="auth-subtitle">Enter your new password.</p>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-error" role="alert">
    <?php foreach ($errors as $field => $messages): ?>
      <?php foreach ($messages as $message): ?>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="/reset-password">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
      <label for="password">New Password</label>
      <input type="password" id="password" name="password" class="form-input" placeholder="At least 8 characters" required autocomplete="new-password" minlength="8">
    </div>

    <div class="form-group">
      <label for="password_confirm">Confirm Password</label>
      <input type="password" id="password_confirm" name="password_confirm" class="form-input" placeholder="Repeat your password" required autocomplete="new-password">
    </div>

    <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
  </form>

  <div class="auth-links">
    <a href="/login">Back to Sign In</a>
  </div>
</div>

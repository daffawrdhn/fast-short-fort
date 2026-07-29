<?php $this->extend('layouts.auth') ?>

<div class="auth-form">
  <h1 class="auth-title">Forgot Password</h1>
  <p class="auth-subtitle">Enter your email and we'll send you a reset link.</p>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-error" role="alert">
    <?php foreach ($errors as $field => $messages): ?>
      <?php foreach ($messages as $message): ?>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="/forgot-password">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" class="form-input" placeholder="you@example.com" required autocomplete="email" autofocus
             value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
  </form>

  <div class="auth-links">
    <a href="/login">Back to Sign In</a>
  </div>
</div>

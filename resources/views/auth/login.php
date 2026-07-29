<?php $this->extend('layouts.auth') ?>

<div class="auth-form">
  <h1 class="auth-title">Sign In</h1>
  <p class="auth-subtitle">Welcome back to FORT</p>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-error" role="alert">
    <?php foreach ($errors as $field => $messages): ?>
      <?php foreach ($messages as $message): ?>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="/login" novalidate>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" class="form-input" placeholder="you@example.com" required autocomplete="email" autofocus
             value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required autocomplete="current-password">
    </div>

    <div class="form-group form-check">
      <label class="checkbox-label">
        <input type="checkbox" name="remember_me" value="1">
        <span>Remember me</span>
      </label>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Sign In</button>
  </form>

  <div class="auth-links">
    <a href="/register">Create an account</a>
    <a href="/forgot-password">Forgot password?</a>
  </div>
</div>

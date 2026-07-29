<?php $this->extend('layouts.auth') ?>

<div class="auth-form">
  <h1 class="auth-title">Verify Your Email</h1>
  <p class="auth-subtitle">We sent a verification link to your email address.</p>

  <div class="auth-message">
    <p>Please check your inbox and click the verification link to activate your account.</p>
    <p>If you did not receive the email, check your spam folder or click the button below to resend.</p>
  </div>

  <form method="POST" action="/email/verification/resend">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" class="btn btn-primary btn-block">Resend Verification Email</button>
  </form>

  <div class="auth-links">
    <a href="/login">Back to Sign In</a>
  </div>
</div>

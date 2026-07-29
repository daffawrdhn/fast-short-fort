<?php $this->extend('layouts.auth') ?>

<div class="auth-form">
  <h1 class="auth-title">Create Account</h1>
  <p class="auth-subtitle">Join FORT URL Shortener</p>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-error" role="alert">
    <?php foreach ($errors as $field => $messages): ?>
      <?php foreach ($messages as $message): ?>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="/register" novalidate>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
      <label for="name">Full Name</label>
      <input type="text" id="name" name="name" class="form-input" placeholder="John Doe" required autocomplete="name" autofocus
             value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" class="form-input" placeholder="you@example.com" required autocomplete="email"
             value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" class="form-input" placeholder="At least 8 characters" required autocomplete="new-password" minlength="8">
      <div class="password-strength" id="password-strength">
        <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
        <span class="strength-text" id="strength-text"></span>
      </div>
    </div>

    <div class="form-group">
      <label for="password_confirm">Confirm Password</label>
      <input type="password" id="password_confirm" name="password_confirm" class="form-input" placeholder="Repeat your password" required autocomplete="new-password">
    </div>

    <div class="form-group form-check">
      <label class="checkbox-label">
        <input type="checkbox" name="terms" value="1" required>
        <span>I agree to the <a href="/terms" target="_blank">Terms of Service</a> and <a href="/privacy" target="_blank">Privacy Policy</a></span>
      </label>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Create Account</button>
  </form>

  <div class="auth-links">
    <a href="/login">Already have an account? Sign in</a>
  </div>
</div>

<script>
(function() {
  var input = document.getElementById('password');
  var fill = document.getElementById('strength-fill');
  var text = document.getElementById('strength-text');
  if (!input || !fill || !text) return;
  input.addEventListener('input', function() {
    var val = this.value;
    var score = 0;
    if (val.length >= 8) score += 25;
    if (val.length >= 12) score += 15;
    if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score += 20;
    if (/\d/.test(val)) score += 15;
    if (/[^a-zA-Z0-9]/.test(val)) score += 25;
    score = Math.min(score, 100);
    fill.style.width = score + '%';
    if (score < 30) {
      fill.style.background = '#ef4444';
      text.textContent = 'Weak';
    } else if (score < 60) {
      fill.style.background = '#f59e0b';
      text.textContent = 'Medium';
    } else if (score < 80) {
      fill.style.background = '#22c55e';
      text.textContent = 'Strong';
    } else {
      fill.style.background = '#16a34a';
      text.textContent = 'Very Strong';
    }
  });
})();
</script>

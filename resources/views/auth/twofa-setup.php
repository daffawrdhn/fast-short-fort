<?php $this->extend('layouts.auth') ?>

<div class="auth-form">
  <h1 class="auth-title">Set Up Two-Factor Authentication</h1>
  <p class="auth-subtitle">Scan the QR code with your authenticator app.</p>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-error" role="alert">
    <?php foreach ($errors as $field => $messages): ?>
      <?php foreach ($messages as $message): ?>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="twofa-qr-container">
    <img src="<?= htmlspecialchars($qrCode ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="QR Code for 2FA setup" class="twofa-qr">
  </div>

  <div class="twofa-secret">
    <p>Or manually enter this key:</p>
    <code class="secret-key"><?= htmlspecialchars($secret ?? '', ENT_QUOTES, 'UTF-8') ?></code>
  </div>

  <form method="POST" action="/twofa/setup">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="secret" value="<?= htmlspecialchars($secret ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
      <label for="code">Verify Code</label>
      <input type="text" id="code" name="code" class="form-input form-input-code" placeholder="000000" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="off" autofocus>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Enable 2FA</button>
  </form>

  <div class="auth-links">
    <a href="/profile">Skip for now</a>
  </div>
</div>

<?php $this->extend('layouts.app'); ?>

<?php $this->section('content'); ?>
<div class="profile-container">
  <div class="page-header">
    <h1 class="page-title">Profile</h1>
    <p class="page-subtitle text-muted">Manage your account settings</p>
  </div>

  <?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash['success']) ?></div>
  <?php endif; ?>
  <?php if (!empty($flash['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($flash['error']) ?></div>
  <?php endif; ?>

  <form method="POST" action="/profile" class="profile-form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Session::getInstance()->csrfToken()) ?>">

    <div class="form-group">
      <label for="name">Name</label>
      <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($profileUser->name ?? '') ?>" required>
    </div>

    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($profileUser->email ?? '') ?>" required>
    </div>

    <hr>

    <h2>Change Password</h2>

    <div class="form-group">
      <label for="current_password">Current Password</label>
      <input type="password" class="form-control" id="current_password" name="current_password">
    </div>

    <div class="form-group">
      <label for="password">New Password</label>
      <input type="password" class="form-control" id="password" name="password">
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
  </form>
</div>
<?php $this->endSection(); ?>

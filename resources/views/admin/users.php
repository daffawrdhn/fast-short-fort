<div class="admin-page-header">
  <h1>User Management</h1>
  <div class="header-actions">
    <a href="#create-user-modal" class="btn btn-primary" data-modal-toggle aria-label="Create new user">+ Create User</a>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <form method="get" action="/admin/users" class="search-form" role="search" aria-label="Search users">
      <div class="search-input-group">
        <input type="search" name="search" value="<?= htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by name or email..." aria-label="Search users">
        <button type="submit" class="btn btn-sm">Search</button>
        <?php if (!empty($search)): ?>
        <a href="/admin/users" class="btn btn-sm btn-ghost">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="table-responsive">
  <table class="table" role="table" aria-label="Users list">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Admin</th>
        <th>Verified</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($users)): ?>
      <tr>
        <td colspan="7" class="text-center text-muted">No users found.</td>
      </tr>
      <?php else: ?>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= htmlspecialchars((string)($u['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($u['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= !empty($u['is_admin']) ? 'Yes' : 'No' ?></td>
        <td><?= !empty($u['email_verified_at']) ? 'Yes' : 'No' ?></td>
        <td><?= htmlspecialchars($u['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td class="actions-cell">
          <a href="#edit-user-<?= $u['id'] ?? 0 ?>" class="btn btn-sm" data-modal-toggle aria-label="Edit user">Edit</a>
          <form method="post" action="/admin/users/<?= $u['id'] ?? 0 ?>/impersonate" class="inline-form" onsubmit="return confirm('WARNING: You will be logged in as this user. All actions will be performed as this user. Continue?');">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-sm btn-warning" aria-label="Impersonate user">Impersonate</button>
          </form>
          <form method="post" action="/admin/users/<?= $u['id'] ?? 0 ?>/delete" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-sm btn-danger" aria-label="Delete user">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (!empty($totalPages) && $totalPages > 1): ?>
<nav class="pagination" aria-label="User list pagination">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
  <a href="/admin/users?page=<?= $p ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-link <?= ($page ?? 1) == $p ? 'active' : '' ?>" aria-label="Page <?= $p ?>"><?= $p ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>

<div id="create-user-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="create-user-title">
  <div class="modal-overlay" data-modal-close></div>
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="create-user-title">Create User</h2>
      <button class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </div>
    <form method="post" action="/admin/users/create">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <div class="modal-body">
        <div class="form-group">
          <label for="create-name">Name</label>
          <input type="text" id="create-name" name="name" required aria-required="true">
        </div>
        <div class="form-group">
          <label for="create-email">Email</label>
          <input type="email" id="create-email" name="email" required aria-required="true">
        </div>
        <div class="form-group">
          <label for="create-password">Password</label>
          <input type="password" id="create-password" name="password" required minlength="8" aria-required="true">
        </div>
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="is_admin" value="1">
            Admin
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Create</button>
      </div>
    </form>
  </div>
</div>

<?php foreach ($users as $u): ?>
<div id="edit-user-<?= $u['id'] ?? 0 ?>" class="modal" role="dialog" aria-modal="true" aria-labelledby="edit-user-title-<?= $u['id'] ?? 0 ?>">
  <div class="modal-overlay" data-modal-close></div>
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="edit-user-title-<?= $u['id'] ?? 0 ?>">Edit User: <?= htmlspecialchars($u['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
      <button class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </div>
    <form method="post" action="/admin/users/<?= $u['id'] ?? 0 ?>/edit">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <div class="modal-body">
        <div class="form-group">
          <label for="edit-name-<?= $u['id'] ?? 0 ?>">Name</label>
          <input type="text" id="edit-name-<?= $u['id'] ?? 0 ?>" name="name" value="<?= htmlspecialchars($u['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
          <label for="edit-email-<?= $u['id'] ?? 0 ?>">Email</label>
          <input type="email" id="edit-email-<?= $u['id'] ?? 0 ?>" name="email" value="<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
          <label for="edit-password-<?= $u['id'] ?? 0 ?>">Password (leave blank to keep current)</label>
          <input type="password" id="edit-password-<?= $u['id'] ?? 0 ?>" name="password" minlength="8">
        </div>
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="is_admin" value="1" <?= !empty($u['is_admin']) ? 'checked' : '' ?>>
            Admin
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

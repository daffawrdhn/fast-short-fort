<div class="admin-page-header">
  <h1>Workspace Management</h1>
</div>

<div class="card">
  <div class="card-body">
    <form method="get" action="/admin/workspaces" class="search-form" role="search" aria-label="Search workspaces">
      <div class="search-input-group">
        <input type="search" name="search" value="<?= htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by name or slug..." aria-label="Search workspaces">
        <button type="submit" class="btn btn-sm">Search</button>
        <?php if (!empty($search)): ?>
        <a href="/admin/workspaces" class="btn btn-sm btn-ghost">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="table-responsive">
  <table class="table" role="table" aria-label="Workspaces list">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Slug</th>
        <th>Owner</th>
        <th>Plan</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($workspaces)): ?>
      <tr>
        <td colspan="7" class="text-center text-muted">No workspaces found.</td>
      </tr>
      <?php else: ?>
      <?php foreach ($workspaces as $w): ?>
      <tr>
        <td><?= htmlspecialchars((string)($w['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($w['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($w['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($w['owner_name'] ?? $w['owner_email'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="badge badge-<?= htmlspecialchars($w['plan'] ?? 'free', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($w['plan'] ?? 'free', ENT_QUOTES, 'UTF-8') ?></span></td>
        <td><?= htmlspecialchars($w['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td class="actions-cell">
          <a href="#edit-plan-<?= $w['id'] ?? 0 ?>" class="btn btn-sm" data-modal-toggle aria-label="Edit plan">Edit Plan</a>
          <form method="post" action="/admin/workspaces/<?= $w['id'] ?? 0 ?>/delete" class="inline-form" onsubmit="return confirm('Delete this workspace and all associated data?');">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-sm btn-danger" aria-label="Delete workspace">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (!empty($totalPages) && $totalPages > 1): ?>
<nav class="pagination" aria-label="Workspace list pagination">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
  <a href="/admin/workspaces?page=<?= $p ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-link <?= ($page ?? 1) == $p ? 'active' : '' ?>"><?= $p ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>

<?php foreach ($workspaces as $w): ?>
<div id="edit-plan-<?= $w['id'] ?? 0 ?>" class="modal" role="dialog" aria-modal="true" aria-labelledby="edit-plan-title-<?= $w['id'] ?? 0 ?>">
  <div class="modal-overlay" data-modal-close></div>
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="edit-plan-title-<?= $w['id'] ?? 0 ?>">Edit Plan: <?= htmlspecialchars($w['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
      <button class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </div>
    <form method="post" action="/admin/workspaces/<?= $w['id'] ?? 0 ?>/edit">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <div class="modal-body">
        <div class="form-group">
          <label for="plan-<?= $w['id'] ?? 0 ?>">Plan</label>
          <select id="plan-<?= $w['id'] ?? 0 ?>" name="plan" aria-label="Select plan">
            <option value="free" <?= ($w['plan'] ?? '') === 'free' ? 'selected' : '' ?>>Free</option>
            <option value="pro" <?= ($w['plan'] ?? '') === 'pro' ? 'selected' : '' ?>>Pro</option>
            <option value="business" <?= ($w['plan'] ?? '') === 'business' ? 'selected' : '' ?>>Business</option>
            <option value="enterprise" <?= ($w['plan'] ?? '') === 'enterprise' ? 'selected' : '' ?>>Enterprise</option>
          </select>
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

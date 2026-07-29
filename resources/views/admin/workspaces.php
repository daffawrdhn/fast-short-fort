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

<?php
$currentLimit = $perPage ?? 10;
$searchParam = !empty($search) ? '&search=' . urlencode($search) : '';
$limitParam = '&limit=' . $currentLimit;
?>
<div class="pagination-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
  <div class="pagination-limit">
    <label for="limit-select" class="text-muted" style="font-size: 0.875rem; margin-right: 0.5rem;">Rows per page:</label>
    <select id="limit-select" class="form-control" style="width: auto; display: inline-block; padding: 0.25rem 0.5rem; height: auto;" onchange="window.location.href='?page=1<?= $searchParam ?>&limit='+this.value">
      <option value="10" <?= $currentLimit == 10 ? 'selected' : '' ?>>10</option>
      <option value="25" <?= $currentLimit == 25 ? 'selected' : '' ?>>25</option>
      <option value="50" <?= $currentLimit == 50 ? 'selected' : '' ?>>50</option>
      <option value="100" <?= $currentLimit == 100 ? 'selected' : '' ?>>100</option>
    </select>
  </div>
  <?php if (!empty($totalPages) && $totalPages > 1): ?>
  <nav class="pagination" aria-label="Workspace list pagination" style="margin-top: 0;">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="/admin/workspaces?page=<?= $p ?><?= $searchParam ?><?= $limitParam ?>" class="page-link <?= ($page ?? 1) == $p ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
</div>

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

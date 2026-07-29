<div class="admin-page-header">
  <h1>Audit Logs</h1>
  <p class="text-muted">System activity and audit trail</p>
</div>

<div class="card">
  <div class="card-body">
    <form method="get" action="/admin/logs" class="filter-form" role="search" aria-label="Filter audit logs">
      <div class="filter-grid">
        <div class="form-group">
          <label for="filter-action">Action</label>
          <select id="filter-action" name="action" aria-label="Filter by action">
            <option value="">All Actions</option>
            <?php foreach (($actions ?? []) as $a): ?>
            <option value="<?= htmlspecialchars($a, ENT_QUOTES, 'UTF-8') ?>" <?= ($action ?? '') === $a ? 'selected' : '' ?>><?= htmlspecialchars($a, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="filter-user">User ID</label>
          <input type="text" id="filter-user" name="user_id" value="<?= htmlspecialchars($userId ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="User ID" aria-label="Filter by user ID">
        </div>
        <div class="form-group">
          <label for="filter-date-from">From</label>
          <input type="date" id="filter-date-from" name="date_from" value="<?= htmlspecialchars($dateFrom ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-label="Filter by start date">
        </div>
        <div class="form-group">
          <label for="filter-date-to">To</label>
          <input type="date" id="filter-date-to" name="date_to" value="<?= htmlspecialchars($dateTo ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-label="Filter by end date">
        </div>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn btn-sm">Apply Filters</button>
        <a href="/admin/logs" class="btn btn-sm btn-ghost">Clear Filters</a>
      </div>
    </form>
  </div>
</div>

<div class="table-responsive">
  <table class="table" role="table" aria-label="Audit logs">
    <thead>
      <tr>
        <th>ID</th>
        <th>User ID</th>
        <th>Action</th>
        <th>Details</th>
        <th>IP Address</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($auditLogs)): ?>
      <tr>
        <td colspan="6" class="text-center text-muted">No audit logs found.</td>
      </tr>
      <?php else: ?>
      <?php foreach ($auditLogs as $log): ?>
      <tr>
        <td><?= htmlspecialchars((string)($log['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($log['user_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="badge badge-action"><?= htmlspecialchars($log['action'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
        <td class="log-details"><?= htmlspecialchars(json_encode($log['details'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($log['ip_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($log['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (!empty($totalPages) && $totalPages > 1): ?>
<nav class="pagination" aria-label="Audit log pagination">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
  <a href="/admin/logs?page=<?= $p ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($userId) ? '&user_id=' . urlencode($userId) : '' ?><?= !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : '' ?><?= !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : '' ?>" class="page-link <?= ($page ?? 1) == $p ? 'active' : '' ?>" aria-label="Page <?= $p ?>"><?= $p ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>

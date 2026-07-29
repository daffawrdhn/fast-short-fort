<?php $this->extend('layouts.app'); ?>

<?php $this->section('content'); ?>
<div class="links-container">
  <div class="page-header">
    <h1 class="page-title">Links</h1>
    <div class="page-actions">
      <a href="/links/create" class="btn btn-primary" aria-label="Create new link">Create Link</a>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <form action="/links" method="GET" class="search-form" role="search" aria-label="Search links">
        <div class="search-row">
          <div class="search-input-group">
            <input type="search" name="search" class="form-control" placeholder="Search URLs, slugs, titles..." value="<?= $this->escape($search ?? '') ?>" aria-label="Search links">
            <button type="submit" class="btn btn-outline" aria-label="Search">Search</button>
            <?php if (!empty($search)): ?>
            <a href="/links" class="btn btn-ghost" aria-label="Clear search">Clear</a>
            <?php endif; ?>
          </div>
          <div class="filter-tabs" role="tablist" aria-label="Filter links">
            <a href="/links?filter=all<?= $search ? '&search=' . urlencode($search) : '' ?>" class="filter-tab <?= ($filter ?? 'all') === 'all' ? 'active' : '' ?>" role="tab">All</a>
            <a href="/links?filter=active<?= $search ? '&search=' . urlencode($search) : '' ?>" class="filter-tab <?= $filter === 'active' ? 'active' : '' ?>" role="tab">Active</a>
            <a href="/links?filter=expired<?= $search ? '&search=' . urlencode($search) : '' ?>" class="filter-tab <?= $filter === 'expired' ? 'active' : '' ?>" role="tab">Expired</a>
            <a href="/links?filter=inactive<?= $search ? '&search=' . urlencode($search) : '' ?>" class="filter-tab <?= $filter === 'inactive' ? 'active' : '' ?>" role="tab">Disabled</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <form action="/links/bulk" method="POST" id="bulk-form" aria-label="Bulk actions">
        <input type="hidden" name="_csrf" value="<?= $this->escape($csrf ?? session()->csrfToken()) ?>">

        <div class="bulk-toolbar" role="toolbar" aria-label="Bulk actions toolbar">
          <div class="bulk-actions">
            <button type="submit" formaction="/links/bulk/delete" class="btn btn-sm btn-danger" disabled id="bulk-delete" aria-label="Delete selected">Delete</button>
            <button type="submit" formaction="/links/bulk/enable" class="btn btn-sm btn-success" disabled id="bulk-enable" aria-label="Enable selected">Enable</button>
            <button type="submit" formaction="/links/bulk/disable" class="btn btn-sm btn-warning" disabled id="bulk-disable" aria-label="Disable selected">Disable</button>
            <span class="bulk-separator"></span>
            <a href="/links/export/csv" class="btn btn-sm btn-outline" aria-label="Export as CSV">Export CSV</a>
            <a href="/links/export/json" class="btn btn-sm btn-outline" aria-label="Export as JSON">Export JSON</a>
          </div>
          <span class="bulk-count text-muted" id="bulk-count" aria-live="polite"></span>
        </div>

        <?php if (!empty($links)): ?>
        <div class="table-responsive">
          <table class="table table-hover" role="table" aria-label="Links list">
            <thead>
              <tr>
                <th class="th-checkbox" scope="col">
                  <input type="checkbox" id="select-all" aria-label="Select all links">
                </th>
                <th scope="col">Short URL</th>
                <th scope="col">Original URL</th>
                <th scope="col" class="th-sortable <?= $sort === 'clicks' ? 'sorted' : '' ?>">
                  <a href="<?= $this->escape(buildSortUrl('clicks', $sort, $order, $search, $filter)) ?>" aria-label="Sort by clicks">Clicks <?= $sort === 'clicks' ? ($order === 'ASC' ? '&#9650;' : '&#9660;') : '' ?></a>
                </th>
                <th scope="col">Status</th>
                <th scope="col">Expires</th>
                <th scope="col" class="th-sortable <?= $sort === 'created_at' ? 'sorted' : '' ?>">
                  <a href="<?= $this->escape(buildSortUrl('created_at', $sort, $order, $search, $filter)) ?>" aria-label="Sort by date">Created <?= $sort === 'created_at' ? ($order === 'ASC' ? '&#9650;' : '&#9660;') : '' ?></a>
                </th>
                <th scope="col" class="th-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($links as $link): ?>
              <?php
                $isExpired = $link['expires_at'] !== null && strtotime($link['expires_at']) < time();
                $status = !$link['is_active'] ? 'Disabled' : ($isExpired ? 'Expired' : 'Active');
                $statusClass = !$link['is_active'] ? 'badge-danger' : ($isExpired ? 'badge-warning' : 'badge-success');
                $shortUrl = ($baseUrl ?? '') . '/' . $link['slug'];
              ?>
              <tr>
                <td class="td-checkbox"><input type="checkbox" name="ids[]" value="<?= $this->escape((string) $link['id']) ?>" class="select-item" aria-label="Select link <?= $this->escape($link['slug']) ?>"></td>
                <td class="td-short-url">
                  <div class="short-url-cell">
                    <a href="/go/<?= $this->escape($link['slug']) ?>" target="_blank" rel="noopener" class="short-url-label"><?= $this->escape($link['slug']) ?></a>
                    <button class="btn-copy btn-sm" data-clipboard-text="<?= $this->escape($shortUrl) ?>" aria-label="Copy short URL" title="Copy short URL"></button>
                  </div>
                </td>
                <td class="td-url">
                  <span class="truncate-text" title="<?= $this->escape($link['original_url']) ?>"><?= $this->escape(mb_substr($link['original_url'], 0, 60)) ?><?= mb_strlen($link['original_url']) > 60 ? '...' : '' ?></span>
                </td>
                <td class="td-clicks"><span class="click-count"><?= $this->escape((string) ($link['clicks'] ?? 0)) ?></span></td>
                <td class="td-status"><span class="badge <?= $statusClass ?>"><?= $status ?></span></td>
                <td class="td-expires"><?= $link['expires_at'] ? $this->escape(date('Y-m-d', strtotime($link['expires_at']))) : '<span class="text-muted">Never</span>' ?></td>
                <td class="td-created"><?= $this->escape(date('Y-m-d', strtotime($link['created_at']))) ?></td>
                <td class="td-actions">
                  <div class="action-buttons">
                    <a href="/links/<?= $this->escape((string) $link['id']) ?>" class="btn btn-icon btn-sm" aria-label="View details" title="View details">&#x1F50D;</a>
                    <a href="/links/<?= $this->escape((string) $link['id']) ?>/edit" class="btn btn-icon btn-sm" aria-label="Edit link" title="Edit link">&#x270F;&#xFE0F;</a>
                    <a href="/links/<?= $this->escape((string) $link['id']) ?>/toggle" class="btn btn-icon btn-sm" aria-label="Toggle active status" title="<?= $link['is_active'] ? 'Disable' : 'Enable' ?>"><?= $link['is_active'] ? '&#x23F8;&#xFE0F;' : '&#x25B6;&#xFE0F;' ?></a>
                    <a href="/links/<?= $this->escape((string) $link['id']) ?>/delete" class="btn btn-icon btn-sm btn-danger-icon" aria-label="Delete link" title="Delete link" onclick="return confirm('Move this link to trash?')">&#x1F5D1;&#xFE0F;</a>
                    <button class="btn btn-icon btn-sm" aria-label="Show QR code" title="Show QR code" onclick="showQR('<?= $this->escape($shortUrl) ?>', '<?= $this->escape($link['slug']) ?>')">&#x1F4F1;</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
          <p class="text-muted">No links found.</p>
          <a href="/links/create" class="btn btn-primary">Create your first link</a>
        </div>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <?php if (($totalPages ?? 1) > 1): ?>
    <?php $this->include('partials.pagination', ['currentPage' => $page ?? 1, 'totalPages' => $totalPages ?? 1, 'total' => $total ?? 0, 'perPage' => $perPage ?? 20, 'search' => $search ?? '', 'filter' => $filter ?? '']); ?>
  <?php endif; ?>
</div>

<?php $this->include('partials.qrcode-modal'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var selectAll = document.getElementById('select-all');
  if (selectAll) {
    selectAll.addEventListener('change', function() {
      var items = document.querySelectorAll('.select-item');
      items.forEach(function(item) { item.checked = selectAll.checked; });
      updateBulkActions();
    });
  }

  var items = document.querySelectorAll('.select-item');
  items.forEach(function(item) {
    item.addEventListener('change', updateBulkActions);
  });

  function updateBulkActions() {
    var checked = document.querySelectorAll('.select-item:checked').length;
    var btns = document.querySelectorAll('#bulk-delete, #bulk-enable, #bulk-disable');
    var countEl = document.getElementById('bulk-count');
    btns.forEach(function(btn) { btn.disabled = checked === 0; });
    if (countEl) countEl.textContent = checked > 0 ? checked + ' selected' : '';
  }
});

function showQR(url, slug) {
  var modal = document.getElementById('qrcode-modal');
  var img = document.getElementById('qrcode-image');
  var downloadPng = document.getElementById('qrcode-download-png');
  var downloadSvg = document.getElementById('qrcode-download-svg');
  if (modal) modal.style.display = 'flex';
  if (img) img.src = '/qrcode?url=' + encodeURIComponent(url);
  if (downloadPng) downloadPng.href = '/links/qr/' + slug + '/png';
  if (downloadSvg) downloadSvg.href = '/links/qr/' + slug + '/svg';
}
</script>
<?php $this->endSection(); ?>

<?php
function buildSortUrl(string $field, string $currentSort, string $currentOrder, string $search, string $filter): string {
  $params = ['sort' => $field, 'order' => ($field === $currentSort && $currentOrder === 'ASC') ? 'DESC' : 'ASC'];
  if ($search !== '') $params['search'] = $search;
  if ($filter !== 'all' && $filter !== '') $params['filter'] = $filter;
  return '/links?' . http_build_query($params);
}
?>

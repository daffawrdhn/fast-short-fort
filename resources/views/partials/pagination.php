<nav class="pagination-container" role="navigation" aria-label="Pagination">
  <div class="pagination-info text-muted">
    Showing <?= $this->escape((string) ((($currentPage ?? 1) - 1) * ($perPage ?? 20) + 1)) ?> to <?= $this->escape((string) min(($currentPage ?? 1) * ($perPage ?? 20), $total ?? 0)) ?> of <?= $this->escape((string) ($total ?? 0)) ?> results
  </div>

  <?php if (($totalPages ?? 1) > 1): ?>
  <ul class="pagination">
    <?php if (($currentPage ?? 1) > 1): ?>
    <li class="page-item">
      <a href="<?= $this->escape(buildPaginationUrl(($currentPage ?? 1) - 1, $search ?? '', $filter ?? '')) ?>" class="page-link" aria-label="Previous page">&laquo; Previous</a>
    </li>
    <?php else: ?>
    <li class="page-item disabled">
      <span class="page-link" aria-disabled="true">&laquo; Previous</span>
    </li>
    <?php endif; ?>

    <?php
      $startPage = max(1, ($currentPage ?? 1) - 2);
      $endPage = min($totalPages ?? 1, ($currentPage ?? 1) + 2);
      if ($startPage > 1): ?>
    <li class="page-item">
      <a href="<?= $this->escape(buildPaginationUrl(1, $search ?? '', $filter ?? '')) ?>" class="page-link" aria-label="Page 1">1</a>
    </li>
    <?php if ($startPage > 2): ?>
    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
    <?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
    <li class="page-item <?= $i === ($currentPage ?? 1) ? 'active' : '' ?>">
      <?php if ($i === ($currentPage ?? 1)): ?>
      <span class="page-link" aria-current="page"><?= $this->escape((string) $i) ?></span>
      <?php else: ?>
      <a href="<?= $this->escape(buildPaginationUrl($i, $search ?? '', $filter ?? '')) ?>" class="page-link" aria-label="Page <?= $this->escape((string) $i) ?>"><?= $this->escape((string) $i) ?></a>
      <?php endif; ?>
    </li>
    <?php endfor; ?>

    <?php if ($endPage < ($totalPages ?? 1)): ?>
    <?php if ($endPage < ($totalPages ?? 1) - 1): ?>
    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
    <?php endif; ?>
    <li class="page-item">
      <a href="<?= $this->escape(buildPaginationUrl($totalPages ?? 1, $search ?? '', $filter ?? '')) ?>" class="page-link" aria-label="Last page"><?= $this->escape((string) ($totalPages ?? 1)) ?></a>
    </li>
    <?php endif; ?>

    <?php if (($currentPage ?? 1) < ($totalPages ?? 1)): ?>
    <li class="page-item">
      <a href="<?= $this->escape(buildPaginationUrl(($currentPage ?? 1) + 1, $search ?? '', $filter ?? '')) ?>" class="page-link" aria-label="Next page">Next &raquo;</a>
    </li>
    <?php else: ?>
    <li class="page-item disabled">
      <span class="page-link" aria-disabled="true">Next &raquo;</span>
    </li>
    <?php endif; ?>
  </ul>
  <?php endif; ?>
</nav>

<?php
function buildPaginationUrl(int $page, string $search, string $filter): string {
  $params = ['page' => $page];
  if ($search !== '') $params['search'] = $search;
  if ($filter !== '' && $filter !== 'all') $params['filter'] = $filter;
  return '/links?' . http_build_query($params);
}
?>

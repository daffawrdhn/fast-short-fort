<?php $this->extend('layouts.app'); ?>

<?php $this->section('content'); ?>
<div class="dashboard-container">
  <div class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle text-muted">Overview of your workspace</p>
  </div>

  <div class="stats-grid" role="region" aria-label="Quick statistics">
    <div class="stat-card">
      <div class="stat-icon stat-icon-links" aria-hidden="true"></div>
      <div class="stat-value"><?= $this->escape((string) ($totalLinks ?? 0)) ?></div>
      <div class="stat-label">Total Links</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon stat-icon-clicks" aria-hidden="true"></div>
      <div class="stat-value"><?= $this->escape((string) ($totalClicks ?? 0)) ?></div>
      <div class="stat-label">Total Clicks</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon stat-icon-active" aria-hidden="true"></div>
      <div class="stat-value"><?= $this->escape((string) ($activeLinks ?? 0)) ?></div>
      <div class="stat-label">Active Links</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon stat-icon-expired" aria-hidden="true"></div>
      <div class="stat-value"><?= $this->escape((string) ($expiredLinks ?? 0)) ?></div>
      <div class="stat-label">Expired Links</div>
    </div>
  </div>

  <div class="card quick-shorten-card">
    <div class="card-header">
      <h2 class="card-title">Shorten a Link</h2>
    </div>
    <div class="card-body">
      <form action="/links" method="POST" class="quick-shorten-form" role="form" aria-label="Quick shorten form">
        <input type="hidden" name="_csrf" value="<?= $this->escape($csrf ?? session()->csrfToken()) ?>">
        <div class="input-group">
          <input type="url" name="original_url" class="form-control" placeholder="Paste a long URL..." required aria-label="Original URL">
          <button type="submit" class="btn btn-primary">Shorten</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Recent Links</h2>
      <a href="/links" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="card-body">
      <?php if (!empty($recentLinks)): ?>
      <div class="table-responsive">
        <table class="table" role="table" aria-label="Recent links">
          <thead>
            <tr>
              <th>Short URL</th>
              <th>Original URL</th>
              <th>Clicks</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentLinks as $link): ?>
            <?php
              $isExpired = $link['expires_at'] !== null && strtotime($link['expires_at']) < time();
              $status = !$link['is_active'] ? 'Disabled' : ($isExpired ? 'Expired' : 'Active');
              $statusClass = !$link['is_active'] ? 'badge-danger' : ($isExpired ? 'badge-warning' : 'badge-success');
            ?>
            <tr>
              <td>
                <a href="/go/<?= $this->escape($link['slug']) ?>" target="_blank" rel="noopener" class="short-url"><?= $this->escape($link['slug']) ?></a>
                <button class="btn-copy" data-clipboard-text="<?= $this->escape($baseUrl ?? '') . '/' . $this->escape($link['slug']) ?>" aria-label="Copy short URL" title="Copy short URL"></button>
              </td>
              <td class="url-cell">
                <span class="truncate-text" title="<?= $this->escape($link['original_url']) ?>"><?= $this->escape(mb_substr($link['original_url'], 0, 60)) ?><?= mb_strlen($link['original_url']) > 60 ? '...' : '' ?></span>
              </td>
              <td><span class="click-count"><?= $this->escape((string) ($link['clicks'] ?? 0)) ?></span></td>
              <td><span class="badge <?= $statusClass ?>"><?= $status ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <p class="text-muted">No links yet. Create your first link above.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php $this->endSection(); ?>

<?php $this->extend('layouts.app'); ?>

<?php $this->section('content'); ?>
<div class="dashboard-container">
  <div class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle text-muted">Overview of your workspace</p>
  </div>

  <div class="bento-grid">
    <!-- Stat 1: Total Links -->
    <div class="bento-card bento-col-3">
      <div class="stat-label">Total Links</div>
      <div class="stat-value"><?= $this->escape((string) ($totalLinks ?? 0)) ?></div>
    </div>

    <!-- Stat 2: Total Clicks -->
    <div class="bento-card bento-col-3">
      <div class="stat-label">Total Clicks</div>
      <div class="stat-value"><?= $this->escape((string) ($totalClicks ?? 0)) ?></div>
    </div>

    <!-- Stat 3: Active Links -->
    <div class="bento-card bento-col-3">
      <div class="stat-label">Active Links</div>
      <div class="stat-value"><?= $this->escape((string) ($activeLinks ?? 0)) ?></div>
    </div>

    <!-- Stat 4: Expired Links -->
    <div class="bento-card bento-col-3">
      <div class="stat-label">Expired Links</div>
      <div class="stat-value"><?= $this->escape((string) ($expiredLinks ?? 0)) ?></div>
    </div>

    <!-- Shorten Link Bento Card -->
    <div class="bento-card bento-col-4">
      <div class="card-header" style="border:none; padding:0; margin-bottom:1.5rem;">
        <h2 class="card-title" style="font-size:1.1rem; font-weight:600;">Shorten a Link</h2>
      </div>
      <div class="card-body" style="padding:0; display:flex; flex-direction:column; height:100%; justify-content:center;">
        <form action="/links" method="POST" class="quick-shorten-form" role="form" aria-label="Quick shorten form" style="display:flex; flex-direction:column; gap:1.25rem;">
          <input type="hidden" name="_csrf" value="<?= $this->escape($csrf ?? session()->csrfToken()) ?>">
          <div class="form-group" style="margin:0;">
            <input type="url" name="original_url" class="form-control" placeholder="Paste a long URL..." required aria-label="Original URL" style="width:100%; padding:0.625rem 0.875rem;">
          </div>
          <button type="submit" class="btn btn-primary btn-block" style="padding:0.625rem 1rem;">Shorten</button>
        </form>
      </div>
    </div>

    <!-- Recent Links Bento Card -->
    <div class="bento-card bento-col-8">
      <div class="card-header" style="border:none; padding:0; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
        <h2 class="card-title" style="font-size:1.1rem; font-weight:600;">Recent Links</h2>
        <a href="/links" class="btn btn-sm btn-outline">View All</a>
      </div>
      <div class="card-body" style="padding:0; flex-grow:1; display:flex; flex-direction:column;">
        <?php if (!empty($recentLinks)): ?>
        <div class="table-responsive" style="border:none; background:transparent;">
          <table class="table" role="table" aria-label="Recent links">
            <thead>
              <tr>
                <th style="padding-left:0;">Short URL</th>
                <th>Original URL</th>
                <th>Clicks</th>
                <th style="padding-right:0;">Status</th>
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
                <td style="padding-left:0;">
                  <div style="display:flex; align-items:center; gap:0.5rem;">
                    <a href="/<?= $this->escape($link['slug']) ?>" target="_blank" rel="noopener" class="short-url" style="font-weight:600; color:var(--text-primary);"><?= $this->escape($link['slug']) ?></a>
                    <button type="button" class="btn-copy" data-clipboard-text="<?= $this->escape($baseUrl ?? '') . '/' . $this->escape($link['slug']) ?>" aria-label="Copy short URL" title="Copy short URL" style="border:none; background:transparent; cursor:pointer; width:14px; height:14px;"></button>
                  </div>
                </td>
                <td class="url-cell">
                  <span class="truncate-text" title="<?= $this->escape($link['original_url']) ?>" style="max-width:350px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= $this->escape($link['original_url']) ?></span>
                </td>
                <td><span class="click-count" style="font-weight:500;"><?= $this->escape((string) ($link['clicks'] ?? 0)) ?></span></td>
                <td style="padding-right:0;"><span class="badge <?= $statusClass ?>"><?= $status ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="margin:2rem auto; text-align:center;">
          <p class="text-muted">No links created yet.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php $this->endSection(); ?>

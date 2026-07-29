<?php $this->extend('layouts.app'); ?>

<?php $this->section('content'); ?>
<div class="link-detail-container">
  <div class="page-header">
    <h1 class="page-title">Link Details</h1>
    <div class="page-actions">
      <a href="/links/<?= $this->escape((string) $link->id) ?>/edit" class="btn btn-primary" aria-label="Edit link">Edit</a>
      <a href="/links/<?= $this->escape((string) $link->id) ?>/delete" class="btn btn-danger" aria-label="Delete link" onclick="return confirm('Move this link to trash?')">Delete</a>
      <a href="/links" class="btn btn-outline" aria-label="Back to links">&larr; Back</a>
    </div>
  </div>

  <div class="detail-grid">
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Link Information</h2>
      </div>
      <div class="card-body">
        <dl class="detail-list">
          <div class="detail-row">
            <dt class="detail-label">Original URL</dt>
            <dd class="detail-value">
              <span class="truncate-text" title="<?= $this->escape($link->original_url ?? '') ?>"><?= $this->escape(mb_substr($link->original_url ?? '', 0, 80)) ?><?= mb_strlen($link->original_url ?? '') > 80 ? '...' : '' ?></span>
              <a href="<?= $this->escape($link->original_url ?? '') ?>" target="_blank" rel="noopener" class="btn btn-icon btn-sm" aria-label="Open original URL">&#x2197;&#xFE0F;</a>
            </dd>
          </div>
          <div class="detail-row">
            <dt class="detail-label">Short URL</dt>
            <dd class="detail-value">
              <code class="short-url-text"><?= $this->escape($shortUrl ?? '') ?></code>
              <button class="btn-copy btn-sm" data-clipboard-text="<?= $this->escape($shortUrl ?? '') ?>" aria-label="Copy short URL" title="Copy short URL"></button>
              <a href="/<?= $this->escape($link->slug) ?>" target="_blank" rel="noopener" class="btn btn-icon btn-sm" aria-label="Open short URL">&#x2197;&#xFE0F;</a>
            </dd>
          </div>
          <div class="detail-row">
            <dt class="detail-label">Created</dt>
            <dd class="detail-value"><?= $this->escape($link->created_at ?? 'N/A') ?></dd>
          </div>
          <div class="detail-row">
            <dt class="detail-label">Expiration</dt>
            <dd class="detail-value"><?= $link->expires_at ? $this->escape($link->expires_at) : '<span class="text-muted">Never</span>' ?></dd>
          </div>
          <div class="detail-row">
            <dt class="detail-label">Status</dt>
            <dd class="detail-value">
              <?php
                $isExpired = $link->expires_at !== null && strtotime($link->expires_at) < time();
                $status = !$link->is_active ? 'Disabled' : ($isExpired ? 'Expired' : 'Active');
                $statusClass = !$link->is_active ? 'badge-danger' : ($isExpired ? 'badge-warning' : 'badge-success');
              ?>
              <span class="badge <?= $statusClass ?>"><?= $status ?></span>
            </dd>
          </div>
          <div class="detail-row">
            <dt class="detail-label">Type</dt>
            <dd class="detail-value"><?= $this->escape(ucfirst($link->link_type ?? 'direct')) ?></dd>
          </div>
          <?php if ($link->link_type === 'deep_link' && $link->deep_link_scheme): ?>
          <div class="detail-row">
            <dt class="detail-label">Deep Link Scheme</dt>
            <dd class="detail-value"><code><?= $this->escape($link->deep_link_scheme) ?></code></dd>
          </div>
          <?php endif; ?>
          <?php if ($link->password_hash): ?>
          <div class="detail-row">
            <dt class="detail-label">Password Protected</dt>
            <dd class="detail-value"><span class="badge badge-warning">Yes</span></dd>
          </div>
          <?php endif; ?>
          <?php if ($link->is_cloaked): ?>
          <div class="detail-row">
            <dt class="detail-label">Cloaked</dt>
            <dd class="detail-value"><span class="badge badge-info">Yes</span></dd>
          </div>
          <?php endif; ?>
        </dl>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">QR Code</h2>
      </div>
      <div class="card-body qr-card-body">
        <div class="qr-display">
          <img src="/qrcode?url=<?= urlencode($shortUrl ?? '') ?>" alt="QR Code for <?= $this->escape($shortUrl ?? '') ?>" class="qr-image" id="qr-image">
        </div>
        <div class="qr-actions">
          <a href="/links/qr/<?= $this->escape((string) $link->id) ?>/png" class="btn btn-sm btn-outline" download aria-label="Download QR as PNG">Download PNG</a>
          <a href="/links/qr/<?= $this->escape((string) $link->id) ?>/svg" class="btn btn-sm btn-outline" download aria-label="Download QR as SVG">Download SVG</a>
        </div>
      </div>
    </div>

    <div class="card card-full">
      <div class="card-header">
        <h2 class="card-title">Analytics Summary</h2>
      </div>
      <div class="card-body">
        <div class="stats-grid stats-grid-sm">
          <div class="stat-card stat-card-sm">
            <div class="stat-value"><?= $this->escape((string) ($stats['total_clicks'] ?? 0)) ?></div>
            <div class="stat-label">Total Clicks</div>
          </div>
          <div class="stat-card stat-card-sm">
            <div class="stat-value"><?= $this->escape((string) ($stats['unique_clicks'] ?? 0)) ?></div>
            <div class="stat-label">Unique Clicks</div>
          </div>
          <div class="stat-card stat-card-sm">
            <div class="stat-value"><?= $this->escape((string) count($stats['countries_data'] ?? [])) ?></div>
            <div class="stat-label">Countries</div>
          </div>
          <div class="stat-card stat-card-sm">
            <div class="stat-value"><?= $this->escape((string) count($stats['browsers'] ?? [])) ?></div>
            <div class="stat-label">Browsers</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card card-full">
      <div class="card-header">
        <h2 class="card-title">Recent Clicks</h2>
      </div>
      <div class="card-body">
        <?php if (!empty($recentClicks)): ?>
        <div class="table-responsive">
          <table class="table table-compact" role="table" aria-label="Recent clicks">
            <thead>
              <tr>
                <th>Country</th>
                <th>Device</th>
                <th>Browser</th>
                <th>OS</th>
                <th>Referrer</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentClicks as $click): ?>
              <tr>
                <td><?= $this->escape($click['country'] ?? 'Unknown') ?></td>
                <td><?= $this->escape($click['device_type'] ?? 'Unknown') ?></td>
                <td><?= $this->escape($click['browser'] ?? 'Unknown') ?></td>
                <td><?= $this->escape($click['os'] ?? 'Unknown') ?></td>
                <td><?= $this->escape(mb_substr($click['referrer'] ?? 'Direct', 0, 40)) ?></td>
                <td><?= $this->escape(date('Y-m-d H:i', strtotime($click['clicked_at']))) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
          <p class="text-muted">No clicks recorded yet.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php $this->endSection(); ?>

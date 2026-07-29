<?php $this->extend('layouts.app'); ?>

<?php $this->section('content'); ?>
<div class="analytics-container">
  <div class="page-header">
    <div class="page-header-row">
      <div>
        <a href="/analytics" class="back-link">&larr; Back to Analytics</a>
        <h1 class="page-title">Link Analytics</h1>
        <?php if ($stats['link']): ?>
        <p class="page-subtitle">
          <strong>/<?= htmlspecialchars($stats['link']['slug'], ENT_QUOTES, 'UTF-8') ?></strong>
          &rarr; <?= htmlspecialchars($stats['link']['original_url'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php endif; ?>
      </div>
      <div class="page-actions">
        <button class="btn btn-outline" onclick="exportLinkAnalytics('csv')">CSV</button>
        <button class="btn btn-outline" onclick="exportLinkAnalytics('json')">JSON</button>
      </div>
    </div>
  </div>

  <form method="GET" action="/analytics/<?= $linkId ?>" class="analytics-filters">
    <div class="filter-group">
      <label for="start_date">From</label>
      <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="filter-group">
      <label for="end_date">To</label>
      <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <button type="submit" class="btn btn-primary">Apply</button>
  </form>

  <div class="stats-cards">
    <div class="stat-card">
      <div class="stat-value"><?= number_format($stats['total_clicks'] ?? 0) ?></div>
      <div class="stat-label">Total Clicks</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= number_format($stats['unique_clicks'] ?? 0) ?></div>
      <div class="stat-label">Unique Clicks</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= number_format($stats['countries'] ?? 0) ?></div>
      <div class="stat-label">Countries</div>
    </div>
  </div>

  <div class="charts-grid">
    <div class="chart-card full-width">
      <h3 class="chart-title">Clicks Over Time</h3>
      <div class="chart-wrapper">
        <canvas id="chart-timeseries"></canvas>
      </div>
    </div>

    <div class="chart-card">
      <h3 class="chart-title">By Country</h3>
      <div class="chart-wrapper">
        <canvas id="chart-country"></canvas>
      </div>
    </div>

    <div class="chart-card">
      <h3 class="chart-title">By Device</h3>
      <div class="chart-wrapper">
        <canvas id="chart-device"></canvas>
      </div>
    </div>

    <div class="chart-card">
      <h3 class="chart-title">By Browser</h3>
      <div class="chart-wrapper">
        <canvas id="chart-browser"></canvas>
      </div>
    </div>

    <div class="chart-card">
      <h3 class="chart-title">By Operating System</h3>
      <div class="chart-wrapper">
        <canvas id="chart-os"></canvas>
      </div>
    </div>

    <div class="chart-card">
      <h3 class="chart-title">Top Referrers</h3>
      <div class="chart-wrapper">
        <canvas id="chart-referrer"></canvas>
      </div>
    </div>
  </div>

  <div class="map-card">
    <h3 class="map-title">Click Locations</h3>
    <div id="geo-map" class="map-placeholder">
      <p>Geolocation map visualization available with a map library integration (Leaflet/Google Maps).</p>
    </div>
  </div>

  <div class="table-card">
    <div class="table-header">
      <h3 class="table-title">Recent Clicks</h3>
      <span class="badge badge-live" id="live-indicator">Live</span>
    </div>
    <div class="table-responsive">
      <table class="table" id="recent-clicks-table">
        <thead>
          <tr>
            <th>Time</th>
            <th>Country</th>
            <th>City</th>
            <th>Device</th>
            <th>Browser</th>
            <th>OS</th>
            <th>Referrer</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($stats['recent_clicks'])): ?>
          <?php foreach ($stats['recent_clicks'] as $click): ?>
          <tr>
            <td><?= htmlspecialchars($click['clicked_at'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($click['country'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($click['city'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($click['device_type'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($click['browser'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($click['os'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="url-cell"><?= htmlspecialchars($click['referrer'] ?? 'Direct', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php else: ?>
          <tr><td colspan="7" class="empty-state">No clicks recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  function renderChart(id, type, labels, datasets, opts) {
    var el = document.getElementById(id);
    if (!el) return;
    new Chart(el, { type: type, data: { labels: labels, datasets: datasets }, options: Object.assign({ responsive: true, maintainAspectRatio: false }, opts || {}) });
  }

  <?php if (!empty($stats['clicks_over_time'])): ?>
  renderChart('chart-timeseries', 'line',
    <?= json_encode(array_column($stats['clicks_over_time'], 'label')) ?>,
    [{ label: 'Clicks', data: <?= json_encode(array_map('intval', array_column($stats['clicks_over_time'], 'count'))) ?>, borderColor: '#3b82f6', fill: true }],
    { plugins: { legend: { display: false } } }
  );
  <?php endif; ?>

  <?php if (!empty($stats['countries_data'])): ?>
  renderChart('chart-country', 'pie',
    <?= json_encode(array_column($stats['countries_data'], 'country')) ?>,
    [{ data: <?= json_encode(array_map('intval', array_column($stats['countries_data'], 'count'))) ?>, backgroundColor: ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16'] }]
  );
  <?php endif; ?>

  <?php if (!empty($stats['devices'])): ?>
  renderChart('chart-device', 'doughnut',
    <?= json_encode(array_column($stats['devices'], 'device_type')) ?>,
    [{ data: <?= json_encode(array_map('intval', array_column($stats['devices'], 'count'))) ?>, backgroundColor: ['#3b82f6','#10b981','#f59e0b'] }]
  );
  <?php endif; ?>

  <?php if (!empty($stats['browsers'])): ?>
  renderChart('chart-browser', 'bar',
    <?= json_encode(array_column($stats['browsers'], 'browser')) ?>,
    [{ label: 'Clicks', data: <?= json_encode(array_map('intval', array_column($stats['browsers'], 'count'))) ?>, backgroundColor: '#3b82f6' }],
    { plugins: { legend: { display: false } } }
  );
  <?php endif; ?>

  <?php if (!empty($stats['os'])): ?>
  renderChart('chart-os', 'bar',
    <?= json_encode(array_column($stats['os'], 'os')) ?>,
    [{ label: 'Clicks', data: <?= json_encode(array_map('intval', array_column($stats['os'], 'count'))) ?>, backgroundColor: '#10b981' }],
    { plugins: { legend: { display: false } } }
  );
  <?php endif; ?>

  <?php if (!empty($stats['referrers'])): ?>
  renderChart('chart-referrer', 'bar',
    <?= json_encode(array_column($stats['referrers'], 'referrer_group')) ?>,
    [{ label: 'Clicks', data: <?= json_encode(array_map('intval', array_column($stats['referrers'], 'count'))) ?>, backgroundColor: '#8b5cf6' }],
    { plugins: { legend: { display: false } } }
  );
  <?php endif; ?>

  startPolling(<?= $linkId ?>);
});

function startPolling(linkId) {
  var lastTime = '<?= !empty($stats['recent_clicks'][0]['clicked_at']) ? addslashes($stats['recent_clicks'][0]['clicked_at']) : '' ?>';
  setInterval(function() {
    var url = '/analytics/' + linkId + '/realtime';
    if (lastTime) url += '?since=' + encodeURIComponent(lastTime);
    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.data.length > 0) {
          var tbody = document.querySelector('#recent-clicks-table tbody');
          data.data.forEach(function(click) {
            if (click.clicked_at > lastTime) lastTime = click.clicked_at;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + escapeHtml(click.clicked_at) + '</td>' +
              '<td>' + escapeHtml(click.country || 'Unknown') + '</td>' +
              '<td>' + escapeHtml(click.city || 'Unknown') + '</td>' +
              '<td>' + escapeHtml(click.device_type || 'Unknown') + '</td>' +
              '<td>' + escapeHtml(click.browser || 'Unknown') + '</td>' +
              '<td>' + escapeHtml(click.os || 'Unknown') + '</td>' +
              '<td class="url-cell">' + escapeHtml(click.referrer || 'Direct') + '</td>';
            tbody.insertBefore(tr, tbody.firstChild);
          });
          document.getElementById('live-indicator').classList.add('pulse');
        }
      })
      .catch(function() {});
  }, 5000);
}

function escapeHtml(str) {
  var div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

function exportLinkAnalytics(format) {
  window.location.href = '/analytics/<?= $linkId ?>/export?format=' + format;
}
</script>
<?php $this->endSection(); ?>

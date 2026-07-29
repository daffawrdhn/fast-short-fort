<?php $this->extend('layouts.app'); ?>

<?php $this->section('content'); ?>
<div class="analytics-container">
  <div class="page-header">
    <div class="page-header-row" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
      <div>
        <a href="/analytics" class="back-link" style="font-size:0.875rem; color:var(--text-secondary); text-decoration:none; display:inline-flex; align-items:center; gap:0.25rem; margin-bottom:0.5rem;">&larr; Back to Analytics</a>
        <h1 class="page-title">Link Analytics</h1>
        <?php if ($stats['link']): ?>
        <p class="page-subtitle text-muted" style="margin-top:0.25rem;">
          <strong>/<?= htmlspecialchars($stats['link']['slug'], ENT_QUOTES, 'UTF-8') ?></strong>
          &rarr; <span style="word-break:break-all;"><?= htmlspecialchars($stats['link']['original_url'], ENT_QUOTES, 'UTF-8') ?></span>
        </p>
        <?php endif; ?>
      </div>
      <div class="page-actions" style="display:flex; gap:0.5rem;">
        <button class="btn btn-outline" onclick="exportLinkAnalytics('csv')">CSV</button>
        <button class="btn btn-outline" onclick="exportLinkAnalytics('json')">JSON</button>
      </div>
    </div>
  </div>

  <form method="GET" action="/analytics/<?= $linkId ?>" class="analytics-filters" style="display:flex; gap:1.5rem; align-items:flex-end; margin-bottom:2rem; flex-wrap:wrap;">
    <div class="filter-group" style="margin:0; display:flex; flex-direction:column; gap:0.5rem;">
      <label for="start_date" style="font-size:0.75rem; text-transform:uppercase; font-weight:600; color:var(--text-secondary);">From</label>
      <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>" class="form-control" style="padding:0.45rem 0.75rem; border-radius:var(--radius); border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary);">
    </div>
    <div class="filter-group" style="margin:0; display:flex; flex-direction:column; gap:0.5rem;">
      <label for="end_date" style="font-size:0.75rem; text-transform:uppercase; font-weight:600; color:var(--text-secondary);">To</label>
      <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>" class="form-control" style="padding:0.45rem 0.75rem; border-radius:var(--radius); border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary);">
    </div>
    <button type="submit" class="btn btn-primary" style="padding:0.525rem 1.25rem;">Apply</button>
  </form>

  <div class="bento-grid">
    <!-- Stat 1: Total Clicks -->
    <div class="bento-card bento-col-4">
      <div class="stat-label">Total Clicks</div>
      <div class="stat-value"><?= number_format($stats['total_clicks'] ?? 0) ?></div>
    </div>

    <!-- Stat 2: Unique Clicks -->
    <div class="bento-card bento-col-4">
      <div class="stat-label">Unique Clicks</div>
      <div class="stat-value"><?= number_format($stats['unique_clicks'] ?? 0) ?></div>
    </div>

    <!-- Stat 3: Countries -->
    <div class="bento-card bento-col-4">
      <div class="stat-label">Countries</div>
      <div class="stat-value"><?= number_format($stats['countries'] ?? 0) ?></div>
    </div>

    <!-- Clicks Over Time -->
    <div class="bento-card bento-col-12 bento-row-3" style="min-height:350px;">
      <h3 class="card-title" style="margin-bottom:1.5rem; font-size:1.05rem; font-weight:600;">Clicks Over Time</h3>
      <div style="flex-grow:1; position:relative; min-height:220px;">
        <canvas id="chart-timeseries"></canvas>
      </div>
    </div>

    <!-- By Country -->
    <div class="bento-card bento-col-4 bento-row-3" style="min-height:320px;">
      <h3 class="card-title" style="margin-bottom:1.5rem; font-size:1.05rem; font-weight:600;">By Country</h3>
      <div style="flex-grow:1; position:relative; min-height:200px;">
        <canvas id="chart-country"></canvas>
      </div>
    </div>

    <!-- By Device -->
    <div class="bento-card bento-col-4 bento-row-3" style="min-height:320px;">
      <h3 class="card-title" style="margin-bottom:1.5rem; font-size:1.05rem; font-weight:600;">By Device</h3>
      <div style="flex-grow:1; position:relative; min-height:200px;">
        <canvas id="chart-device"></canvas>
      </div>
    </div>

    <!-- By Browser -->
    <div class="bento-card bento-col-4 bento-row-3" style="min-height:320px;">
      <h3 class="card-title" style="margin-bottom:1.5rem; font-size:1.05rem; font-weight:600;">By Browser</h3>
      <div style="flex-grow:1; position:relative; min-height:200px;">
        <canvas id="chart-browser"></canvas>
      </div>
    </div>

    <!-- By OS -->
    <div class="bento-card bento-col-4 bento-row-3" style="min-height:320px;">
      <h3 class="card-title" style="margin-bottom:1.5rem; font-size:1.05rem; font-weight:600;">By Operating System</h3>
      <div style="flex-grow:1; position:relative; min-height:200px;">
        <canvas id="chart-os"></canvas>
      </div>
    </div>

    <!-- Top Referrers -->
    <div class="bento-card bento-col-4 bento-row-3" style="min-height:320px;">
      <h3 class="card-title" style="margin-bottom:1.5rem; font-size:1.05rem; font-weight:600;">Top Referrers</h3>
      <div style="flex-grow:1; position:relative; min-height:200px;">
        <canvas id="chart-referrer"></canvas>
      </div>
    </div>

    <!-- Top Languages -->
    <div class="bento-card bento-col-4 bento-row-3" style="min-height:320px;">
      <h3 class="card-title" style="margin-bottom:1.5rem; font-size:1.05rem; font-weight:600;">Top Languages</h3>
      <div style="flex-grow:1; position:relative; min-height:200px;">
        <canvas id="chart-language"></canvas>
      </div>
    </div>

    <!-- Map Visualization -->
    <div class="bento-card bento-col-12" style="min-height:400px; display:flex; flex-direction:column; justify-content:center;">
      <h3 class="card-title" style="margin-bottom:1.5rem; font-size:1.05rem; font-weight:600;">Click Locations</h3>
      <div id="geo-map" style="flex-grow:1; min-height:350px; border:1px solid var(--border-color); border-radius:var(--radius); overflow:hidden; z-index:1;">
      </div>
    </div>

    <!-- Recent Clicks Table -->
    <div class="bento-card bento-col-12">
      <div class="card-header" style="border:none; padding:0; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
        <h3 class="card-title" style="font-size:1.05rem; font-weight:600; margin:0;">Recent Clicks</h3>
        <span class="badge badge-success" id="live-indicator" style="animation: pulse 2s infinite;">Live</span>
      </div>
      <div class="card-body" style="padding:0;">
        <div class="table-responsive" style="border:none; background:transparent;">
          <table class="table" id="recent-clicks-table">
            <thead>
              <tr>
                <th style="padding-left:0;">Time</th>
                <th>Visitor ID</th>
                <th>IP Address</th>
                <th>Country</th>
                <th>City</th>
                <th>Lang</th>
                <th>ISP</th>
                <th>Conn</th>
                <th>VPN</th>
                <th>DNT</th>
                <th>Device</th>
                <th>Browser</th>
                <th>OS</th>
                <th>User Agent</th>
                <th>Referrer</th>
                <th style="padding-right:0;">Bot</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($stats['recent_clicks'])): ?>
              <?php foreach ($stats['recent_clicks'] as $click): ?>
              <tr>
                <td style="padding-left:0;"><?= htmlspecialchars($click['clicked_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td style="font-family:var(--font-mono); font-size:0.8rem;" title="<?= htmlspecialchars($click['visitor_uuid'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(substr($click['visitor_uuid'] ?? 'N/A', 0, 8), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="font-family:var(--font-mono); font-size:0.875rem;"><?= htmlspecialchars($click['ip_address'] ?? ($click['ip_hash'] ? substr($click['ip_hash'], 0, 8) . '...' : 'Unknown'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($click['country'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($click['city'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="badge badge-secondary" style="text-transform:uppercase;"><?= htmlspecialchars($click['user_language'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></span></td>
                <td><?= htmlspecialchars($click['isp'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($click['connection_type'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= ($click['is_vpn'] ?? 0) ? '<span class="badge" style="background-color:#ef4444; color:white;">VPN</span>' : '<span class="badge" style="background-color:#6b7280; color:white;">No</span>' ?></td>
                <td><?= ($click['dnt_status'] ?? 0) ? '<span class="badge" style="background-color:#f59e0b; color:white;">Active</span>' : '<span class="badge" style="background-color:#6b7280; color:white;">Off</span>' ?></td>
                <td><?= htmlspecialchars($click['device_type'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($click['browser'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($click['os'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="truncate-text" title="<?= htmlspecialchars($click['user_agent'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>" style="max-width:180px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($click['user_agent'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></span></td>
                <td><span class="truncate-text" title="<?= htmlspecialchars($click['referrer'] ?? 'Direct', ENT_QUOTES, 'UTF-8') ?>" style="max-width:150px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($click['referrer'] ?? 'Direct', ENT_QUOTES, 'UTF-8') ?></span></td>
                <td style="padding-right:0;"><?= $click['is_bot'] ? 'Yes' : 'No' ?></td>
              </tr>
              <?php endforeach; ?>
              <?php else: ?>
              <tr><td colspan="16" class="empty-state" style="text-align:center; padding:2rem 0;">No clicks recorded yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
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

  <?php if (!empty($stats['languages'])): ?>
  renderChart('chart-language', 'pie',
    <?= json_encode(array_column($stats['languages'], 'language')) ?>,
    [{ data: <?= json_encode(array_map('intval', array_column($stats['languages'], 'count'))) ?>, backgroundColor: ['#ec4899','#8b5cf6','#ef4444','#f59e0b','#10b981','#3b82f6','#06b6d4','#84cc16'] }]
  );
  <?php endif; ?>

  // Map initialization
  var mapEl = document.getElementById('geo-map');
  var map, markerGroup;
  if (mapEl) {
    map = L.map('geo-map').setView([20, 0], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    markerGroup = L.layerGroup().addTo(map);
  }

  function updateMapMarkers(clicks) {
    if (!map || !markerGroup) return;
    markerGroup.clearLayers();
    var coords = [];
    clicks.forEach(function(click) {
      if (click.latitude && click.longitude) {
        var lat = parseFloat(click.latitude);
        var lon = parseFloat(click.longitude);
        if (!isNaN(lat) && !isNaN(lon)) {
          var popupContent = '<b>' + escapeHtml(click.city || 'Unknown') + ', ' + escapeHtml(click.country || 'Unknown') + '</b><br>' +
                             'IP: ' + escapeHtml(click.ip_address || 'Unknown') + '<br>' +
                             'ISP: ' + escapeHtml(click.isp || 'Unknown') + '<br>' +
                             'Time: ' + escapeHtml(click.clicked_at);
          var marker = L.marker([lat, lon]).bindPopup(popupContent);
          markerGroup.addLayer(marker);
          coords.push([lat, lon]);
        }
      }
    });

    if (coords.length > 0) {
      map.fitBounds(coords, { maxZoom: 10, padding: [20, 20] });
    }
  }

  var initialClicks = <?= json_encode($stats['recent_clicks'] ?? []) ?>;
  updateMapMarkers(initialClicks);

  startPolling(<?= $linkId ?>, initialClicks);
});

function startPolling(linkId, allClicks) {
  var lastTime = <?= json_encode($stats['recent_clicks'][0]['clicked_at'] ?? '') ?>;
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
            allClicks.unshift(click);
            var tr = document.createElement('tr');
            tr.innerHTML = '<td style="padding-left:0;">' + escapeHtml(click.clicked_at) + '</td>' +
              '<td style="font-family:var(--font-mono); font-size:0.8rem;" title="' + escapeHtml(click.visitor_uuid || 'N/A') + '">' + escapeHtml((click.visitor_uuid || 'N/A').substring(0, 8)) + '</td>' +
              '<td style="font-family:var(--font-mono); font-size:0.875rem;">' + escapeHtml(click.ip_address || (click.ip_hash ? click.ip_hash.substring(0, 8) + '...' : 'Unknown')) + '</td>' +
              '<td>' + escapeHtml(click.country || 'Unknown') + '</td>' +
              '<td>' + escapeHtml(click.city || 'Unknown') + '</td>' +
              '<td><span class="badge badge-secondary" style="text-transform:uppercase;">' + escapeHtml(click.user_language || 'Unknown') + '</span></td>' +
              '<td>' + escapeHtml(click.isp || 'Unknown') + '</td>' +
              '<td>' + escapeHtml(click.connection_type || 'Unknown') + '</td>' +
              '<td>' + (click.is_vpn ? '<span class="badge" style="background-color:#ef4444; color:white;">VPN</span>' : '<span class="badge" style="background-color:#6b7280; color:white;">No</span>') + '</td>' +
              '<td>' + (click.dnt_status ? '<span class="badge" style="background-color:#f59e0b; color:white;">Active</span>' : '<span class="badge" style="background-color:#6b7280; color:white;">Off</span>') + '</td>' +
              '<td>' + escapeHtml(click.device_type || 'Unknown') + '</td>' +
              '<td>' + escapeHtml(click.browser || 'Unknown') + '</td>' +
              '<td>' + escapeHtml(click.os || 'Unknown') + '</td>' +
              '<td><span class="truncate-text" title="' + escapeHtml(click.user_agent || 'Unknown') + '" style="max-width:180px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + escapeHtml(click.user_agent || 'Unknown') + '</span></td>' +
              '<td><span class="truncate-text" title="' + escapeHtml(click.referrer || 'Direct') + '" style="max-width:150px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + escapeHtml(click.referrer || 'Direct') + '</span></td>' +
              '<td style="padding-right:0;">' + (click.is_bot ? 'Yes' : 'No') + '</td>';
            tbody.insertBefore(tr, tbody.firstChild);
          });
          updateMapMarkers(allClicks);
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

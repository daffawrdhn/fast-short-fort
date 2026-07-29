<?php $this->extend('layouts.app'); ?>

<?php $this->section('content'); ?>
<div class="analytics-container">
  <div class="page-header">
    <h1 class="page-title">Analytics</h1>
    <div class="page-actions">
      <button class="btn btn-outline" onclick="exportAnalytics('csv')" title="Export CSV">
        CSV
      </button>
      <button class="btn btn-outline" onclick="exportAnalytics('json')" title="Export JSON">
        JSON
      </button>
    </div>
  </div>

  <form method="GET" action="/analytics" class="analytics-filters" id="filter-form" style="display:flex; gap:1.5rem; align-items:flex-end; margin-bottom:2rem; flex-wrap:wrap;">
    <div class="filter-group" style="margin:0; display:flex; flex-direction:column; gap:0.5rem;">
      <label for="preset" style="font-size:0.75rem; text-transform:uppercase; font-weight:600; color:var(--text-secondary);">Period</label>
      <select id="preset" onchange="applyPreset(this.value)" class="form-control" style="padding:0.5rem 2rem 0.5rem 0.75rem; border-radius:var(--radius); border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary);">
        <option value="24h">Last 24 Hours</option>
        <option value="7d" <?= $startDate === date('Y-m-d', strtotime('-7 days')) ? 'selected' : '' ?>>Last 7 Days</option>
        <option value="30d" <?= $startDate === date('Y-m-d', strtotime('-30 days')) ? 'selected' : '' ?>>Last 30 Days</option>
        <option value="90d" <?= $startDate === date('Y-m-d', strtotime('-90 days')) ? 'selected' : '' ?>>Last 90 Days</option>
        <option value="custom" <?= !in_array($startDate, [date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('-90 days'))]) ? 'selected' : '' ?>>Custom</option>
      </select>
    </div>
    <div id="custom-dates" style="display:<?= !in_array($startDate, [date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('-90 days'))]) ? 'flex' : 'none' ?>; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
      <div class="filter-group" style="margin:0; display:flex; flex-direction:column; gap:0.5rem;">
        <label for="start_date" style="font-size:0.75rem; text-transform:uppercase; font-weight:600; color:var(--text-secondary);">From</label>
        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>" class="form-control" style="padding:0.45rem 0.75rem; border-radius:var(--radius); border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary);">
      </div>
      <div class="filter-group" style="margin:0; display:flex; flex-direction:column; gap:0.5rem;">
        <label for="end_date" style="font-size:0.75rem; text-transform:uppercase; font-weight:600; color:var(--text-secondary);">To</label>
        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>" class="form-control" style="padding:0.45rem 0.75rem; border-radius:var(--radius); border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary);">
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="padding:0.525rem 1.25rem;">Apply</button>
  </form>

  <div class="bento-grid">
    <!-- Stat 1: Total Clicks -->
    <div class="bento-card bento-col-3">
      <div class="stat-label">Total Clicks</div>
      <div class="stat-value"><?= number_format($stats['total_clicks'] ?? 0) ?></div>
    </div>

    <!-- Stat 2: Unique Clicks -->
    <div class="bento-card bento-col-3">
      <div class="stat-label">Unique Clicks</div>
      <div class="stat-value"><?= number_format($stats['unique_clicks'] ?? 0) ?></div>
    </div>

    <!-- Stat 3: Countries -->
    <div class="bento-card bento-col-3">
      <div class="stat-label">Countries</div>
      <div class="stat-value"><?= number_format(count($stats['countries_data'] ?? [])) ?></div>
    </div>

    <!-- Stat 4: Device Types -->
    <div class="bento-card bento-col-3">
      <div class="stat-label">Device Types</div>
      <div class="stat-value"><?= number_format(count($stats['devices'] ?? [])) ?></div>
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
    <div class="bento-card bento-col-6 bento-row-3" style="min-height:320px;">
      <h3 class="card-title" style="margin-bottom:1.5rem; font-size:1.05rem; font-weight:600;">By Operating System</h3>
      <div style="flex-grow:1; position:relative; min-height:200px;">
        <canvas id="chart-os"></canvas>
      </div>
    </div>

    <!-- Top Referrers -->
    <div class="bento-card bento-col-6 bento-row-3" style="min-height:320px;">
      <h3 class="card-title" style="margin-bottom:1.5rem; font-size:1.05rem; font-weight:600;">Top Referrers</h3>
      <div style="flex-grow:1; position:relative; min-height:200px;">
        <canvas id="chart-referrer"></canvas>
      </div>
    </div>

    <!-- Top Links -->
    <div class="bento-card bento-col-12">
      <h3 class="card-title" style="margin-bottom:1.5rem; font-size:1.05rem; font-weight:600;">Top Links</h3>
      <?php if (!empty($stats['top_links'])): ?>
      <div class="table-responsive" style="border:none; background:transparent;">
        <table class="table">
          <thead>
            <tr>
              <th style="padding-left:0;">Slug</th>
              <th>Original URL</th>
              <th>Clicks</th>
              <th style="padding-right:0;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($stats['top_links'] as $link): ?>
            <tr>
              <td style="padding-left:0;"><a href="/analytics/<?= (int) $link['id'] ?>" style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($link['slug'], ENT_QUOTES, 'UTF-8') ?></a></td>
              <td class="url-cell">
                <span class="truncate-text" title="<?= htmlspecialchars($link['original_url'], ENT_QUOTES, 'UTF-8') ?>" style="max-width:550px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($link['original_url'], ENT_QUOTES, 'UTF-8') ?></span>
              </td>
              <td><span style="font-weight:500;"><?= number_format((int) $link['clicks']) ?></span></td>
              <td style="padding-right:0;"><a href="/analytics/<?= (int) $link['id'] ?>" class="btn btn-sm btn-outline">View</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <p class="empty-state" style="text-align:center; margin:2rem 0;">No analytics data available yet. Create some links to get started.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var preset = document.getElementById('preset');
  if (preset) {
    var selected = preset.value;
    if (['24h','7d','30d','90d'].includes(selected)) {
      var n = selected === '24h' ? 1 : parseInt(selected);
      var u = selected === '24h' ? 'hours' : 'days';
    }
  }

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
});

function applyPreset(val) {
  var custom = document.getElementById('custom-dates');
  var sd = document.getElementById('start_date');
  var ed = document.getElementById('end_date');
  var today = new Date();
  var fmt = function(d) { return d.getFullYear()+'-'+(d.getMonth()+1).toString().padStart(2,'0')+'-'+d.getDate().toString().padStart(2,'0'); };
  if (val === '24h') {
    custom.style.display = 'none';
    sd.value = fmt(new Date(today.getTime() - 86400000));
    ed.value = fmt(today);
  } else if (val === '7d') {
    custom.style.display = 'none';
    var d = new Date(today); d.setDate(d.getDate()-7);
    sd.value = fmt(d); ed.value = fmt(today);
  } else if (val === '30d') {
    custom.style.display = 'none';
    var d = new Date(today); d.setDate(d.getDate()-30);
    sd.value = fmt(d); ed.value = fmt(today);
  } else if (val === '90d') {
    custom.style.display = 'none';
    var d = new Date(today); d.setDate(d.getDate()-90);
    sd.value = fmt(d); ed.value = fmt(today);
  } else {
    custom.style.display = 'flex';
  }
}

function exportAnalytics(format) {
  var params = new URLSearchParams(window.location.search);
  params.set('export', format);
  window.location.href = '/analytics/export?' + params.toString();
}
</script>
<?php $this->endSection(); ?>

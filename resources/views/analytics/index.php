<?php $this->extend('layouts.app'); ?>

<?php $this->section('content'); ?>
<div class="analytics-container">
  <div class="page-header">
    <h1 class="page-title">Analytics</h1>
    <div class="page-actions">
      <button class="btn btn-outline" onclick="exportAnalytics('csv')" title="Export CSV">
        <span class="icon-download"></span> CSV
      </button>
      <button class="btn btn-outline" onclick="exportAnalytics('json')" title="Export JSON">
        <span class="icon-download"></span> JSON
      </button>
    </div>
  </div>

  <form method="GET" action="/analytics" class="analytics-filters" id="filter-form">
    <div class="filter-group">
      <label for="preset">Period</label>
      <select id="preset" onchange="applyPreset(this.value)">
        <option value="24h">Last 24 Hours</option>
        <option value="7d" <?= $startDate === date('Y-m-d', strtotime('-7 days')) ? 'selected' : '' ?>>Last 7 Days</option>
        <option value="30d" <?= $startDate === date('Y-m-d', strtotime('-30 days')) ? 'selected' : '' ?>>Last 30 Days</option>
        <option value="90d" <?= $startDate === date('Y-m-d', strtotime('-90 days')) ? 'selected' : '' ?>>Last 90 Days</option>
        <option value="custom" <?= !in_array($startDate, [date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('-90 days'))]) ? 'selected' : '' ?>>Custom</option>
      </select>
    </div>
    <div class="filter-group" id="custom-dates" style="display:<?= !in_array($startDate, [date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('-90 days'))]) ? 'flex' : 'none' ?>">
      <label for="start_date">From</label>
      <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>">
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
      <div class="stat-value"><?= number_format(count($stats['countries_data'] ?? [])) ?></div>
      <div class="stat-label">Countries</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= number_format(count($stats['devices'] ?? [])) ?></div>
      <div class="stat-label">Device Types</div>
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

  <div class="table-card">
    <h3 class="table-title">Top Links</h3>
    <?php if (!empty($stats['top_links'])): ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Slug</th>
            <th>Original URL</th>
            <th>Clicks</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($stats['top_links'] as $link): ?>
          <tr>
            <td><a href="/analytics/<?= (int) $link['id'] ?>"><?= htmlspecialchars($link['slug'], ENT_QUOTES, 'UTF-8') ?></a></td>
            <td class="url-cell"><?= htmlspecialchars($link['original_url'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((int) $link['clicks']) ?></td>
            <td><a href="/analytics/<?= (int) $link['id'] ?>" class="btn btn-sm btn-outline">View</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="empty-state">No analytics data available yet. Create some links to get started.</p>
    <?php endif; ?>
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

(function () {
  'use strict';

  window.AnalyticsChart = {
    charts: {},

    init: function () {
      this.loadChartJS();
    },

    loadChartJS: function () {
      if (typeof Chart !== 'undefined') {
        this.onReady();
        return;
      }
      var script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
      script.integrity = 'sha256-Z6Sg8C/Yw3SEsIBasoyqCXz7L6bMriSj08B1RQLA9hc=';
      script.crossOrigin = 'anonymous';
      var self = this;
      script.onload = function () { self.onReady(); };
      script.onerror = function () { console.error('Failed to load Chart.js'); };
      document.head.appendChild(script);
    },

    onReady: function () {
      this.renderTimeSeries();
      this.renderBreakdownCharts();
    },

    renderTimeSeries: function () {
      var canvas = document.getElementById('time-series-chart');
      if (!canvas) return;

      var labels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
      var values = JSON.parse(canvas.getAttribute('data-values') || '[]');
      var label = canvas.getAttribute('data-label') || 'Clicks';

      var ctx = canvas.getContext('2d');
      this.charts.timeSeries = new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: label,
            data: values,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
            pointHoverRadius: 6,
            borderWidth: 2
          }]
        },
        options: this.getTimeSeriesOptions()
      });
    },

    getTimeSeriesOptions: function () {
      var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      var gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)';
      var textColor = isDark ? '#94a3b8' : '#6b7280';

      return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: isDark ? '#1e293b' : '#ffffff',
            titleColor: isDark ? '#f1f5f9' : '#111827',
            bodyColor: isDark ? '#94a3b8' : '#6b7280',
            borderColor: gridColor,
            borderWidth: 1,
            padding: 12,
            boxPadding: 6
          }
        },
        scales: {
          x: {
            grid: { color: gridColor },
            ticks: { color: textColor, maxTicksLimit: 12 }
          },
          y: {
            grid: { color: gridColor },
            ticks: { color: textColor, beginAtZero: true },
            beginAtZero: true
          }
        }
      };
    },

    renderBreakdownCharts: function () {
      var containers = document.querySelectorAll('[data-breakdown-chart]');
      var self = this;
      containers.forEach(function (el) {
        var type = el.getAttribute('data-breakdown-chart');
        var labels = JSON.parse(el.getAttribute('data-labels') || '[]');
        var values = JSON.parse(el.getAttribute('data-values') || '[]');
        var canvas = el.querySelector('canvas');
        if (!canvas) return;

        var ctx = canvas.getContext('2d');
        var id = 'breakdown-' + type;
        if (self.charts[id]) self.charts[id].destroy();

        self.charts[id] = new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: labels,
            datasets: [{
              data: values,
              backgroundColor: [
                '#3b82f6', '#22c55e', '#f59e0b', '#ef4444',
                '#8b5cf6', '#ec4899', '#06b6d4', '#f97316',
                '#6366f1', '#14b8a6'
              ],
              borderWidth: 2,
              borderColor: isDark() ? '#0f172a' : '#ffffff'
            }]
          },
          options: self.getDoughnutOptions(type)
        });
      });
    },

    getDoughnutOptions: function (type) {
      return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 16,
              usePointStyle: true,
              color: isDark() ? '#94a3b8' : '#6b7280'
            }
          },
          title: {
            display: true,
            text: type.charAt(0).toUpperCase() + type.slice(1),
            color: isDark() ? '#f1f5f9' : '#111827',
            font: { size: 14, weight: '600' },
            padding: { bottom: 12 }
          }
        }
      };
    },

    updateTimeSeries: function (labels, values) {
      if (this.charts.timeSeries) {
        this.charts.timeSeries.data.labels = labels;
        this.charts.timeSeries.data.datasets[0].data = values;
        this.charts.timeSeries.update();
      }
    },

    destroy: function () {
      Object.keys(this.charts).forEach(function (key) {
        if (this.charts[key]) this.charts[key].destroy();
      }.bind(this));
      this.charts = {};
    }
  };

  function isDark () {
    return document.documentElement.getAttribute('data-theme') === 'dark';
  }

  function observeTheme () {
    var observer = new MutationObserver(function () {
      var isDarkMode = isDark();
      var gridColor = isDarkMode ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)';
      var textColor = isDarkMode ? '#94a3b8' : '#6b7280';

      Object.keys(AnalyticsChart.charts).forEach(function (key) {
        var chart = AnalyticsChart.charts[key];
        if (!chart) return;
        if (chart.config.type === 'line') {
          chart.options.scales.x.grid.color = gridColor;
          chart.options.scales.y.grid.color = gridColor;
          chart.options.scales.x.ticks.color = textColor;
          chart.options.scales.y.ticks.color = textColor;
        }
        chart.update();
      });
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
  }

  if (document.readyState !== 'loading') {
    AnalyticsChart.init();
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      AnalyticsChart.init();
    });
  }

  observeTheme();
})();

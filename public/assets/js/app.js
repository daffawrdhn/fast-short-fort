(function () {
  'use strict';

  function getCSRFToken () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function ready (fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  function darkMode () {
    var stored = localStorage.getItem('theme');
    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    var theme = stored || (prefersDark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);

    var toggles = document.querySelectorAll('.theme-toggle');
    toggles.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var current = document.documentElement.getAttribute('data-theme');
        var next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
      });
    });
  }

  function mobileNav () {
    var toggle = document.querySelector('.navbar-toggle');
    var navbar = document.querySelector('.navbar');
    if (!toggle || !navbar) return;

    toggle.addEventListener('click', function () {
      var isOpen = navbar.classList.toggle('open');
      toggle.setAttribute('aria-expanded', isOpen);
    });

    document.addEventListener('click', function (e) {
      if (!navbar.contains(e.target) && navbar.classList.contains('open')) {
        navbar.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function userDropdown () {
    var container = document.querySelector('[data-dropdown]');
    if (!container) return;
    var trigger = container.querySelector('.dropdown-trigger');
    var menu = container.querySelector('.dropdown-menu');

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = menu.classList.toggle('open');
      trigger.setAttribute('aria-expanded', isOpen);
    });

    document.addEventListener('click', function () {
      menu.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
    });
  }

  function flashDismiss () {
    var alerts = document.querySelectorAll('.alert-dismiss');
    alerts.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var alert = this.closest('.alert');
        if (alert) alert.remove();
      });
    });

    var flashAlerts = document.querySelectorAll('.flash-container .alert');
    flashAlerts.forEach(function (alert) {
      setTimeout(function () {
        if (alert.parentNode) {
          alert.style.transition = 'opacity 0.3s ease';
          alert.style.opacity = '0';
          setTimeout(function () { alert.remove(); }, 300);
        }
      }, 5000);
    });
  }

  window.copyToClipboard = function (text) {
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).catch(function () {
        fallbackCopy(text);
      });
    } else {
      fallbackCopy(text);
    }
  };

  function fallbackCopy (text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
  }

  window.copyToClipboardWithFeedback = function (text, btn) {
    copyToClipboard(text);
    if (!btn) return;
    var orig = btn.textContent;
    btn.textContent = 'Copied!';
    btn.disabled = true;
    setTimeout(function () {
      btn.textContent = orig;
      btn.disabled = false;
    }, 2000);
  };

  function formValidation () {
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        var valid = true;
        form.querySelectorAll('[required]').forEach(function (field) {
          var errorEl = field.closest('.form-group').querySelector('.form-error');
          if (!field.value.trim()) {
            field.classList.add('error');
            if (errorEl) errorEl.style.display = 'block';
            valid = false;
          } else {
            field.classList.remove('error');
            if (errorEl) errorEl.style.display = 'none';
          }
        });
        if (!valid) e.preventDefault();
      });
    });
  }

  window.fetchJSON = function (url, options) {
    options = options || {};
    options.headers = options.headers || {};
    options.headers['Accept'] = 'application/json';
    options.headers['Content-Type'] = options.headers['Content-Type'] || 'application/json';
    options.headers['X-CSRF-Token'] = getCSRFToken();

    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      options.body = JSON.stringify(options.body);
    }

    return fetch(url, options).then(function (res) {
      if (!res.ok) {
        return res.json().then(function (data) {
          var err = new Error(data.error || data.message || 'Request failed');
          err.status = res.status;
          err.data = data;
          throw err;
        }, function () {
          var err = new Error('Request failed with status ' + res.status);
          err.status = res.status;
          throw err;
        });
      }
      return res.json();
    });
  };

  window.postJSON = function (url, data) {
    return fetchJSON(url, { method: 'POST', body: data });
  };

  window.putJSON = function (url, data) {
    return fetchJSON(url, { method: 'PUT', body: data });
  };

  window.deleteJSON = function (url) {
    return fetchJSON(url, { method: 'DELETE' });
  };

  function qrModal () {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-qr-modal]');
      if (!btn) return;
      var modalId = btn.getAttribute('data-qr-modal');
      var modal = document.getElementById(modalId);
      if (modal) modal.classList.add('open');
    });

    document.addEventListener('click', function (e) {
      var overlay = e.target.closest('.modal-overlay');
      if (overlay && e.target === overlay) {
        overlay.classList.remove('open');
      }
    });

    document.querySelectorAll('.modal-close').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var modal = this.closest('.modal-overlay');
        if (modal) modal.classList.remove('open');
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(function (m) {
          m.classList.remove('open');
        });
      }
    });
  }

  function bulkActions () {
    var selectAll = document.querySelector('[data-select-all]');
    if (!selectAll) return;
    selectAll.addEventListener('change', function () {
      var checked = this.checked;
      document.querySelectorAll('[data-select-item]').forEach(function (cb) {
        cb.checked = checked;
      });
    });

    document.addEventListener('change', function () {
      var items = document.querySelectorAll('[data-select-item]');
      var checked = document.querySelectorAll('[data-select-item]:checked');
      var selectAll = document.querySelector('[data-select-all]');
      if (selectAll && items.length) {
        selectAll.checked = checked.length === items.length;
        selectAll.indeterminate = checked.length > 0 && checked.length < items.length;
      }
    });
  }

  function debounce (fn, delay) {
    var timer;
    return function () {
      var ctx = this, args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
    };
  }

  function searchInput () {
    document.querySelectorAll('[data-search]').forEach(function (input) {
      var handler = debounce(function () {
        var query = input.value.trim();
        var container = document.querySelector(input.getAttribute('data-search-target') || '[data-search-results]');
        if (!container) return;
        var rows = container.querySelectorAll('[data-search-row]');
        rows.forEach(function (row) {
          var text = (row.textContent || '').toLowerCase();
          row.style.display = query === '' || text.indexOf(query.toLowerCase()) !== -1 ? '' : 'none';
        });
        var event = new CustomEvent('search', { detail: { query: query } });
        container.dispatchEvent(event);
      }, 300);
      input.addEventListener('input', handler);
    });
  }

  function sortableTables () {
    document.querySelectorAll('th.sortable').forEach(function (th) {
      th.addEventListener('click', function () {
        var table = this.closest('table');
        var tbody = table.querySelector('tbody');
        var index = Array.prototype.indexOf.call(this.parentNode.children, this);
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        var isAsc = this.classList.contains('asc');

        table.querySelectorAll('th.sortable').forEach(function (h) {
          h.classList.remove('asc', 'desc');
        });

        this.classList.add(isAsc ? 'desc' : 'asc');

        rows.sort(function (a, b) {
          var aText = (a.children[index] ? a.children[index].textContent.trim() : '');
          var bText = (b.children[index] ? b.children[index].textContent.trim() : '');
          var aVal = isNaN(Number(aText)) ? aText.toLowerCase() : Number(aText);
          var bVal = isNaN(Number(bText)) ? bText.toLowerCase() : Number(bText);
          if (aVal < bVal) return isAsc ? 1 : -1;
          if (aVal > bVal) return isAsc ? -1 : 1;
          return 0;
        });

        rows.forEach(function (row) { tbody.appendChild(row); });
      });
    });
  }

  function buttonLoading () {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-loading]');
      if (!btn) return;
      btn.classList.add('btn-loading');
      btn.disabled = true;
    });
  }

  window.slugCheck = function (input, url, callback) {
    var val = input.value.trim();
    if (!val) return;
    fetchJSON(url.replace('{slug}', encodeURIComponent(val)))
      .then(function (data) { if (callback) callback(data.available, data); })
      .catch(function () { if (callback) callback(false); });
  };

  function initClipboard() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.btn-copy') || e.target.closest('[data-clipboard-text]');
      if (!btn) return;
      var text = btn.getAttribute('data-clipboard-text');
      if (text) {
        copyToClipboard(text);
        var origClass = btn.className;
        btn.classList.add('copied');
        var origTitle = btn.getAttribute('title') || 'Copy short URL';
        btn.setAttribute('title', 'Copied!');
        setTimeout(function () {
          btn.classList.remove('copied');
          btn.setAttribute('title', origTitle);
        }, 1500);
      }
    });
  }

  function adminSidebarToggle () {
    var toggle = document.querySelector('.sidebar-toggle');
    var sidebar = document.querySelector('.admin-sidebar');
    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', function () {
      var isOpen = sidebar.classList.toggle('open');
      toggle.setAttribute('aria-expanded', isOpen);
    });

    document.addEventListener('click', function (e) {
      if (!sidebar.contains(e.target) && sidebar.classList.contains('open') && !toggle.contains(e.target)) {
        sidebar.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  ready(darkMode);
  ready(mobileNav);
  ready(adminSidebarToggle);
  ready(userDropdown);
  ready(flashDismiss);
  ready(formValidation);
  ready(qrModal);
  ready(bulkActions);
  ready(searchInput);
  ready(sortableTables);
  ready(buttonLoading);
  ready(initClipboard);
})();


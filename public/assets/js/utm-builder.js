(function () {
  'use strict';

  function UTMBuilder () {
    this.form = document.getElementById('utm-builder');
    if (!this.form) return;
    this.bind();
  }

  UTMBuilder.prototype.bind = function () {
    var inputs = this.form.querySelectorAll('input, select');
    var self = this;

    inputs.forEach(function (input) {
      input.addEventListener('input', function () { self.updatePreview(); });
      input.addEventListener('change', function () { self.updatePreview(); });
    });

    this.updatePreview();

    var copyBtn = this.form.querySelector('[data-utm-copy]');
    if (copyBtn) {
      copyBtn.addEventListener('click', function () {
        var preview = document.getElementById('utm-preview-url');
        if (preview) {
          window.copyToClipboardWithFeedback(preview.textContent, this);
        }
      });
    }

    var baseInput = this.form.querySelector('[name="base_url"]');
    if (baseInput) {
      baseInput.addEventListener('blur', function () {
        var val = this.value.trim();
        if (val && !val.startsWith('http://') && !val.startsWith('https://')) {
          this.value = 'https://' + val;
          self.updatePreview();
        }
      });
    }
  };

  UTMBuilder.prototype.getParams = function () {
    var params = {};
    var fields = { source: 'utm_source', medium: 'utm_medium', campaign: 'utm_campaign', term: 'utm_term', content: 'utm_content' };

    Object.keys(fields).forEach(function (key) {
      var input = this.form.querySelector('[name="' + key + '"]');
      if (input && input.value.trim()) {
        params[fields[key]] = input.value.trim();
      }
    }, this);

    return params;
  };

  UTMBuilder.prototype.updatePreview = function () {
    var baseInput = this.form.querySelector('[name="base_url"]');
    var preview = document.getElementById('utm-preview-url');
    if (!baseInput || !preview) return;

    var base = baseInput.value.trim();
    if (!base) {
      preview.textContent = '';
      return;
    }

    if (!base.startsWith('http://') && !base.startsWith('https://')) {
      base = 'https://' + base;
    }

    var params = this.getParams();
    var keys = Object.keys(params);

    if (keys.length === 0) {
      preview.textContent = base;
      return;
    }

    var url = new URL(base);
    keys.forEach(function (key) {
      url.searchParams.set(key, params[key]);
    });

    preview.textContent = url.toString();
  };

  ready(function () {
    new UTMBuilder();
  });

  function ready (fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }
})();

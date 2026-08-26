/**
 * Sales rep portal PWA — install bar + SW (scope /sale only).
 * Shows on login and all /sale pages (not only after auth).
 */
(function () {
  'use strict';
  var cfg = window.__SALE_PWA__ || {};
  var KEY = 'sale_pwa_installed';
  var DISMISS_KEY = 'sale_pwa_dismissed_at';
  var DISMISS_MS = 7 * 24 * 60 * 60 * 1000; // 7 days
  var deferred = null;

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
      || window.matchMedia('(display-mode: minimal-ui)').matches
      || window.navigator.standalone === true;
  }
  function installed() {
    try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; }
  }
  function dismissedRecently() {
    try {
      var t = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
      return t > 0 && (Date.now() - t) < DISMISS_MS;
    } catch (e) { return false; }
  }
  function mark() {
    try { localStorage.setItem(KEY, '1'); } catch (e) {}
  }
  function markDismiss() {
    try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch (e) {}
  }
  function setBar(show) {
    var bar = document.getElementById('sale_pwa_install_bar');
    if (!bar) return;
    if (show && !isStandalone() && !installed()) {
      bar.hidden = false;
    } else {
      bar.hidden = true;
    }
  }

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(cfg.swUrl || '/sale/pwa/sw.js', { scope: '/sale/' }).catch(function () {});
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    if (!dismissedRecently()) setBar(true);
  });
  window.addEventListener('appinstalled', function () {
    mark();
    setBar(false);
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest('#sale_pwa_dismiss_btn')) {
      markDismiss();
      setBar(false);
      return;
    }
    var btn = e.target.closest('#sale_pwa_install_btn');
    if (!btn) return;
    e.preventDefault();
    if (deferred) {
      deferred.prompt();
      deferred.userChoice.then(function (c) {
        deferred = null;
        if (c && c.outcome === 'accepted') { mark(); setBar(false); }
      });
      return;
    }
    if (/iphone|ipad|ipod/i.test(navigator.userAgent)) {
      alert('On iPhone: Share → Add to Home Screen');
    } else {
      alert('Use browser menu → Install app / Add to Home Screen');
    }
  });

  function boot() {
    if (isStandalone() || installed()) {
      mark();
      document.body && document.body.classList.add('sale-pwa-standalone');
      setBar(false);
      return;
    }
    // Show install bar on login + all pages (Chrome waits for beforeinstallprompt;
    // still show UI so user can install / get instructions).
    if (!dismissedRecently()) {
      setBar(true);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();

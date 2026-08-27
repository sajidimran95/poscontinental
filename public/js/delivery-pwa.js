(function () {
  'use strict';
  var cfg = window.__DLV_PWA__ || {};
  var KEY = 'dlv_pwa_installed';
  var DISMISS_KEY = 'dlv_pwa_dismissed_session';
  var deferred = null;

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
      || window.matchMedia('(display-mode: minimal-ui)').matches
      || window.navigator.standalone === true;
  }
  function installed() {
    try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; }
  }
  function dismissedThisSession() {
    try { return sessionStorage.getItem(DISMISS_KEY) === '1'; } catch (e) { return false; }
  }
  function mark() {
    try { localStorage.setItem(KEY, '1'); } catch (e) {}
  }
  function markDismiss() {
    try { sessionStorage.setItem(DISMISS_KEY, '1'); } catch (e) {}
  }
  function setBar(show) {
    var bar = document.getElementById('dlv_pwa_install_bar');
    if (!bar) return;
    var visible = !!show && !isStandalone() && !installed() && !dismissedThisSession();
    bar.hidden = !visible;
    if (!visible) bar.setAttribute('hidden', 'hidden');
    else bar.removeAttribute('hidden');
  }

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(cfg.swUrl || '/delivery/pwa/sw.js', { scope: '/delivery/' }).catch(function () {});
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    setBar(true);
  });
  window.addEventListener('appinstalled', function () {
    mark();
    setBar(false);
  });

  function bindBar() {
    var dismiss = document.getElementById('dlv_pwa_dismiss_btn');
    var install = document.getElementById('dlv_pwa_install_btn');
    if (dismiss) {
      dismiss.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        markDismiss();
        setBar(false);
      });
    }
    if (install) {
      install.addEventListener('click', function (e) {
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
          alert('On iPhone: tap Share, then Add to Home Screen.');
        } else {
          alert('Use the browser menu → Install app / Add to Home Screen.');
        }
      });
    }
    setBar(true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindBar);
  } else {
    bindBar();
  }

  if (isStandalone()) mark();
})();

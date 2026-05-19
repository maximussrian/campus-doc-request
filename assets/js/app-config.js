/**
 * API base URL — XAMPP (/docu_request), Hostinger root, or subfolder.
 * Optional: <meta name="app-base" content="/docu_request"> on server .env subfolder deploy.
 */
(function (global) {
  'use strict';
  var meta = document.querySelector('meta[name="app-base"]');
  var configured = meta ? meta.getAttribute('content') : null;
  var autoBase = /^\/docu_request(\/|$)/.test(global.location.pathname) ? '/docu_request' : '';
  var base = configured !== null ? String(configured).replace(/\/$/, '') : autoBase;
  global.APP_BASE = base;
  global.API = global.location.origin + base + '/api';
})(window);

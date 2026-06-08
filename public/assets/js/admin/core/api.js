/**
 * AdminApi — Centralized AJAX wrapper for Cape Tennis admin.
 *
 * Usage:
 *   AdminApi.request({ url, method, data }).then(res => ...).catch(err => ...)
 *   AdminApi.get(url).then(...)
 *   AdminApi.post(url, data).then(...)
 *   AdminApi.delete(url, data).then(...)
 */

(function (root) {
  'use strict';

  var DEFAULT_TIMEOUT = 20000; // 20 s
  var DEFAULT_RETRIES = 1;

  function csrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  /**
   * Core request.
   *
   * @param {object} opts
   * @param {string}  opts.url
   * @param {string}  [opts.method='GET']
   * @param {object}  [opts.data]
   * @param {boolean} [opts.json=false]   – send as JSON body
   * @param {number}  [opts.timeout]
   * @param {number}  [opts.retries]
   * @returns {Promise<object>}
   */
  function request(opts) {
    opts = opts || {};
    var method   = (opts.method || 'GET').toUpperCase();
    var url      = opts.url;
    var data     = opts.data || {};
    var asJson   = opts.json || false;
    var timeout  = opts.timeout || DEFAULT_TIMEOUT;
    var retries  = opts.retries != null ? opts.retries : DEFAULT_RETRIES;

    function attempt(attemptsLeft) {
      return new Promise(function (resolve, reject) {
        var ajaxOpts = {
          url:     url,
          method:  method,
          timeout: timeout,
          headers: {
            'X-CSRF-TOKEN': csrf(),
            'Accept':       'application/json'
          },
          success: function (res) { resolve(res); },
          error:   function (xhr, status) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message)
              ? xhr.responseJSON.message
              : 'Request failed (' + (xhr.status || status) + ')';

            // Retry on network errors or 5xx
            if (attemptsLeft > 0 && (status === 'timeout' || (xhr.status >= 500 && xhr.status < 600))) {
              attempt(attemptsLeft - 1).then(resolve).catch(reject);
              return;
            }

            reject({ status: xhr.status, message: msg, xhr: xhr, body: xhr.responseJSON || {} });
          }
        };

        if (asJson) {
          ajaxOpts.contentType = 'application/json';
          ajaxOpts.data = JSON.stringify(data);
        } else {
          ajaxOpts.data = data;
        }

        $.ajax(ajaxOpts);
      });
    }

    return attempt(retries);
  }

  function get(url, data) {
    return request({ url: url, method: 'GET', data: data });
  }

  function post(url, data) {
    return request({ url: url, method: 'POST', data: data });
  }

  function postJson(url, data) {
    return request({ url: url, method: 'POST', data: data, json: true });
  }

  function del(url, data) {
    return request({ url: url, method: 'DELETE', data: data });
  }

  root.AdminApi = {
    request:  request,
    get:      get,
    post:     post,
    postJson: postJson,
    delete:   del
  };

}(window));

/* =====================================================================
   assets/js/otp-resend.js — Resend-OTP countdown + AJAX call for the
   AgriCart registration OTP step.

   IMPORTANT: this file is a UI convenience only. The real cooldown,
   resend-count limit, and rate limiting are enforced server-side in
   pages/register.php (action=resend_otp) — this countdown never
   grants a resend by itself; the fetch() below still hits the server,
   which can reject the request even if the button looks enabled.

   Usage: call AgriOtpResend.init({...}) after DOMContentLoaded, e.g.
     AgriOtpResend.init({
       buttonId: 'resendBtn2',
       timerId: 'resendTimer2',
       alertId: 'otpAlert',
       csrfSelector: '#step2 input[name="csrf_token"]',
       cooldownSeconds: 30,
       postUrl: window.location.href,
       extraFields: { lang: 'en' },
     });
   ===================================================================== */
(function (global) {
  'use strict';

  function init(opts) {
    var cfg = Object.assign({
      buttonId: 'resendBtn2',
      timerId: 'resendTimer2',
      alertId: 'otpAlert',
      csrfSelector: 'input[name="csrf_token"]',
      cooldownSeconds: 30,
      postUrl: global.location.href,
      extraFields: {},
    }, opts || {});

    var btn = document.getElementById(cfg.buttonId);
    var tmr = document.getElementById(cfg.timerId);
    if (!btn) return;

    var countdownTimer = null;

    function startCountdown(seconds) {
      var sec = seconds;
      btn.disabled = true;
      if (tmr) tmr.textContent = sec;
      if (countdownTimer) clearInterval(countdownTimer);
      countdownTimer = setInterval(function () {
        sec--;
        if (tmr) tmr.textContent = Math.max(sec, 0);
        if (sec <= 0) {
          clearInterval(countdownTimer);
          btn.disabled = false;
        }
      }, 1000);
    }

    function showMessage(text) {
      var box = document.getElementById(cfg.alertId);
      if (box) {
        box.textContent = text;
        box.style.display = 'flex';
      }
    }

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      if (btn.disabled) return;
      btn.disabled = true;

      var csrfInput = document.querySelector(cfg.csrfSelector);
      var fd = new FormData();
      fd.append('action', 'resend_otp');
      fd.append('csrf_token', csrfInput ? csrfInput.value : '');
      Object.keys(cfg.extraFields).forEach(function (k) {
        fd.append(k, cfg.extraFields[k]);
      });

      fetch(cfg.postUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.message) showMessage(data.message);
          if (data.success) {
            startCountdown(cfg.cooldownSeconds);
          } else {
            btn.disabled = data.disable_button ? true : false;
            if (data.wait) startCountdown(data.wait);
          }
        })
        .catch(function () {
          btn.disabled = false;
        });
    });

    startCountdown(cfg.cooldownSeconds);
  }

  global.AgriOtpResend = { init: init };
})(window);

/**
 * AgriCart Authentication System
 * Multi-step registration wizard + Login modal
 * Pure Vanilla JS — No dependencies
 */

(function () {
  'use strict';

  /* ============================================
     STATE
     ============================================ */
  const state = {
    loginMethod: 'mobile',    // mobile | email | otp
    regStep: 1,
    totalRegSteps: 10,
    formData: {}
  };

  /* ============================================
     DOM SELECTORS
     ============================================ */
  const overlay    = document.getElementById('authOverlay');
  const modal      = document.getElementById('authModal');
  const closeBtn   = document.getElementById('authClose');
  const tabs       = document.querySelectorAll('.auth-tab');
  const panels     = document.querySelectorAll('.auth-panel');

  /* ============================================
     OPEN / CLOSE
     ============================================ */
  function openAuth(tab = 'login') {
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    switchTab(tab);
  }

  function closeAuth() {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  // Expose globally so header button can call it
  window.openAuthModal  = openAuth;
  window.closeAuthModal = closeAuth;

  // All elements with data-auth-open trigger
  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-auth-open]');
    if (trigger) {
      e.preventDefault();
      openAuth(trigger.dataset.authOpen || 'login');
    }
  });

  if (closeBtn) closeBtn.addEventListener('click', closeAuth);

  overlay && overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closeAuth();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAuth();
  });

  /* ============================================
     TAB SWITCHING
     ============================================ */
  function switchTab(name) {
    tabs.forEach(t => {
      t.classList.toggle('active', t.dataset.tab === name);
    });
    panels.forEach(p => {
      p.classList.toggle('active', p.dataset.panel === name);
    });
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', () => switchTab(tab.dataset.tab));
  });

  /* ============================================
     LOGIN METHOD TABS
     ============================================ */
  const loginMethodBtns = document.querySelectorAll('.login-method-btn');
  const loginPanels     = document.querySelectorAll('.login-method-panel');

  loginMethodBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const method = btn.dataset.method;
      loginMethodBtns.forEach(b => b.classList.toggle('active', b.dataset.method === method));
      loginPanels.forEach(p => p.classList.toggle('active', p.dataset.methodPanel === method));
      state.loginMethod = method;
    });
  });

  /* ============================================
     PASSWORD STRENGTH METER
     ============================================ */
  function measureStrength(pwd) {
    if (!pwd) return { score: 0, label: '', color: '' };
    let score = 0;
    if (pwd.length >= 8)  score++;
    if (pwd.length >= 12) score++;
    if (/[A-Z]/.test(pwd)) score++;
    if (/[0-9]/.test(pwd)) score++;
    if (/[^A-Za-z0-9]/.test(pwd)) score++;
    const levels = [
      { label: '',          color: '' },
      { label: 'Very Weak', color: '#ef4444' },
      { label: 'Weak',      color: '#f97316' },
      { label: 'Fair',      color: '#eab308' },
      { label: 'Strong',    color: '#22c55e' },
      { label: 'Very Strong', color: '#16a34a' }
    ];
    return { score, ...levels[score] };
  }

  document.querySelectorAll('.pwd-field').forEach(input => {
    const bar   = input.closest('.form-group').querySelector('.pwd-strength-fill');
    const label = input.closest('.form-group').querySelector('.pwd-strength-label');
    if (!bar) return;
    input.addEventListener('input', () => {
      const { score, color, label: lbl } = measureStrength(input.value);
      bar.style.width  = (score / 5 * 100) + '%';
      bar.style.background = color;
      if (label) label.textContent = lbl;
      label && (label.style.color = color);
    });
  });

  /* ============================================
     SHOW / HIDE PASSWORD TOGGLE
     ============================================ */
  document.querySelectorAll('.pwd-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.previousElementSibling;
      if (!input) return;
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      btn.textContent = isHidden ? '🙈' : '👁';
    });
  });

  /* ============================================
     OTP INPUT – auto-advance
     ============================================ */
  document.querySelectorAll('.otp-row').forEach(row => {
    const inputs = row.querySelectorAll('.otp-input');
    inputs.forEach((input, i) => {
      input.addEventListener('input', () => {
        input.value = input.value.slice(-1);
        if (input.value && i < inputs.length - 1) inputs[i + 1].focus();
      });
      input.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !input.value && i > 0) inputs[i - 1].focus();
      });
      input.addEventListener('paste', e => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
        pasted.split('').forEach((ch, j) => { if (inputs[j]) inputs[j].value = ch; });
        if (inputs[Math.min(pasted.length, inputs.length - 1)]) inputs[Math.min(pasted.length, inputs.length - 1)].focus();
      });
    });
  });

  /* ============================================
     RESEND OTP COUNTDOWN
     ============================================ */
  function startResendCountdown(btnId, seconds = 30) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    let remaining = seconds;
    btn.disabled = true;
    btn.style.opacity = '.5';
    const tick = setInterval(() => {
      btn.textContent = `Resend OTP (${remaining}s)`;
      remaining--;
      if (remaining < 0) {
        clearInterval(tick);
        btn.textContent = 'Resend OTP';
        btn.disabled = false;
        btn.style.opacity = '1';
      }
    }, 1000);
  }

  // Auto start for OTP panels when they become visible (simple approach)
  document.querySelectorAll('.otp-section').forEach(sec => {
    const btnId = sec.dataset.resendBtn;
    if (btnId) startResendCountdown(btnId, 30);
  });

  /* ============================================
     MULTI-STEP REGISTRATION NAVIGATION
     ============================================ */
  const stepContents = document.querySelectorAll('.reg-step-content');
  const stepDots     = document.querySelectorAll('.reg-step-dot');
  const stepLines    = document.querySelectorAll('.reg-step-line');
  const stepCounter  = document.getElementById('stepCounter');

  function goToStep(n) {
    if (n < 1 || n > state.totalRegSteps) return;
    state.regStep = n;

    stepContents.forEach(s => {
      s.classList.toggle('active', parseInt(s.dataset.step) === n);
    });

    stepDots.forEach((dot, i) => {
      const sn = i + 1;
      dot.classList.remove('active', 'done');
      if (sn === n)  dot.classList.add('active');
      if (sn < n)    dot.classList.add('done');
      // swap number for checkmark on done
      const dotEl = dot.querySelector('.dot');
      if (dotEl) dotEl.textContent = sn < n ? '✓' : sn;
    });

    stepLines.forEach((line, i) => {
      line.classList.toggle('done', i < n - 1);
    });

    if (stepCounter) stepCounter.textContent = `Step ${n} of ${state.totalRegSteps}`;

    // Scroll to top of right panel
    const rightPanel = document.querySelector('.auth-right');
    if (rightPanel) rightPanel.scrollTop = 0;
  }

  // Next / Prev buttons
  document.addEventListener('click', function (e) {
    if (e.target.closest('.btn-reg-next')) {
      if (validateCurrentStep()) goToStep(state.regStep + 1);
    }
    if (e.target.closest('.btn-reg-prev')) {
      goToStep(state.regStep - 1);
    }
  });

  /* ============================================
     SIMPLE STEP VALIDATION (front-end)
     ============================================ */
  function validateCurrentStep() {
    const stepEl = document.querySelector(`.reg-step-content[data-step="${state.regStep}"]`);
    if (!stepEl) return true;
    let valid = true;

    stepEl.querySelectorAll('input[required], select[required]').forEach(field => {
      const errEl = field.closest('.form-group')?.querySelector('.field-error');
      if (!field.value.trim()) {
        field.classList.add('error');
        if (errEl) { errEl.textContent = 'This field is required.'; errEl.classList.add('show'); }
        valid = false;
      } else {
        field.classList.remove('error');
        if (errEl) errEl.classList.remove('show');
      }
    });

    // Mobile validation
    const mobile = stepEl.querySelector('input[type="tel"]');
    if (mobile && mobile.value && !/^[6-9]\d{9}$/.test(mobile.value)) {
      mobile.classList.add('error');
      const errEl = mobile.closest('.form-group')?.querySelector('.field-error');
      if (errEl) { errEl.textContent = 'Enter a valid 10-digit mobile number.'; errEl.classList.add('show'); }
      valid = false;
    }

    // Email validation
    const email = stepEl.querySelector('input[type="email"]');
    if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
      email.classList.add('error');
      const errEl = email.closest('.form-group')?.querySelector('.field-error');
      if (errEl) { errEl.textContent = 'Enter a valid email address.'; errEl.classList.add('show'); }
      valid = false;
    }

    // Password match (step 1)
    if (state.regStep === 1) {
      const pwd  = document.getElementById('regPassword');
      const cpwd = document.getElementById('regConfirmPassword');
      if (pwd && cpwd && pwd.value && cpwd.value && pwd.value !== cpwd.value) {
        cpwd.classList.add('error');
        const errEl = cpwd.closest('.form-group')?.querySelector('.field-error');
        if (errEl) { errEl.textContent = 'Passwords do not match.'; errEl.classList.add('show'); }
        valid = false;
      }
    }

    return valid;
  }

  // Live clear error
  document.addEventListener('input', function (e) {
    if (e.target.matches('input, select, textarea')) {
      e.target.classList.remove('error');
      const errEl = e.target.closest('.form-group')?.querySelector('.field-error');
      if (errEl) errEl.classList.remove('show');
    }
  });

  /* ============================================
     PHOTO UPLOAD PREVIEW
     ============================================ */
  document.querySelectorAll('.photo-upload-box input[type=file]').forEach(input => {
    input.addEventListener('change', function () {
      const box   = input.closest('.photo-upload-box');
      const files = Array.from(input.files);
      if (!files.length) return;
      const preview = files[0];
      const reader  = new FileReader();
      reader.onload = ev => {
        const existing = box.querySelector('.upload-preview');
        if (existing) existing.remove();
        const img = document.createElement('img');
        img.src   = ev.target.result;
        img.className = 'upload-preview';
        img.style.cssText = 'width:60px;height:60px;border-radius:50%;object-fit:cover;margin:8px auto 0;display:block;border:2px solid var(--green-400)';
        box.appendChild(img);
        box.querySelector('p').textContent = preview.name;
      };
      reader.readAsDataURL(preview);
    });
  });

  /* ============================================
     FINAL SUBMIT (Step 10)
     ============================================ */
  const finalSubmitBtn = document.getElementById('finalSubmitBtn');
  if (finalSubmitBtn) {
    finalSubmitBtn.addEventListener('click', function () {
      const terms = document.getElementById('agreeTerms');
      if (!terms || !terms.checked) {
        alert('Please agree to the Terms & Conditions to proceed.');
        return;
      }
      finalSubmitBtn.disabled = true;
      finalSubmitBtn.textContent = 'Creating Your Account…';
      setTimeout(() => {
        // Show success screen
        const step10 = document.querySelector('.reg-step-content[data-step="10"] .step-form-body');
        if (step10) step10.innerHTML = '';
        goToStep(10); // stay on step 10 but show success
        const successDiv = document.querySelector('.reg-step-content[data-step="10"]');
        if (successDiv) successDiv.innerHTML = buildSuccessScreen();
        // Animate completion bar
        setTimeout(() => {
          const fill = document.querySelector('.completion-bar-fill');
          if (fill) fill.style.width = '65%';
        }, 400);
      }, 1800);
    });
  }

  function buildSuccessScreen() {
    return `
    <div class="success-screen">
      <div class="success-anim">🌾</div>
      <h3>Welcome to AgriCart!</h3>
      <p>Your farmer account has been created successfully.<br>Complete your profile to unlock all features.</p>
      <div class="badge-row">
        <span class="badge-chip green">🟢 Verified Farmer</span>
        <span class="badge-chip blue">📱 Mobile Verified</span>
        <span class="badge-chip gold">⭐ New Member</span>
      </div>
      <div class="profile-completion-card">
        <div class="completion-header">
          <span>Profile Completion</span>
          <span class="completion-pct">65%</span>
        </div>
        <div class="completion-bar-bg">
          <div class="completion-bar-fill" style="width:0%"></div>
        </div>
        <div class="completion-items">
          <div class="ci-item done"><span class="ci-icon">✅</span> Account Created</div>
          <div class="ci-item done"><span class="ci-icon">✅</span> Mobile Verified</div>
          <div class="ci-item done"><span class="ci-icon">✅</span> Location Added</div>
          <div class="ci-item done"><span class="ci-icon">✅</span> Farmer Type Selected</div>
          <div class="ci-item pending"><span class="ci-icon">❌</span> Farm Photos Missing</div>
          <div class="ci-item pending"><span class="ci-icon">❌</span> Equipment Details Missing</div>
          <div class="ci-item pending"><span class="ci-icon">❌</span> Bank Details Pending</div>
        </div>
      </div>
      <button class="btn-primary" onclick="closeAuthModal(); location.reload();">Go to Dashboard →</button>
    </div>`;
  }

  /* ============================================
     LOGIN FORM SUBMIT
     ============================================ */
  const loginBtn = document.getElementById('loginSubmitBtn');
  if (loginBtn) {
    loginBtn.addEventListener('click', function () {
      const method = state.loginMethod;
      let valid = true;

      if (method === 'mobile') {
        const mobile = document.getElementById('loginMobile');
        const pwd    = document.getElementById('loginPassword');
        if (!mobile || !mobile.value || !pwd || !pwd.value) {
          valid = false;
          alert('Please enter mobile number and password.');
        }
      } else if (method === 'email') {
        const email = document.getElementById('loginEmail');
        const pwd   = document.getElementById('loginEmailPassword');
        if (!email || !email.value || !pwd || !pwd.value) {
          valid = false;
          alert('Please enter email and password.');
        }
      }

      if (!valid) return;

      loginBtn.disabled = true;
      loginBtn.textContent = 'Signing In…';

      setTimeout(() => {
        loginBtn.textContent = '✓ Welcome Back!';
        loginBtn.style.background = 'linear-gradient(135deg, #16a34a, #22c55e)';
        setTimeout(() => {
          closeAuth();
          loginBtn.disabled = false;
          loginBtn.textContent = 'Sign In to AgriCart';
          loginBtn.style.background = '';
        }, 1500);
      }, 1600);
    });
  }

  /* ============================================
     FORGOT PASSWORD flow
     ============================================ */
  window.showForgotPassword = function () {
    const panel = document.querySelector('.forgot-password-section');
    const form  = document.querySelector('.login-method-panel[data-method-panel="mobile"]');
    if (panel && form) {
      form.style.display = 'none';
      panel.style.display = 'block';
    }
  };

  window.hideForgotPassword = function () {
    const panel = document.querySelector('.forgot-password-section');
    const form  = document.querySelector('.login-method-panel[data-method-panel="mobile"]');
    if (panel && form) {
      panel.style.display = 'none';
      form.style.display = 'block';
    }
  };

  /* ============================================
     INIT
     ============================================ */
  goToStep(1);

})();
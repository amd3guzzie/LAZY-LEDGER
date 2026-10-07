/* Landing page: login and sign-up modals. */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const loginModalEl = document.getElementById('loginModal');
  const signupModalEl = document.getElementById('signupModal');
  const loginForm = document.getElementById('loginForm');
  const signupForm = document.getElementById('signupForm');

  // Helper to permanently freeze the login UI
  const lockLoginUI = () => {
    Array.from(loginForm.elements).forEach(el => el.disabled = true);
    loginForm.querySelectorAll('.btn-link-ll').forEach(link => link.style.display = 'none');
    const errorBox = loginForm.querySelector('.form-error');
    errorBox.textContent = 'Too many failed attempts. Please contact the admin of the page.';
    errorBox.hidden = false;
  };

  // Check for existing browser lock immediately on load
  if (localStorage.getItem('ll_login_locked') === 'true') {
    lockLoginUI();
  }

  // Switch between login and sign-up modals.
  document.querySelectorAll('[data-switch]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const from = btn.closest('.modal');
      bootstrap.Modal.getInstance(from)?.hide();
      from.addEventListener('hidden.bs.modal', () => bootstrap.Modal.getOrCreateInstance(document.querySelector(btn.dataset.switch)).show(), { once: true });
    });
  });

  // Open login automatically after being redirected from a protected page.
  if (new URLSearchParams(location.search).has('login') || location.hash === '#login') {
    bootstrap.Modal.getOrCreateInstance(loginModalEl).show();
  }

  loginModalEl.addEventListener('shown.bs.modal', () => {
    if (localStorage.getItem('ll_login_locked') !== 'true') loginModalEl.querySelector('input').focus();
  });
  signupModalEl.addEventListener('shown.bs.modal', () => signupModalEl.querySelector('input').focus());

  const emailOk = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);

  // Mirrors the API rule: valid date, not in the future, at least 13 years old.
  const birthDateError = (v) => {
    if (!v) return 'Date of birth is required.';
    const max = signupForm.elements.birth_date.max;
    if (!/^\d{4}-\d{2}-\d{2}$/.test(v) || v < '1900-01-01') return 'Please enter a valid date of birth.';
    if (v > LL.today()) return 'Date of birth cannot be in the future.';
    if (max && v > max) return 'You must be at least 13 years old to sign up.';
    return null;
  };
  const consentError = 'Please confirm your details and agree to the Data Privacy Notice.';

// --- PASSWORD VISIBILITY TOGGLE ---
  document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
      const input = document.querySelector(this.dataset.target);
      const icon = this.querySelector('i');
      
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
        icon.style.color = '#c77a5f';
      } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
        icon.style.color = '#c77a5f';
      }
    });
  });

 // --- LOGIN FORM ---
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (localStorage.getItem('ll_login_locked') === 'true') return;

    const data = LL.formData(loginForm);
    const fields = {};
    
    if (typeof emailOk === 'function' && !emailOk(data.email)) {
      fields.email = 'Please enter a valid email address.';
    } else if (!data.email) {
      fields.email = 'Email is required.';
    }
    
    if (!loginForm.elements.password.value) {
      fields.password = 'Password is required.';
    }

    if (Object.keys(fields).length) {
      return LL.showErrors(loginForm, { fields });
    }
    
    const btn = loginForm.querySelector('[type="submit"]');
    LL.clearErrors(loginForm);
    document.getElementById('loginSuccess').hidden = true;
    if (btn) btn.disabled = true;

    grecaptcha.ready(function() {
      grecaptcha.execute('6LeC6dYtAAAAAECIlAtZGIffeHlx8gDNwLwYMlO_', {action: 'login'}).then(async function(token) {
        data['g-recaptcha-response'] = token;
        data.password = loginForm.elements.password.value; // never trim passwords

        try {
          const res = await LL.api('/auth/login', { method: 'POST', body: data });
          if (res && res.redirect) {
            window.location.href = res.redirect;
          } else {
            throw new Error('Invalid server response format. Please check the backend.');
          }
        } catch (err) {
          if (err.status === 429) {
            localStorage.setItem('ll_login_locked', 'true');
            if (typeof lockLoginUI === 'function') lockLoginUI();
          } else {
            LL.showErrors(loginForm, err);
            if (btn) btn.disabled = false;
          }
        }
      });
    });
  });

  // --- FORGOT PASSWORD (emailed one-time code) ---
  const forgotModalEl = document.getElementById('forgotModal');
  const forgotForm = document.getElementById('forgotForm');
  const resetForm = document.getElementById('resetForm');
  const pwRuleError = (v) => (v.length < 8 || !/[A-Za-z]/.test(v) || !/\d/.test(v) ? 'At least 8 characters, with a letter and a number.' : null);
  let resetEmail = '';

  const showForgotStep = (step) => {
    forgotForm.hidden = step !== 'email';
    resetForm.hidden = step !== 'code';
    (step === 'email' ? forgotForm.elements.email : resetForm.elements.code).focus();
  };

  document.getElementById('forgotLink').addEventListener('click', () => {
    forgotForm.reset();
    resetForm.reset();
    LL.clearErrors(forgotForm);
    LL.clearErrors(resetForm);
    forgotForm.elements.email.value = loginForm.elements.email.value.trim();
    bootstrap.Modal.getInstance(loginModalEl)?.hide();
    loginModalEl.addEventListener('hidden.bs.modal', () => bootstrap.Modal.getOrCreateInstance(forgotModalEl).show(), { once: true });
  });
  forgotModalEl.addEventListener('shown.bs.modal', () => showForgotStep('email'));

  const requestCode = async (email) => {
    const res = await LL.api('/auth/forgot-password', { method: 'POST', body: { email } });
    resetEmail = email;
    document.getElementById('resetSent').textContent = res.message;
  };

  forgotForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const email = forgotForm.elements.email.value.trim();
    if (!emailOk(email)) return LL.showErrors(forgotForm, { fields: { email: 'Please enter a valid email address.' } });
    LL.submitting(forgotForm, async () => {
      await requestCode(email);
      LL.clearErrors(forgotForm);
      showForgotStep('code');
    });
  });

  document.getElementById('resendCode').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    btn.disabled = true;
    try {
      await requestCode(resetEmail);
      LL.clearErrors(resetForm);
      LL.toast('A new code is on its way.');
    } catch (err) {
      LL.showErrors(resetForm, err);
    } finally {
      btn.disabled = false;
    }
  });

  // Digits only in the code box.
  resetForm.elements.code.addEventListener('input', (e) => { e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6); });
resetForm.elements.confirm_password.addEventListener('paste', (e) => e.preventDefault());

resetForm.addEventListener('submit', (e) => {
  e.preventDefault();
  const data = {
    email: resetEmail,
    code: resetForm.elements.code.value.trim(),
    password: resetForm.elements.password.value,
    confirm_password: resetForm.elements.confirm_password.value,
  };
  const fields = {};

  // Code validation
  if (!/^\d{6}$/.test(data.code)) {
    fields.code = 'Enter the 6-digit code from the email.';
  }

  // Password validation
  if (!data.password) {
    fields.password = 'Password is required.';
  } else if (data.password.length < 8 || !/[A-Za-z]/.test(data.password) || !/\d/.test(data.password)) {
    fields.password = 'At least 8 characters, with a letter and a number.';
  }

  // Confirm password validation
  if (!data.confirm_password) {
    fields.confirm_password = 'Please confirm your password.';
  } else if (data.confirm_password !== data.password) {
    fields.confirm_password = 'Passwords do not match.';
  }

  // Check for any errors
  if (Object.keys(fields).length) return LL.showErrors(resetForm, { fields });

  // Submit if valid
  LL.submitting(resetForm, async () => {
    const res = await LL.api('/auth/reset-password', { method: 'POST', body: data });
    bootstrap.Modal.getInstance(forgotModalEl)?.hide();
    
    forgotModalEl.addEventListener('hidden.bs.modal', () => {
      LL.clearErrors(loginForm);
      loginForm.elements.email.value = res.email || resetEmail;
      loginForm.elements.password.value = '';
      
      const ok = document.getElementById('loginSuccess');
      ok.textContent = res.message;
      ok.hidden = false;
      
      bootstrap.Modal.getOrCreateInstance(loginModalEl).show();
      loginModalEl.addEventListener('shown.bs.modal', () => loginForm.elements.password.focus(), { once: true });
    }, { once: true });
  });
});

  // --- SIGNUP FORM (Inline Validation & Password Strength) ---
  
  // Helper to show errors on a single field instantly
  const showInlineError = (input, msg) => {
  input.classList.toggle('is-invalid', !!msg);

  const parent = input.parentElement;
  let fb = parent.querySelector('.invalid-feedback');
  
  if (!fb && msg) {
    fb = document.createElement('div');
    fb.className = 'invalid-feedback';
    
    if (parent.classList.contains('input-group')) {
      parent.appendChild(fb);
      parent.classList.add('has-validation'); 
    } else {
      input.insertAdjacentElement('afterend', fb);
    }
  }
  
  if (fb) fb.textContent = msg || '';
};

  // Validate individual fields on blur (Tab key or clicking away)
  const validateSignupField = (input) => {
    const name = input.name;
    const val = input.value.trim();
    let err = null;
    
    if (name === 'first_name') {
      if (!val) err = 'First name is required.';
      else if (val.length < 2) err = 'First name must be at least 2 characters.';
    } else if (name === 'last_name') {
      if (!val) err = 'Last name is required.';
      else if (val.length < 2) err = 'Last name must be at least 2 characters.';
    } else if (name === 'email') {
      if (!val) err = 'Email is required.';
      else if (!emailOk(val)) err = 'Please enter a valid email address.';
    } else if (name === 'password') {
      if (!val) err = 'Password is required.';
      else if (val.length < 8 || !/[A-Za-z]/.test(val) || !/\d/.test(val)) err = 'At least 8 characters, with a letter and a number.';
    } else if (name === 'confirm_password') {
      const pw = signupForm.elements.password.value;
      if (!val) err = 'Please confirm your password.';
      else if (val !== pw) err = 'Passwords do not match.';
    } else if (name === 'birth_date') {
      err = birthDateError(val);
    } else if (name === 'gender') {
      if (!val) err = 'Please select a gender (or “Prefer not to say”).';
    } else if (name === 'privacy_consent') {
      if (!input.checked) err = consentError;
    }
    
    showInlineError(input, err);
  };

  // Without this, mousedown on "Sign up" blurs the focused field, its error message pushes the
  // button down, and the mouseup misses it, so the first click does nothing. Submit validates every field anyway.
  signupForm.querySelector('[type="submit"]').addEventListener('mousedown', (e) => e.preventDefault());

  // Validate each field when the user leaves it (selects and the checkbox on change)
  Array.from(signupForm.elements).forEach(el => {
    if (el.type === 'checkbox' || el.tagName === 'SELECT') {
      el.addEventListener('change', () => validateSignupField(el));
    } else if (el.tagName === 'INPUT') {
      el.addEventListener('blur', () => validateSignupField(el));
    }
  });

  // Real-time password strength meter
  const regPassword = document.getElementById('regPassword');
  const pwHelp = document.getElementById('pwHelp');
  
  regPassword.addEventListener('input', (e) => {
    const val = e.target.value;
    if (!val) {
      pwHelp.textContent = 'At least 8 characters, with a letter and a number.';
      pwHelp.className = 'form-text';
      return;
    }
    
    const hasLetter = /[A-Za-z]/.test(val);
    const hasNumber = /\d/.test(val);
    const hasSpecial = /[^A-Za-z0-9]/.test(val);
    
    if (val.length < 8 || !hasLetter || !hasNumber) {
      pwHelp.textContent = 'Strength: Weak (Needs 8+ chars, letter & number)';
      pwHelp.className = 'form-text text-danger fw-bold';
    } else if (val.length >= 10 && hasSpecial) {
      pwHelp.textContent = 'Strength: Strong';
      pwHelp.className = 'form-text text-success fw-bold';
    } else {
      pwHelp.textContent = 'Strength: Fair';
      pwHelp.className = 'form-text text-warning fw-bold';
    }
  });

  // Prevent pasting into the confirm password field
  const regConfirm = document.getElementById('regConfirm');
  if (regConfirm) {
    regConfirm.addEventListener('paste', (e) => {
      e.preventDefault();
    });
  }

  // Final validation block on form submission
  signupForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.target;
    const data = LL.formData(form);
    data.password = form.elements.password.value;
    data.confirm_password = form.elements.confirm_password.value;
    const fields = {};
    
    if (!data.first_name || data.first_name.length < 2) fields.first_name = 'First name must be at least 2 characters.';
    if (!data.last_name || data.last_name.length < 2) fields.last_name = 'Last name must be at least 2 characters.';
    if (!emailOk(data.email)) fields.email = 'Please enter a valid email address.';
    if (!data.password) {
      fields.password = 'Password is required.';
    } else if (data.password.length < 8 || !/[A-Za-z]/.test(data.password) || !/\d/.test(data.password)) {
      fields.password = 'At least 8 characters, with a letter and a number.';
    }
    if (data.password !== data.confirm_password) fields.confirm_password = 'Passwords do not match.';
    const dobErr = birthDateError(data.birth_date);
    if (dobErr) fields.birth_date = dobErr;
    if (!data.gender) fields.gender = 'Please select a gender (or “Prefer not to say”).';
    if (!data.currency) fields.currency = 'Please choose a currency.';
    if (data.privacy_consent !== true) fields.privacy_consent = consentError;
    
    if (Object.keys(fields).length) return LL.showErrors(form, { fields });
    
    LL.submitting(form, async () => {
      const res = await LL.api('/auth/register', { method: 'POST', body: data });
      if (!res || !res.ok) throw new Error('Invalid server response format. Please check the backend.');

      // Account created, but no session: the user has to log in (and pass the CAPTCHA) first.
      form.reset();
      LL.clearErrors(form);
      pwHelp.textContent = 'At least 8 characters, with a letter and a number.';
      pwHelp.className = 'form-text';
      bootstrap.Modal.getInstance(signupModalEl)?.hide();
      signupModalEl.addEventListener('hidden.bs.modal', () => {
        LL.clearErrors(loginForm);
        loginForm.elements.email.value = res.email || data.email;
        loginForm.elements.password.value = '';
        const ok = document.getElementById('loginSuccess');
        ok.textContent = res.message || 'Account created! Please log in to continue.';
        ok.hidden = false;
        bootstrap.Modal.getOrCreateInstance(loginModalEl).show();
        loginModalEl.addEventListener('shown.bs.modal', () => loginForm.elements.password.focus(), { once: true });
      }, { once: true });
    });
  });
});
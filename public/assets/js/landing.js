/* Landing page: login and sign-up modals. */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const loginModalEl = document.getElementById('loginModal');
  const signupModalEl = document.getElementById('signupModal');
  const loginForm = document.getElementById('loginForm');
  const signupForm = document.getElementById('signupForm');
  document.querySelectorAll('[data-bs-toggle="popover"]').forEach((el) => new bootstrap.Popover(el));

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

  // --- SIGNUP FORM (Inline Validation & Password Strength) ---
  
  // Helper to show errors on a single field instantly
  const showInlineError = (input, msg) => {
    input.classList.toggle('is-invalid', !!msg);
    let fb = input.parentElement.querySelector('.invalid-feedback');
    if (!fb && msg) {
      fb = document.createElement('div');
      fb.className = 'invalid-feedback';
      input.insertAdjacentElement('afterend', fb);
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
      if (val && val.length < 2) err = 'Last name must be at least 2 characters.';
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
    if (data.last_name && data.last_name.length < 2) fields.last_name = 'Last name must be at least 2 characters.';
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
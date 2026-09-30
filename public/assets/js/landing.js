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

  // --- LOGIN FORM ---
 loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (localStorage.getItem('ll_login_locked') === 'true') return;

    const data = LL.formData(loginForm);
    const fields = {};
    
    if (!emailOk(data.email)) fields.email = 'Please enter a valid email address.';
    if (!loginForm.elements.password.value) fields.password = 'Password is required.';
    
    // Ensure the CAPTCHA was checked before hitting the server
    if (!data['g-recaptcha-response']) {
        fields['g-recaptcha-response'] = 'Please complete the CAPTCHA.';
    }

    if (Object.keys(fields).length) return LL.showErrors(loginForm, { fields });
    
    data.password = loginForm.elements.password.value; // never trim passwords
    
    const btn = loginForm.querySelector('[type="submit"]');
    LL.clearErrors(loginForm);
    if (btn) btn.disabled = true;

    try {
      const res = await LL.api('/auth/login', { method: 'POST', body: data });
      if (res && res.redirect) {
        window.location.href = res.redirect;
      } else {
        throw new Error('Invalid server response format. Please check the backend.');
      }
    } catch (err) {
      // Reset the CAPTCHA widget on any login failure
      if (typeof grecaptcha !== 'undefined') {
        grecaptcha.reset();
      }

      if (err.status === 429) {
        localStorage.setItem('ll_login_locked', 'true');
        lockLoginUI();
      } else {
        LL.showErrors(loginForm, err);
        if (btn) btn.disabled = false;
      }
    }
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
    }
    
    showInlineError(input, err);
  };

  // Attach the blur listener to all inputs in the sign up form
  Array.from(signupForm.elements).forEach(el => {
    if (el.tagName === 'INPUT') {
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
    if (data.password.length < 8 || !/[A-Za-z]/.test(data.password) || !/\d/.test(data.password)) {
      fields.password = 'At least 8 characters, with a letter and a number.';
    }
    if (data.password !== data.confirm_password) fields.confirm_password = 'Passwords do not match.';
    
    if (Object.keys(fields).length) return LL.showErrors(form, { fields });
    
    LL.submitting(form, async () => {
      const res = await LL.api('/auth/register', { method: 'POST', body: data });
      if (res && res.redirect) {
        window.location.href = res.redirect;
      } else {
        throw new Error('Invalid server response format. Please check the backend.');
      }
    });
  });
});
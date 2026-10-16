<?php declare(strict_types=1);
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/views/layout.php';
require_once dirname(__DIR__) . '/src/currencies.php';

send_security_headers();
$user = current_user();
require_once dirname(__DIR__) . '/src/visitors.php';
log_visit();

$team = [
    ['Agapito, Raphael Lian', 'Database / API Developer'],
    ['De Guzman, Alyssa Mae', 'Project Manager'],
    ['Granada, Justin Daniel', 'Frontend Developer'],
    ['Magdaluyo, Sydney Allison', 'Backend Developer'],
    ['Robles, Nicole Joy', 'QA / UI / Documentation'],
];
// Latest 4-5 star reviews shown on the landing page (revision #5).
try {
    $reviews = q_all(
        "SELECT r.rating, r.comment, r.updated_at,
                CONCAT(u.first_name, IF(u.last_name = '', '', CONCAT(' ', LEFT(u.last_name, 1), '.'))) AS author
         FROM reviews r JOIN users u ON u.id = r.user_id
         WHERE r.rating >= 4 ORDER BY r.updated_at DESC LIMIT 3"
    );
} catch (Throwable $e) {
    $reviews = []; // reviews table not created yet
}

// Made-up budgeting events (revision #5). Edit this list to change what the landing page shows.
$events = [
    ['Oct 24, 2026', 'Budgeting 101 for Students', 'Free online workshop: build your first monthly budget with the 50/30/20 rule.'],
    ['Nov 7, 2026', 'No-Spend Weekend Challenge', 'Track every peso for 2 days and share what you saved with the community.'],
    ['Nov 21, 2026', 'Holiday Savings Live Q&A', 'Plan your Christmas spending early so January does not hurt.'],
];

// YouTube videos about budgeting (verified video IDs; shown with the privacy-enhanced player).
$videos = [
    ['-Of_WRjDrx0', 'How to Make a Budget: Beginner\'s Guide'],
    ['NUSjIhHzBlQ', 'How to Create a Budget When Money Is Tight'],
    ['9pk130oW0PI', 'How to Save & Budget as a Beginner'],
];

// Sign-up requires age 13+ (also enforced by the API).
$maxBirthDate = (new DateTimeImmutable('today'))->modify('-13 years')->format('Y-m-d');
render_head('Lazy Ledger');
?>
<body class="landing">
<a class="visually-hidden-focusable" href="#home">Skip to content</a>
<div class="container-xl py-3 py-md-4" id="top">

  <nav class="panel landing-nav d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 px-md-4 py-3 mb-3" aria-label="Main">
    <a class="brand d-flex align-items-center gap-2 text-decoration-none" href="#top">
      <img src="/assets/img/logo.png" style="height: 36px; width: auto;">LAZY LEDGER</a>
    <div class="d-flex align-items-center gap-3 gap-md-4">
      <a class="nav-item-link" href="#features">Features</a>
      <a class="nav-item-link" href="#how">How it works</a>
      <a class="nav-item-link" href="#about">About Us</a>
      <span class="divider" aria-hidden="true"></span>
      <?php if ($user): ?>
        <a class="nav-item-link" href="<?= e(home_for_role($user['role'])) ?>">Dashboard</a>
      <?php else: ?>
        <a class="nav-item-link" href="#login" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a>
        <a class="btn btn-ll btn-sm" href="#signup" data-bs-toggle="modal" data-bs-target="#signupModal">Sign up free</a>
      <?php endif; ?>
    </div>
  </nav>

  <section class="panel p-4 p-md-5 mb-3" id="home">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <h1 class="hero-title">MANAGE YOUR<br>FINANCES,<br>EFFORTLESSLY.</h1>
      </div>
      <div class="col-lg-5 text-center">
        <img class="hero-img" src="/assets/img/hero-section.png" alt="Open ledger book with coins and a calculator">
      </div>
      <div class="col-lg-8">
        <p class="hero-copy mb-3">LazyLedger helps you track income and expenses, set budgets and see where your money goes, without the stress.</p>
        <ul class="list-unstyled benefit-list mb-0">
          <li><i class="bi bi-check-circle-fill"></i> Free to use, set up in under a minute</li>
          <li><i class="bi bi-check-circle-fill"></i> Budgets that warn you before you overspend</li>
          <li><i class="bi bi-check-circle-fill"></i> Track cash, banks and e-wallets in one place</li>
        </ul>
      </div>
      <div class="col-lg-4 text-lg-end d-grid d-lg-block gap-2">
        <?php if ($user): ?>
          <a class="btn btn-ll btn-lg" href="<?= e(home_for_role($user['role'])) ?>">Go to dashboard <i class="bi bi-chevron-right"></i></a>
        <?php else: ?>
          <button class="btn btn-ll btn-lg" type="button" data-bs-toggle="modal" data-bs-target="#signupModal">Sign up free <i class="bi bi-chevron-right"></i></button>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="panel p-4 p-md-4 mb-3 text-center" id="features" style="padding-bottom: 3rem;">
    <h2 class="section-title mb-2">WHY CHOOSE LAZYLEDGER?</h2>
    <p class="hero-copy">Take control of your daily budget with a streamlined tracker designed for clarity, security, and ease of use.</p>
    <div class="row g-4 mt-2 mb-4">
      <div class="col-md-4">
        <div class="feature-card">
          <img src="/assets/img/feature-section-1.png" alt="">
          <h3>Track Wallets &amp; Banks</h3>
          <p>Manage cash, e-wallets, and bank balances in one dashboard.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <img src="/assets/img/feature-section-2.png" alt="">
          <h3>Live currency conversion</h3>
          <p>Track in your own currency and view it in 30 others with live exchange rates.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <img src="/assets/img/feature-section-3.png" alt="">
          <h3>Customer, Staff &amp; Admin</h3>
          <p>Dedicated views for accounts, requests, and reports.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- HOW IT WORKS: what the system does and how to use it (revision #11) -->
  <section class="panel p-4 p-md-5 mb-3" id="how">
    <h2 class="section-title mb-2 text-center">HOW LAZYLEDGER WORKS</h2>
    <p class="hero-copy text-center mx-auto" style="max-width: 900px">LazyLedger is a personal budget tracker. You record the money that comes in and goes out, and it shows you how much you have left, warns you before you overspend and charts where your money goes.</p>
    <div class="row g-3 mt-2">
      <?php foreach ([
          ['1', 'Sign up and confirm', 'Create a free account, then click the link we email you to activate it.'],
          ['2', 'Add your accounts', 'Add your cash, bank, e-wallet or savings accounts with their current balance.'],
          ['3', 'Record transactions', 'Log each expense or income. Set recurring bills so you never forget them.'],
          ['4', 'Set budgets and review', 'Set a monthly limit per category and check Stats to see where your money went.'],
      ] as [$num, $title, $text]): ?>
        <div class="col-sm-6 col-lg-3">
          <div class="d-flex gap-3 align-items-start">
            <div class="step-num"><?= e($num) ?></div>
            <div><h3 class="step-title"><?= e($title) ?></h3><p class="step-sub"><?= e($text) ?></p></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- REVIEWS from real users (revision #5) -->
  <section class="panel p-4 p-md-5 mb-3 text-center" id="reviews">
    <h2 class="section-title mb-3">WHAT OUR USERS SAY</h2>
    <?php if ($reviews): ?>
      <div class="row g-3">
        <?php foreach ($reviews as $r): ?>
          <div class="col-md-4">
            <div class="feature-card h-100 text-start">
              <div class="text-terracotta fs-4" aria-label="<?= (int) $r['rating'] ?> out of 5 stars"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></div>
              <p class="my-2">&ldquo;<?= e($r['comment']) ?>&rdquo;</p>
              <small class="fw-bold text-muted-ll"><?= e($r['author']) ?> &middot; <?= e(date('M j, Y', strtotime($r['updated_at']))) ?></small>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="hero-copy mb-0">No reviews yet. Sign up and be the first to rate LazyLedger!</p>
    <?php endif; ?>
  </section>

  <!-- EVENTS and VIDEOS about budgeting (revision #5) -->
  <section class="panel p-4 p-md-5 mb-3" id="events">
    <h2 class="section-title mb-3 text-center">UPCOMING EVENTS</h2>
    <div class="row g-3 mb-4">
      <?php foreach ($events as [$date, $title, $text]): ?>
        <div class="col-md-4">
          <div class="feature-card h-100 text-start">
            <div class="fw-bold text-terracotta"><i class="bi bi-calendar-event"></i> <?= e($date) ?></div>
            <h3 class="mt-2"><?= e($title) ?></h3>
            <p><?= e($text) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <h2 class="section-title mb-3 text-center">LEARN TO BUDGET</h2>
    <div class="row g-3">
      <?php foreach ($videos as [$id, $title]): ?>
        <div class="col-md-4">
          <div class="ratio ratio-16x9">
            <iframe src="https://www.youtube-nocookie.com/embed/<?= e($id) ?>" title="<?= e($title) ?>" loading="lazy" allow="encrypted-media; picture-in-picture" allowfullscreen></iframe>
          </div>
          <p class="fw-bold mt-2 mb-0"><?= e($title) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if (!$user): ?>
  <section class="panel p-4 p-md-5 mb-3 text-center" id="join">
    <h2 class="section-title mb-2">READY TO KNOW WHERE YOUR MONEY WENT?</h2>
    <p class="hero-copy">Join LazyLedger today and make your first budget in minutes.</p>
    <button class="btn btn-ll btn-lg" type="button" data-bs-toggle="modal" data-bs-target="#signupModal">Create my free account <i class="bi bi-chevron-right"></i></button>
    <p class="small fw-bold mt-3 mb-0">Already a member? <a href="#login" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a></p>
  </section>
  <?php endif; ?>

<section class="panel p-4 p-md-5 mb-3 text-center" id="about">
    <h2 class="section-title mb-3">ABOUT LAZY LEDGER</h2>
    <p class="hero-copy mx-auto" style="max-width: 900px">LazyLedger combines a secure, role-based backend architecture with a clean, responsive frontend interface. By integrating a MySQL database, a PHP REST API, and external APIs   such as live currency conversion   we strive to deliver a reliable, full-stack application that bridges academic concepts with real-world usability.</p>
    <div class="team-panel p-4 p-md-5 mt-4">
      <h2 class="section-title">MEET THE TEAM</h2>
      <p class="fw-bold fs-5" style="color: var(--ll-steel)">The student developers behind LazyLedger.</p>
      <div class="row g-4 justify-content-center mt-1">
        <?php foreach ($team as [$name, $role]): ?>
          <?php $initials = implode('', array_map(fn($p) => mb_substr(trim($p), 0, 1), explode(',', $name))); ?>
          <div class="col-sm-6 col-lg-4">
            <div class="team-card">
              <div class="team-photo" aria-hidden="true"><?= e($initials) ?></div>
              <h3><?= e($name) ?></h3>
              <p><?= e($role) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <a class="back-to-top mt-4" href="#top"><i class="bi bi-chevron-up"></i> BACK TO THE TOP <i class="bi bi-chevron-up"></i></a>
  </section>

  <footer class="panel p-4 p-md-5">
    <div class="row g-4 align-items-end">
      <div class="col-md-8">
        <div class="footer-title">LAZY LEDGER</div>
        <p class="fw-bold text-terracotta mb-0">Simplifying personal finance tracking,<br>one transaction at a time.</p>
      </div>
      <div class="col-md-4 text-md-end">
        <?php render_quick_links(); ?>
      </div>
    </div>
    <p class="text-center text-terracotta fw-bold small mt-4 mb-0">All rights reserved.   <?= date('Y') ?> LazyLedger. | Developed as a final project for ITS122P by Group 6.</p>
  </footer>
</div>

<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3 p-md-4">
      <div class="modal-header pb-0">
      <h2 class="brand d-flex align-items-center gap-2 text-decoration-none" href="#top">
      <img src="/assets/img/logo.png" style="height: 36px; width: auto;">LAZY LEDGER</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="loginForm" novalidate>
        <div class="login-instructions" role="note">
          <strong>How to log in</strong>
          <ul>
            <li>Fields marked <span class="text-danger fw-bold">*</span> are required.</li>
            <li>Use the email you registered with. New accounts must be confirmed first (check your inbox for the link).</li>
            <li>Passwords are 8-72 characters with at least one letter and one number.</li>
            <li>Forgot your password? Click <em>Forgot Password?</em>, enter your email and use the 6-digit code we send you. The code lasts 10 minutes.</li>
            <li>Too many wrong attempts temporarily locks login.</li>
            <li>Didn't get the confirmation email? <button type="button" class="btn-link-ll small" id="resendBtn">Resend it</button></li>
          </ul>
        </div>
        <div class="alert alert-success py-2 fw-bold" id="loginSuccess" role="status" hidden></div>
        <div class="alert alert-danger py-2 form-error" role="alert" hidden></div>
        
        <div class="mb-3">
          <label class="form-label" for="loginEmail">Email Address<span class="req">*</span></label>
          <input class="form-control" type="email" id="loginEmail" name="email" autocomplete="email" required maxlength="190">
        </div>
        <div class="mb-1">
          <label class="form-label" for="loginPassword">Password<span class="req">*</span></label>
          <div class="input-group">
            <input class="form-control" type="password" id="loginPassword" name="password" autocomplete="current-password" required maxlength="128">
            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#loginPassword" style="border: 2px solid #c77a5f; background-color: white;">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <div class="text-end mb-3">
          <button type="button" class="btn-link-ll small" style="color:#f59366" id="forgotLink">Forgot Password?</button>
        </div>
        
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
          <span class="fw-bold text-terracotta small">New here? <button type="button" class="btn-link-ll" data-switch="#signupModal">Create Account</button></span>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-ll-outline btn-lg" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-ll btn-lg" type="submit" id="loginSubmitBtn">Login</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Forgot password: email a one-time code, then enter it with the new password -->
<div class="modal fade" id="forgotModal" tabindex="-1" aria-labelledby="forgotTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3 p-md-4">
      <div class="modal-header pb-0">
        <h2 class="fs-4 font-display text-terracotta mb-0" id="forgotTitle">Reset your password</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="forgotForm" novalidate>
        <div class="alert alert-danger py-2 form-error" role="alert" hidden></div>
        <p class="small mb-3">Enter the email you signed up with and we'll send you a 6-digit code.</p>
        <div class="mb-3">
          <label class="form-label" for="forgotEmail">Email Address<span class="req">*</span></label>
          <input class="form-control" type="email" id="forgotEmail" name="email" autocomplete="email" required maxlength="190">
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
          <button type="button" class="btn-link-ll small" data-switch="#loginModal">Back to login</button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-ll-outline btn-lg" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-ll btn-lg" type="submit">Send code</button>
          </div>
        </div>
      </form>
      <form class="modal-body" id="resetForm" novalidate hidden>
        <div class="alert alert-success py-2 small fw-bold" id="resetSent" role="status"></div>
        <div class="alert alert-danger py-2 form-error" role="alert" hidden></div>
        <div class="mb-3">
          <label class="form-label" for="resetCode">6-digit code<span class="req">*</span></label>
          <input class="form-control otp-input" id="resetCode" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="resetPassword">New Password<span class="req">*</span></label>
          <div class="input-group">
            <input class="form-control" type="password" id="resetPassword" name="password" autocomplete="new-password" required minlength="8" maxlength="72" aria-describedby="resetPwHelp">
            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#resetPassword" style="border: 2px solid #c77a5f; background-color: white;">
                <i class="bi bi-eye"></i>
            </button>
          </div>
          <div class="form-text" id="resetPwHelp">At least 8 characters, with a letter and a number.</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="resetConfirm">Confirm New Password<span class="req">*</span></label>
          <div class="input-group">
            <input class="form-control" type="password" id="resetConfirm" name="confirm_password" autocomplete="new-password" required maxlength="72">
            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#resetConfirm" style="border: 2px solid #c77a5f; background-color: white;">
                <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
          <span class="small fw-bold text-terracotta">No email? <button type="button" class="btn-link-ll" id="resendCode">Send a new code</button></span>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-ll-outline btn-lg" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-ll btn-lg" type="submit">Reset password</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Sign up -->
<div class="modal fade" id="signupModal" tabindex="-1" aria-labelledby="signupTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content p-3 p-md-4">
      <div class="modal-header pb-0 justify-content-center position-relative">
        <h2 class="brand d-flex align-items-center gap-2 text-decoration-none" href="#top">
      <img src="/assets/img/logo.png" style="height: 36px; width: auto;">LAZY LEDGER</h2>
        <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="signupForm" novalidate>
        <div class="alert alert-danger py-2 form-error" role="alert" hidden></div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="regFirst">First Name<span class="req">*</span></label>
            <input class="form-control" id="regFirst" name="first_name" autocomplete="given-name" required maxlength="60">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="regLast">Last Name<span class="req">*</span></label>
            <input class="form-control" id="regLast" name="last_name" autocomplete="family-name" required maxlength="60">
          </div>
          <div class="col-12">
            <label class="form-label" for="regEmail">Email Address<span class="req">*</span></label>
            <input class="form-control" type="email" id="regEmail" name="email" autocomplete="email" required maxlength="190">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="regBirth">Date of Birth<span class="req">*</span></label>
            <input class="form-control" type="date" id="regBirth" name="birth_date" autocomplete="bday" required min="1900-01-01" max="<?= e($maxBirthDate) ?>" aria-describedby="birthHelp">
            <div class="form-text" id="birthHelp">You must be at least 13 years old.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="regGender">Gender<span class="req">*</span></label>
            <select class="form-select" id="regGender" name="gender" required>
              <option value="" selected disabled>Select…</option>
              <option value="female">Female</option>
              <option value="male">Male</option>
              <option value="non_binary">Non-binary</option>
              <option value="prefer_not_to_say">Prefer not to say</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="regCurrency">Currency<span class="req">*</span></label>
            <select class="form-select" id="regCurrency" name="currency" required aria-describedby="currencyHelp">
              <?php foreach (CURRENCIES as $code => $label): ?>
                <option value="<?= e($code) ?>"<?= $code === 'PHP' ? ' selected' : '' ?>><?= e("$code — $label") ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text" id="currencyHelp">Your amounts are recorded in this currency.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="regPassword">Password<span class="req">*</span></label>
            <div class="input-group">
              <input class="form-control" type="password" id="regPassword" name="password" autocomplete="new-password" required minlength="8" maxlength="72" aria-describedby="pwHelp">
              <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#regPassword" style="border: 2px solid #c77a5f; background-color: white;">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <div class="form-text" id="pwHelp">At least 8 characters, with a letter and a number.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="regConfirm">Confirm Password<span class="req">*</span></label>
            <div class="input-group">
              <input class="form-control" type="password" id="regConfirm" name="confirm_password" autocomplete="new-password" required minlength="8" maxlength="72">
              <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#regConfirm" style="border: 2px solid #c77a5f; background-color: white;">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>
          <div class="col-12">
            <h3 class="form-label fs-6 mb-1" id="privacyTitle">Data Privacy Notice</h3>
            <div class="privacy-notice" tabindex="0" role="region" aria-labelledby="privacyTitle">
              <p>LazyLedger respects your privacy and processes your personal data in accordance with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>, its Implementing Rules and Regulations, and issuances of the National Privacy Commission (NPC).</p>
              <p><strong>What we collect.</strong> Your name, email address, date of birth, gender and preferred currency; the accounts, transactions, budgets, category requests and support tickets you enter; and security records such as login times, IP addresses, the pages you visit and the buttons you click, and an activity log of actions taken on your account.</p>
              <p><strong>Why we collect it.</strong> To create and secure your account, confirm you meet the minimum age, show your finances in your chosen currency, send account and support emails, respond to your requests and tickets, and detect and investigate misuse.</p>
              <p><strong>Who can see it.</strong> Your balances and transactions are visible only to you. Our support staff can see your name, email, account status, category requests and tickets, never your transactions. Administrators see aggregated statistics and a security activity log that records which actions were taken (for example, “added a transaction”) but not amounts or descriptions. We do not sell your data. Service providers that process data on our behalf: our hosting provider, Brevo (email delivery) and Google reCAPTCHA (bot protection at login). Exchange rates are fetched from the Frankfurter API without any personal data.</p>
              <p><strong>How long we keep it.</strong> Until you delete your account from your Profile, which permanently erases your financial records. Visitor logs are deleted after 12 months. Security log entries may be kept afterwards to protect the service. Read the full <a href="/company.php#retention" target="_blank" rel="noopener">Data Retention Policy</a>.</p>
              <p><strong>Your rights.</strong> You have the right to be informed, to access, correct, and object to processing of your data, to erasure or blocking, to data portability (export your transactions as CSV from your Profile), to damages, and to file a complaint with the National Privacy Commission.</p>
              <p class="mb-0"><strong>Contact.</strong> For privacy questions or requests, email our Data Protection Officer at <a href="mailto:support@lazyledger.app">support@lazyledger.app</a>.</p>
            </div>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" id="regConsent" name="privacy_consent" required>
              <label class="form-check-label fw-bold small" for="regConsent">I confirm that the information I provided is true and correct, and I have read and agree to the collection and processing of my personal data as described in the Data Privacy Notice and the <a href="/company.php#retention" target="_blank" rel="noopener">Data Retention Policy</a>.<span class="req">*</span></label>
              <div class="invalid-feedback"></div>
            </div>
          </div>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mt-4">
          <div>
            <span class="d-inline-block border border-2 border-danger rounded-3 px-2 small fw-bold text-danger mb-2">* Indicates required field</span><br>
            <span class="fw-bold text-terracotta small">Already have an account? <button type="button" class="btn-link-ll" data-switch="#loginModal">Login</button></span>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-ll-outline btn-lg" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-ll btn-lg" type="submit">Sign up</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php render_common_ui(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Google reCAPTCHA v3 Script -->
<script src="https://www.google.com/recaptcha/api.js?render=6LeC6dYtAAAAAECIlAtZGIffeHlx8gDNwLwYMlO_"></script>

<script src="/assets/js/api.js"></script>
<script src="/assets/js/landing.js?v=reset-otp"></script>
</body>
</html> 
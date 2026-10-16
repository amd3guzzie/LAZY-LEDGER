<?php declare(strict_types=1);
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/views/layout.php';
require_once dirname(__DIR__) . '/src/visitors.php';

send_security_headers();
log_visit();
render_head('About LazyLedger');
?>
<body class="landing">
<div class="container-xl py-3 py-md-4">
  <nav class="panel d-flex justify-content-between align-items-center px-3 px-md-4 py-3 mb-3" aria-label="Main">
    <a class="brand d-flex align-items-center gap-2 text-decoration-none" href="/">
      <img src="/assets/img/logo.png" style="height:36px;width:auto" alt="">LAZY LEDGER</a>
    <a class="nav-item-link" href="/">&larr; Back to home</a>
  </nav>

  <section class="panel p-4 p-md-5 mb-3" id="about">
    <h1 class="section-title mb-3">ABOUT US</h1>
    <p>LazyLedger is a personal budget tracker built by Group 6 for the ITS122P final project. It helps people track income and expenses across cash, bank and e-wallet accounts, set monthly budgets and see where their money goes.</p>
    <p class="mb-0"><strong>Mission:</strong> make money tracking so easy that anyone can keep doing it.</p>
  </section>

  <section class="panel p-4 p-md-5 mb-3" id="contact">
    <h2 class="section-title mb-3">CONTACT</h2>
    <p class="mb-1">Email: <a href="mailto:support@lazyledger.app">support@lazyledger.app</a></p>
    <p class="mb-0">You can also contact support by opening a ticket from your dashboard.</p>
  </section>

  <section class="panel p-4 p-md-5 mb-3" id="privacy">
    <h2 class="section-title mb-3">PRIVACY &amp; RETENTION</h2>
    <p>We use account details and financial records to provide your dashboard, budgets, notifications and support. Your transactions are visible only to you; support staff do not have access to them.</p>
    <p class="mb-0">You can permanently delete your account and financial records from Profile. Security and visitor logs may be retained to protect and operate the service. For the full Data Privacy Notice, see the notice shown during registration.</p>
  </section>

  <section class="panel p-4 p-md-5 mb-3" id="terms">
    <h2 class="section-title mb-3">TERMS OF USE</h2>
    <ol class="mb-0">
      <li>You must be at least 13 years old to register.</li>
      <li>Keep your password private. You are responsible for activity on your account.</li>
      <li>LazyLedger is a student project. It tracks your records but does not give financial advice.</li>
      <li>We may suspend accounts that misuse the service.</li>
    </ol>
  </section>

  <footer class="panel p-4 p-md-5">
    <?php render_quick_links(); ?>
  </footer>
</div>
</body>
</html>
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
    <p class="mb-0">You can permanently delete your account and financial records from Profile. For the full Data Privacy Notice, see the notice shown during registration.</p>
  </section>

  <section class="panel p-4 p-md-5 mb-3" id="retention">
    <h2 class="section-title mb-3">DATA RETENTION POLICY</h2>
    <p>This policy explains what data LazyLedger keeps, for how long, and how you can ask us to delete it.</p>
    <div class="table-responsive">
      <table class="table table-ll">
        <thead><tr><th>Data</th><th>How long we keep it</th></tr></thead>
        <tbody>
          <tr><td>Profile (name, email, birth date, gender, currency)</td><td>While your account is active. Deleted right away when you delete your account.</td></tr>
          <tr><td>Accounts, transactions, budgets and recurring bills</td><td>While your account is active. Deleted right away when you delete your account.</td></tr>
          <tr><td>Reviews, support tickets, category requests and notifications</td><td>While your account is active. Deleted together with your account.</td></tr>
          <tr><td>Visitor log (IP address, time, page, button clicked, browser)</td><td>12 months, then deleted automatically. When you delete your account, your name is removed from these rows.</td></tr>
          <tr><td>Email confirmation and password reset codes</td><td>Confirmation links expire after 24 hours and reset codes after 10 minutes. Old links are deleted after 30 days.</td></tr>
          <tr><td>Security activity log</td><td>Kept to protect the service and investigate misuse. It records what action was taken, never amounts or descriptions.</td></tr>
        </tbody>
      </table>
    </div>
    <h3 class="fs-5 fw-bold mt-3">How to request deletion</h3>
    <ol class="mb-0">
      <li><strong>Delete it yourself:</strong> log in, open <em>Profile</em> and choose <em>Delete account</em>. This erases your data immediately.</li>
      <li><strong>Ask us:</strong> email <a href="mailto:support@lazyledger.app">support@lazyledger.app</a> or open a support ticket. Tell us your account email (or your IP address for visitor logs).</li>
      <li><strong>Download first:</strong> you can export your transactions as CSV or PDF before deleting your account.</li>
    </ol>
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
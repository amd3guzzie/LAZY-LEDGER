<?php
declare(strict_types=1);

/** Shared <head> for every page. */
function render_head(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($title) ?></title>
  <link rel="icon" type="image/png" href="/assets/img/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bowlby+One&family=League+Spartan:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <link href="/assets/css/styles.css" rel="stylesheet">
</head>
    <?php
}

/**
 * Dashboard shell: header + main content slot + sidebar (static on desktop, offcanvas on mobile).
 * $nav: list of [view, label, bootstrap-icon].
 */
function render_shell_start(array $user, array $nav, string $roleLabel): void
{
    $name = trim($user['first_name'] . ' ' . $user['last_name']);
    ?>
<body class="app" data-user-id="<?= (int) $user['id'] ?>" data-currency="<?= e($user['currency'] ?? 'PHP') ?>" data-tour="<?= empty($user['tour_completed_at']) ? 'pending' : 'done' ?>">
<a class="visually-hidden-focusable" href="#main">Skip to content</a>
<div class="app-shell">
  <main class="app-main" id="main">
    <header class="app-header">
      <a class="brand d-flex align-items-center gap-2 text-decoration-none" href="#top">
      <img src="/assets/img/logo.png" style="height: 36px; width: auto;">LAZY LEDGER</a>
      <div class="d-flex align-items-center gap-2">
        <div id="headerActions" class="d-flex gap-2"></div>
        <button class="hamburger d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open menu">
          <img src="/assets/img/nav-icon-1.png" alt="">
        </button>
      </div>
    </header>
    <?php
    // content follows
    $GLOBALS['__shell'] = compact('user', 'nav', 'roleLabel', 'name');
}

function render_shell_end(array $scripts): void
{
    ['nav' => $nav, 'roleLabel' => $roleLabel, 'name' => $name] = $GLOBALS['__shell'];
    ?>
  </main>

  <aside class="sidebar offcanvas-lg offcanvas-end" tabindex="-1" id="sidebar" aria-label="Main navigation">
    <div class="d-flex justify-content-end d-lg-none">
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button>
    </div>
    <div class="sidebar-profile">
      <img class="avatar" src="/assets/img/profile-icon.png" alt="">
      <div class="sidebar-card mt-2">
        <div class="sidebar-name" id="sidebarName"><?= e($name) ?></div>
        <span class="role-badge"><?= e($roleLabel) ?></span>
      </div>
    </div>
    <nav class="sidebar-card">
      <ul class="side-nav">
        <?php foreach ($nav as [$view, $label, $icon]): ?>
          <li><a href="#<?= e($view) ?>" data-view-link="<?= e($view) ?>"><i class="bi <?= e($icon) ?>" aria-hidden="true"></i><?= e($label) ?><span class="badge rounded-pill text-bg-danger d-none" data-badge="<?= e($view) ?>"></span></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <button class="logout-btn mt-auto" type="button" id="logoutBtn"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Logout</button>
  </aside>
</div>

<?php render_common_ui(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php foreach ($scripts as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
    <?php
}

/** Toast area + reusable confirm dialog used by LL.confirm(). */
function render_common_ui(): void
{
    ?>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toasts" aria-live="polite"></div>
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title fs-5" id="confirmTitle">Are you sure?</h2></div>
      <div class="modal-body fw-bold" id="confirmBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ll-outline" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger fw-bold" id="confirmOk">Confirm</button>
      </div>
    </div>
  </div>
</div>
    <?php
}
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/views/layout.php';

$user = page_guard('admin');
render_head('Admin');
render_shell_start($user, [
    ['overview', 'Overview', 'bi-speedometer2'],
    ['users', 'Users & Roles', 'bi-people-fill'],
    ['categories', 'Global Categories', 'bi-tags-fill'],
    ['audit', 'Audit Log', 'bi-journal-text'],
], 'Admin');
?>

<section class="view" data-view="overview" aria-labelledby="ovTitle">
  <div class="content-panel">
    <h1 class="page-title mb-3" id="ovTitle">Overview</h1>
    <div class="row g-3 mb-3">
      <div class="col-6 col-xl-3"><div class="ll-card stat-card"><div class="stat-label">Total users</div><div class="stat-value" id="aTotal">—</div><div class="stat-sub" id="aTotalSub"></div></div></div>
      <div class="col-6 col-xl-3"><div class="ll-card stat-card"><div class="stat-label">Active this week</div><div class="stat-value" id="aActive">—</div><div class="stat-sub">logged in, last 7 days</div></div></div>
      <div class="col-6 col-xl-3"><div class="ll-card stat-card"><div class="stat-label">New this month</div><div class="stat-value" id="aNew">—</div><div class="stat-sub" id="aTx"></div></div></div>
      <div class="col-6 col-xl-3"><div class="ll-card stat-card"><div class="stat-label">Pending work</div><div class="stat-value" id="aPending">—</div><div class="stat-sub" id="aPendingSub"></div></div></div>
    </div>
    <div class="row g-3">
      <div class="col-lg-8"><div class="ll-card"><h2 class="ll-card-title mb-2">Signups &amp; transactions per month</h2><div class="chart-box"><canvas id="growthChart" role="img" aria-label="Signups and transactions per month"></canvas></div></div></div>
      <div class="col-lg-4"><div class="ll-card"><h2 class="ll-card-title mb-2">Users by role</h2><div class="chart-box"><canvas id="roleChart" role="img" aria-label="Users by role"></canvas></div></div></div>
      <div class="col-12"><h2 class="ll-card-title mt-2 mb-0">Customer demographics</h2><p class="privacy-note mb-0" id="demoSummary">From the details customers give at sign-up.</p></div>
      <div class="col-md-6 col-xl-4"><div class="ll-card"><h3 class="ll-card-title fs-6 mb-2">Gender</h3><div class="chart-box"><canvas id="genderChart" role="img" aria-label="Customers by gender"></canvas></div></div></div>
      <div class="col-md-6 col-xl-4"><div class="ll-card"><h3 class="ll-card-title fs-6 mb-2">Age group</h3><div class="chart-box"><canvas id="ageChart" role="img" aria-label="Customers by age group"></canvas></div></div></div>
      <div class="col-md-12 col-xl-4"><div class="ll-card"><h3 class="ll-card-title fs-6 mb-2">Currency</h3><div class="chart-box"><canvas id="currencyChart" role="img" aria-label="Customers by recording currency"></canvas></div></div></div>
      <div class="col-lg-5"><div class="ll-card"><h2 class="ll-card-title mb-2">Most-used categories</h2><div id="topCats"></div></div></div>
      <div class="col-lg-7"><div class="ll-card"><div class="d-flex justify-content-between"><h2 class="ll-card-title mb-2">Recent activity (all users)</h2><a class="see-all" href="#audit">See all</a></div>
        <div class="table-responsive"><table class="table table-ll table-sm"><tbody id="recentActivity"></tbody></table></div></div></div>
    </div>
  </div>
</section>

<section class="view" data-view="users" hidden aria-labelledby="usersTitle">
  <div class="content-panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title" id="usersTitle">Users &amp; Roles</h1>
      <button class="btn btn-ll" id="newUserBtn"><i class="bi bi-person-plus-fill"></i> Add staff / admin</button>
    </div>
    <form class="row g-2 mb-2" id="usersFilter" role="search">
      <div class="col-md-6"><label class="visually-hidden" for="uQ">Search</label><input class="form-control border-0" type="search" id="uQ" name="q" placeholder="Search by name or email"></div>
      <div class="col-6 col-md-3"><label class="visually-hidden" for="uRole">Role</label><select class="form-select border-0" id="uRole" name="role"><option value="">All roles</option><option value="customer">Customers</option><option value="staff">Staff</option><option value="admin">Admins</option></select></div>
      <div class="col-6 col-md-3"><label class="visually-hidden" for="uStatus">Status</label><select class="form-select border-0" id="uStatus" name="status"><option value="">All statuses</option><option value="active">Active</option><option value="suspended">Suspended</option></select></div>
    </form>
    <div class="ll-card">
      <div class="table-responsive"><table class="table table-ll align-middle">
        <thead><tr>
          <th class="sortable" data-sort="name">Name <span class="sort-ind"></span></th>
          <th class="sortable" data-sort="email">Email <span class="sort-ind"></span></th>
          <th>Role</th><th>Status</th>
          <th class="sortable" data-sort="last_login">Last login <span class="sort-ind"></span></th>
          <th class="sortable" data-sort="created">Joined <span class="sort-ind"></span></th>
          <th class="text-end">Actions</th>
        </tr></thead>
        <tbody id="usersBody"></tbody>
      </table></div>
      <div id="usersPager" class="mt-2"></div>
    </div>
  </div>
</section>

<section class="view" data-view="categories" hidden aria-labelledby="catTitle">
  <div class="content-panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title" id="catTitle">Global Categories</h1>
      <button class="btn btn-ll" id="newCatBtn"><i class="bi bi-plus-lg"></i> New category</button>
    </div>
    <p class="privacy-note">Global categories are available to every customer. Deleting one keeps customers' transactions but marks them “Uncategorized”.</p>
    <div class="ll-card">
      <div class="table-responsive"><table class="table table-ll align-middle">
        <thead><tr><th>Name</th><th>Type</th><th>Transactions</th><th>Customers using</th><th class="text-end">Actions</th></tr></thead>
        <tbody id="catBody"></tbody>
      </table></div>
    </div>
  </div>
</section>

<section class="view" data-view="audit" hidden aria-labelledby="auditTitle">
  <div class="content-panel">
    <h1 class="page-title mb-3" id="auditTitle">Audit Log</h1>
    <p class="privacy-note">Append-only record of every customer, staff and admin action (sign-ups, logins, failed logins, and every create / update / delete). Entries cannot be edited or deleted.</p>
    <form class="row g-2 mb-2" id="auditFilter" role="search">
      <div class="col-md-4"><label class="visually-hidden" for="aQ">Search</label><input class="form-control border-0" type="search" id="aQ" name="q" placeholder="Search action, details or email"></div>
      <div class="col-6 col-md-2"><label class="visually-hidden" for="aRole">Role</label><select class="form-select border-0" id="aRole" name="role"><option value="">All roles</option><option value="customer">Customers</option><option value="staff">Staff</option><option value="admin">Admins</option></select></div>
      <div class="col-6 col-md-2"><label class="visually-hidden" for="aCategory">Action type</label><select class="form-select border-0" id="aCategory" name="category">
        <option value="">All actions</option><option value="auth">Login / logout</option><option value="profile">Profile</option><option value="transaction">Transactions</option><option value="recurring">Recurring bills</option>
        <option value="account">Accounts</option><option value="budget">Budgets</option><option value="category_request">Category requests</option>
        <option value="ticket">Support tickets</option><option value="user">User management</option><option value="category">Global categories</option></select></div>
      <div class="col-6 col-md-2"><label class="form-label small visually-hidden" for="aFrom">From</label><input class="form-control border-0" type="date" id="aFrom" name="from" aria-label="From date"></div>
      <div class="col-6 col-md-2"><label class="form-label small visually-hidden" for="aTo">To</label><input class="form-control border-0" type="date" id="aTo" name="to" aria-label="To date"></div>
    </form>
    <div class="ll-card">
      <div class="table-responsive"><table class="table table-ll table-sm">
        <thead><tr><th>Time</th><th>Who</th><th>Action</th><th>Target</th><th>Details</th></tr></thead>
        <tbody id="auditBody"></tbody>
      </table></div>
      <div id="auditPager" class="mt-2"></div>
    </div>
  </div>
</section>

<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <form id="userForm" novalidate>
      <div class="modal-header"><h2 class="modal-title fs-4" id="userModalTitle">Add team member</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body row g-2">
        <div class="alert alert-danger py-2 form-error" hidden></div>
        <div class="col-6"><label class="form-label" for="nuFirst">First name<span class="req">*</span></label><input class="form-control" id="nuFirst" name="first_name" maxlength="60" required></div>
        <div class="col-6"><label class="form-label" for="nuLast">Last name</label><input class="form-control" id="nuLast" name="last_name" maxlength="60"></div>
        <div class="col-12"><label class="form-label" for="nuEmail">Email<span class="req">*</span></label><input class="form-control" type="email" id="nuEmail" name="email" maxlength="190" required></div>
        <div class="col-6"><label class="form-label" for="nuRole">Role<span class="req">*</span></label><select class="form-select" id="nuRole" name="role"><option value="staff">Staff</option><option value="admin">Admin</option><option value="customer">Customer</option></select></div>
        <div class="col-6"><label class="form-label" for="nuPassword">Temporary password<span class="req">*</span></label><input class="form-control" type="password" id="nuPassword" name="password" autocomplete="new-password" required></div>
        <div class="col-12 form-text">Share the temporary password privately; they can change it from their profile.</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-ll-outline" data-bs-dismiss="modal">Cancel</button><button class="btn btn-ll" type="submit">Create</button></div>
    </form>
  </div></div>
</div>

<div class="modal fade" id="catModal" tabindex="-1" aria-labelledby="catModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
    <form id="catForm" novalidate>
      <div class="modal-header"><h2 class="modal-title fs-5" id="catModalTitle">New category</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="alert alert-danger py-2 form-error" hidden></div>
        <input type="hidden" name="id">
        <div class="mb-2"><label class="form-label" for="catName">Name<span class="req">*</span></label><input class="form-control" id="catName" name="name" maxlength="60" required></div>
        <div class="mb-2"><label class="form-label" for="catType">Type<span class="req">*</span></label><select class="form-select" id="catType" name="type"><option value="expense">Expense</option><option value="income">Income</option></select></div>
      </div>
      <div class="modal-footer"><button class="btn btn-ll w-100" type="submit">Save</button></div>
    </form>
  </div></div>
</div>

<?php render_shell_end([
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
    '/assets/js/api.js',
    '/assets/js/admin.js',
]); ?>

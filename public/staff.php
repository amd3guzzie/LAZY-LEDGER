<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/views/layout.php';

$user = page_guard('staff');
render_head('Staff');
render_shell_start($user, [
    ['overview', 'Overview', 'bi-speedometer2'],
    ['requests', 'Category Requests', 'bi-tags-fill'],
    ['tickets', 'Support Tickets', 'bi-life-preserver'],
    ['users', 'Customers', 'bi-people-fill'],
], 'Staff');
?>

<section class="view" data-view="overview" aria-labelledby="ovTitle">
  <div class="content-panel">
    <h1 class="page-title mb-3" id="ovTitle">Overview</h1>
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3"><div class="ll-card stat-card"><div class="stat-label">Pending requests</div><div class="stat-value" id="sPendingReq">—</div></div></div>
      <div class="col-6 col-lg-3"><div class="ll-card stat-card"><div class="stat-label">Open tickets</div><div class="stat-value" id="sOpenTix">—</div></div></div>
      <div class="col-6 col-lg-3"><div class="ll-card stat-card"><div class="stat-label">Resolved tickets</div><div class="stat-value" id="sResolved">—</div></div></div>
      <div class="col-6 col-lg-3"><div class="ll-card stat-card"><div class="stat-label">Customers</div><div class="stat-value" id="sCustomers">—</div></div></div>
    </div>
    <div class="row g-3">
      <div class="col-lg-5">
        <div class="ll-card"><h2 class="ll-card-title mb-2">Request status</h2><div class="chart-box"><canvas id="reqChart" role="img" aria-label="Category requests by status"></canvas></div></div>
      </div>
      <div class="col-lg-7">
        <div class="ll-card"><h2 class="ll-card-title mb-2">Recent activity</h2>
          <div class="table-responsive"><table class="table table-ll table-sm"><thead><tr><th>Type</th><th>Title</th><th>Customer</th><th>Status</th></tr></thead><tbody id="recentBody"></tbody></table></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="view" data-view="requests" hidden aria-labelledby="reqTitle">
  <div class="content-panel">
    <h1 class="page-title mb-3" id="reqTitle">Category Requests</h1>
    <form class="row g-2 mb-2" id="reqFilter" role="search">
      <div class="col-md-8"><label class="visually-hidden" for="reqQ">Search</label><input class="form-control border-0" type="search" id="reqQ" name="q" placeholder="Search by category or customer"></div>
      <div class="col-md-4"><label class="visually-hidden" for="reqStatus">Status</label>
        <select class="form-select border-0" id="reqStatus" name="status"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="">All statuses</option></select></div>
    </form>
    <div class="ll-card">
      <div class="table-responsive"><table class="table table-ll align-middle">
        <thead><tr><th>Requested</th><th>Category</th><th>Type</th><th>Customer</th><th>Reason</th><th>Status</th><th class="text-end">Action</th></tr></thead>
        <tbody id="reqBody"></tbody>
      </table></div>
      <div id="reqPager" class="mt-2"></div>
    </div>
  </div>
</section>

<section class="view" data-view="tickets" hidden aria-labelledby="tixTitle">
  <div class="content-panel">
    <h1 class="page-title mb-3" id="tixTitle">Support Tickets</h1>
    <form class="row g-2 mb-2" id="tixFilter" role="search">
      <div class="col-md-8"><label class="visually-hidden" for="tixQ">Search</label><input class="form-control border-0" type="search" id="tixQ" name="q" placeholder="Search subject, message or email"></div>
      <div class="col-md-4"><label class="visually-hidden" for="tixStatus">Status</label>
        <select class="form-select border-0" id="tixStatus" name="status"><option value="open">Open (pending + in progress)</option><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="resolved">Resolved</option><option value="">All</option></select></div>
    </form>
    <div class="ll-card">
      <div class="table-responsive"><table class="table table-ll align-middle">
        <thead><tr><th>Opened</th><th>Subject</th><th>Customer</th><th>Status</th><th>Handled by</th><th class="text-end">Action</th></tr></thead>
        <tbody id="tixBody"></tbody>
      </table></div>
      <div id="tixPager" class="mt-2"></div>
    </div>
  </div>
</section>

<section class="view" data-view="users" hidden aria-labelledby="usersTitle">
  <div class="content-panel">
    <h1 class="page-title mb-3" id="usersTitle">Customers</h1>
    <p class="privacy-note"><i class="bi bi-shield-lock-fill"></i> For privacy, staff can see account status only. Customer balances and transactions are intentionally hidden.</p>
    <form class="row g-2 mb-2" id="usersFilter" role="search">
      <div class="col-md-8"><label class="visually-hidden" for="usersQ">Search</label><input class="form-control border-0" type="search" id="usersQ" name="q" placeholder="Search by name or email"></div>
      <div class="col-md-4"><label class="visually-hidden" for="usersStatus">Status</label>
        <select class="form-select border-0" id="usersStatus" name="status"><option value="">All statuses</option><option value="active">Active</option><option value="suspended">Suspended</option></select></div>
    </form>
    <div class="ll-card">
      <div class="table-responsive"><table class="table table-ll">
        <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Open tickets</th><th>Last login</th><th>Joined</th></tr></thead>
        <tbody id="usersBody"></tbody>
      </table></div>
      <div id="usersPager" class="mt-2"></div>
    </div>
  </div>
</section>

<div class="modal fade" id="ticketModal" tabindex="-1" aria-labelledby="ticketModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
    <form id="ticketForm" novalidate>
      <div class="modal-header"><h2 class="modal-title fs-4" id="ticketModalTitle">Ticket</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="alert alert-danger py-2 form-error" hidden></div>
        <input type="hidden" name="id">
        <p class="small fw-bold text-muted-ll mb-1" id="ticketMeta"></p>
        <div class="ll-card mb-3" style="white-space:pre-wrap" id="ticketMessage"></div>
        <div class="mb-2"><label class="form-label" for="ticketReply">Reply to customer</label><textarea class="form-control" id="ticketReply" name="staff_reply" rows="4" maxlength="2000"></textarea></div>
        <div class="mb-2"><label class="form-label" for="ticketStatus">Status</label>
          <select class="form-select" id="ticketStatus" name="status"><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="resolved">Resolved</option></select></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-ll-outline" data-bs-dismiss="modal">Cancel</button><button class="btn btn-ll" type="submit">Save &amp; notify customer</button></div>
    </form>
  </div></div>
</div>

<?php render_shell_end([
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
    '/assets/js/api.js',
    '/assets/js/staff.js',
]); ?>

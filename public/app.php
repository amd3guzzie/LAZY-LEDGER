<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/views/layout.php';

$user = page_guard('customer');
render_head('Dashboard');
render_shell_start($user, [
    ['home', 'Home', 'bi-house-door-fill'],
    ['accounts', 'Accounts', 'bi-bank2'],
    ['transactions', 'Transactions', 'bi-list-ul'],
    ['budgets', 'Budgets', 'bi-wallet-fill'],
    ['stats', 'Stats', 'bi-pie-chart-fill'],
    ['profile', 'Profile', 'bi-person-fill'],
], 'Customer');
?>

<!-- ============ HOME ============ -->
<section class="view" data-view="home" aria-labelledby="homeTitle">
  <!-- Onboarding (shown until the user has an account, a budget and a transaction) -->
  <div id="onboarding" hidden>
    <div class="row g-3">
      <div class="col-lg-8">
        <div class="content-panel h-100">
          <h1 class="page-title" style="font-size:clamp(2rem,6vw,3.6rem)">Welcome, <span id="welcomeName"><?= e($user['first_name']) ?></span>!</h1>
          <p class="fw-bold text-muted-ll fs-5">Three quick steps and you'll see where your money goes.</p>
          <div class="ll-card">
            <div class="onboard-step">
              <div class="step-num done"><i class="bi bi-check-lg"></i></div>
              <div class="flex-grow-1"><p class="step-title">Create your LazyLedger account</p></div>
              <span class="fw-800" style="color:var(--ll-green-bar)">DONE</span>
            </div>
            <div class="onboard-step" data-step="accounts">
              <div class="step-num">2</div>
              <div class="flex-grow-1"><p class="step-title">Add a money account</p><p class="step-sub">Cash, bank, e-wallet or credit card.</p></div>
              <button class="btn btn-ll btn-sm" data-action="add-account">Add account</button>
            </div>
            <div class="onboard-step" data-step="budgets">
              <div class="step-num">3</div>
              <div class="flex-grow-1"><p class="step-title">Set your monthly budget</p><p class="step-sub">Pick a total, then split it by category.</p></div>
              <a class="btn btn-ll btn-sm" href="#budgets">Set budget</a>
            </div>
            <div class="onboard-step" data-step="transactions">
              <div class="step-num">4</div>
              <div class="flex-grow-1"><p class="step-title">Add your first transaction</p><p class="step-sub">It takes about ten seconds.</p></div>
              <button class="btn btn-ll btn-sm" data-action="add-transaction">Add transaction</button>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4 d-flex flex-column gap-3">
        <div class="ll-card empty-hint"><img src="/assets/img/new-user-section-1.png" alt=""><h3>Plan Your Spending</h3><p class="mb-0">Your budgets will show up here with progress bars.</p></div>
        <div class="ll-card empty-hint"><img src="/assets/img/new-user-section-2.png" alt=""><h3 style="color:var(--ll-steel)">Track Your Purchase</h3><p class="mb-0">Recent transactions and your spending chart will appear here.</p></div>
      </div>
    </div>
  </div>

  <div id="homeMain">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title" id="homeTitle">Home</h1>
      <div class="d-flex align-items-center gap-2">
        <div class="btn-group" role="group" aria-label="Month">
          <button class="btn btn-ll-outline btn-sm" id="prevMonth" aria-label="Previous month"><i class="bi bi-chevron-left"></i></button>
          <span class="btn btn-ll-outline btn-sm disabled fw-800" id="monthLabel" style="min-width:9.5rem"></span>
          <button class="btn btn-ll-outline btn-sm" id="nextMonth" aria-label="Next month"><i class="bi bi-chevron-right"></i></button>
        </div>
        <button class="btn btn-ll-outline btn-sm" id="currencyBtn" title="Uses live rates from the Frankfurter API">View in USD</button>
      </div>
    </div>
    <p class="small fw-bold text-muted-ll mb-2" id="rateNote" hidden></p>

    <div class="content-panel mb-3">
      <div class="row g-3">
        <div class="col-md-4"><div class="ll-card stat-card"><div class="stat-label">Left to spend</div><div class="stat-value" id="leftToSpend">—</div><div class="stat-sub" id="pctUsed"></div></div></div>
        <div class="col-md-4"><div class="ll-card stat-card"><div class="stat-label">Income</div><div class="stat-value" id="incomeTotal">—</div><div class="stat-sub" id="incomeChange"></div></div></div>
        <div class="col-md-4"><div class="ll-card stat-card"><div class="stat-label">Expenses</div><div class="stat-value" id="expenseTotal">—</div><div class="stat-sub" id="expenseChange"></div></div></div>
      </div>
    </div>

    <div id="alertBox"></div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="ll-card">
          <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="ll-card-title">Budgets</h2><a class="see-all" href="#budgets">See all</a></div>
          <div id="homeBudgets"></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="ll-card">
          <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="ll-card-title">Recent transactions</h2><a class="see-all" href="#transactions">See all</a></div>
          <div class="table-responsive">
            <table class="table table-ll table-borderless">
              <thead><tr><th>Item</th><th class="text-end">Amount</th></tr></thead>
              <tbody id="homeRecent"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="ll-card">
          <h2 class="ll-card-title mb-2">Spending breakdown</h2>
          <div class="chart-box"><canvas id="donutChart" aria-label="Spending by category this month" role="img"></canvas></div>
          <p class="empty-hint mb-0" id="donutEmpty" hidden>No expenses this month yet.</p>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="ll-card">
          <h2 class="ll-card-title mb-2">Coming up</h2>
          <ul class="list-unstyled mb-0" id="homeUpcoming"></ul>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ ACCOUNTS ============ -->
<section class="view" data-view="accounts" hidden aria-labelledby="accountsTitle">
  <div class="content-panel">
    <h1 class="page-title mb-3" id="accountsTitle">Accounts</h1>
    <div class="row g-3">
      <div class="col-md-8 col-lg-9">
        <div class="ll-card d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div><div class="stat-label fs-3">Net worth</div><div class="stat-sub" id="accountsCount"></div></div>
          <div class="stat-value" style="font-size:clamp(2.2rem,6vw,4rem)" id="netWorth">—</div>
        </div>
      </div>
      <div class="col-md-4 col-lg-3">
        <button class="add-tile" data-action="add-account"><span><i class="bi bi-plus-lg"></i>Add account</span></button>
      </div>
    </div>
    <hr class="rule">
    <div class="row g-3" id="accountsGrid"></div>
  </div>
</section>

<!-- ============ TRANSACTIONS ============ -->
<section class="view" data-view="transactions" hidden aria-labelledby="txTitle">
  <div class="content-panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title" id="txTitle">Transactions</h1>
      <div class="d-flex gap-2">
        <button class="btn btn-ll-outline" id="exportCsv"><i class="bi bi-download"></i> Export CSV</button>
        <button class="btn btn-ll" data-action="add-transaction"><i class="bi bi-plus-lg"></i> Add transaction</button>
      </div>
    </div>
    <form class="d-flex gap-2 mb-2" id="txSearchForm" role="search">
      <label class="visually-hidden" for="txSearch">Search transactions</label>
      <input class="form-control border-0" type="search" id="txSearch" name="q" placeholder="Search by item, category or account" maxlength="100">
      <button class="btn btn-light" type="submit" aria-label="Search"><i class="bi bi-search text-muted-ll"></i></button>
      <button class="btn btn-light fw-800 text-muted-ll" type="button" data-bs-toggle="collapse" data-bs-target="#txFilters" aria-expanded="false" aria-controls="txFilters"><i class="bi bi-funnel-fill"></i> FILTER</button>
    </form>
    <div class="collapse" id="txFilters">
      <form class="ll-card mb-2 row g-2 mx-0" id="txFilterForm">
        <div class="col-6 col-md-2"><label class="form-label small" for="fType">Type</label>
          <select class="form-select form-select-sm" id="fType" name="type"><option value="">All</option><option value="expense">Expenses</option><option value="income">Income</option></select></div>
        <div class="col-6 col-md-3"><label class="form-label small" for="fCategory">Category</label>
          <select class="form-select form-select-sm" id="fCategory" name="category_id"><option value="">All</option></select></div>
        <div class="col-6 col-md-3"><label class="form-label small" for="fAccount">Account</label>
          <select class="form-select form-select-sm" id="fAccount" name="account_id"><option value="">All</option></select></div>
        <div class="col-6 col-md-2"><label class="form-label small" for="fFrom">From</label><input class="form-control form-control-sm" type="date" id="fFrom" name="from"></div>
        <div class="col-6 col-md-2"><label class="form-label small" for="fTo">To</label><input class="form-control form-control-sm" type="date" id="fTo" name="to"></div>
        <div class="col-6 col-md-12 d-flex align-items-end justify-content-end gap-2">
          <button class="btn btn-sm btn-ll-outline" type="reset">Clear</button>
        </div>
      </form>
    </div>
    <div class="ll-card">
      <div class="table-responsive">
        <table class="table table-ll table-hover">
          <thead><tr>
            <th class="sortable" data-sort="date" scope="col">Date <span class="sort-ind"></span></th>
            <th class="sortable" data-sort="description" scope="col">Item <span class="sort-ind"></span></th>
            <th class="sortable" data-sort="category" scope="col">Category <span class="sort-ind"></span></th>
            <th class="sortable" data-sort="account" scope="col">Account <span class="sort-ind"></span></th>
            <th class="sortable text-end" data-sort="amount" scope="col">Amount <span class="sort-ind"></span></th>
            <th scope="col"><span class="visually-hidden">Actions</span></th>
          </tr></thead>
          <tbody id="txBody"></tbody>
        </table>
      </div>
      <div id="txPager" class="mt-2"></div>
    </div>
  </div>
</section>

<!-- ============ BUDGETS ============ -->
<section class="view" data-view="budgets" hidden aria-labelledby="budgetsTitle">
  <div class="content-panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title" id="budgetsTitle">Budgets</h1>
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-ll-outline" data-action="request-category"><i class="bi bi-tag"></i> Request category</button>
        <button class="btn btn-ll" data-action="add-budget"><i class="bi bi-plus-lg"></i> New budget</button>
      </div>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-md-4"><div class="ll-card stat-card"><div class="stat-label">Budgeted <button class="btn-icon" data-action="set-total" aria-label="Edit monthly budget"><i class="bi bi-pencil-fill"></i></button></div><div class="stat-value" id="bBudgeted">—</div><div class="stat-sub" id="bBudgetedSub"></div></div></div>
      <div class="col-md-4"><div class="ll-card stat-card"><div class="stat-label">Spent</div><div class="stat-value" id="bSpent">—</div><div class="stat-sub" id="bPct"></div></div></div>
      <div class="col-md-4"><div class="ll-card stat-card"><div class="stat-label">Unassigned</div><div class="stat-value" id="bUnassigned">—</div><div class="stat-sub">Budgeted − category budgets</div></div></div>
    </div>
    <div class="ll-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="ll-card-title">Monthly budget · <span id="budgetMonth"></span></h2>
      </div>
      <div class="row gx-5" id="budgetList"></div>
    </div>
    <div class="ll-card">
      <h2 class="ll-card-title mb-2">My category requests</h2>
      <div class="table-responsive"><table class="table table-ll table-sm">
        <thead><tr><th>Name</th><th>Type</th><th>Status</th><th>Requested</th></tr></thead>
        <tbody id="requestList"></tbody>
      </table></div>
    </div>
  </div>
</section>

<!-- ============ STATS ============ -->
<section class="view" data-view="stats" hidden aria-labelledby="statsTitle">
  <div class="content-panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title" id="statsTitle">Stats</h1>
      <div class="d-flex flex-wrap gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="View" id="statsView">
          <button class="btn btn-ll-outline active" data-v="expense">Spending</button>
          <button class="btn btn-ll-outline" data-v="income">Income</button>
          <button class="btn btn-ll-outline" data-v="net">Net</button>
        </div>
        <div class="btn-group btn-group-sm" role="group" aria-label="Range" id="statsRange">
          <button class="btn btn-ll-outline" data-m="3">3M</button>
          <button class="btn btn-ll-outline active" data-m="6">6M</button>
          <button class="btn btn-ll-outline" data-m="12">12M</button>
        </div>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="ll-card">
          <h2 class="ll-card-title mb-2" id="barTitle">Spending by month</h2>
          <div class="chart-box" style="height:320px"><canvas id="barChart" role="img" aria-label="Monthly totals chart"></canvas></div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="ll-card">
          <h2 class="ll-card-title mb-3">Top categories <small class="text-muted-ll fs-6">(this month)</small></h2>
          <div id="topCategories"></div>
        </div>
      </div>
      <div class="col-12">
        <div class="ll-card"><h2 class="ll-card-title mb-2">Insights</h2><ul class="mb-0 fw-bold text-muted-ll" id="insights"></ul></div>
      </div>
    </div>
  </div>
</section>

<!-- ============ PROFILE ============ -->
<section class="view" data-view="profile" hidden aria-labelledby="profileTitle">
  <div class="content-panel">
    <h1 class="page-title mb-3" id="profileTitle">Profile</h1>
    <div class="row g-3">
      <div class="col-lg-8">
        <div class="ll-card">
          <h2 class="ll-card-title mb-3">Basic information</h2>
          <form id="profileForm" class="row g-3" novalidate>
            <div class="col-md-6"><label class="form-label" for="pFirst">First name</label><input class="form-control" id="pFirst" name="first_name" maxlength="60" required></div>
            <div class="col-md-6"><label class="form-label" for="pLast">Last name</label><input class="form-control" id="pLast" name="last_name" maxlength="60"></div>
            <div class="col-12"><label class="form-label" for="pEmail">Email address</label><input class="form-control" type="email" id="pEmail" name="email" maxlength="190" required></div>
            <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
              <button type="button" class="btn-link-ll" data-bs-toggle="modal" data-bs-target="#passwordModal">Change password</button>
              <button class="btn btn-ll" type="submit">Save changes</button>
            </div>
          </form>
        </div>
      </div>
      <div class="col-lg-4 d-flex flex-column gap-3">
        <div class="ll-card">
          <h2 class="ll-card-title mb-2">Notifications</h2>
          <div class="form-check form-switch d-flex justify-content-between ps-0 mb-2">
            <label class="form-check-label fw-800 text-terracotta" for="prefAlerts">Budget alerts</label>
            <input class="form-check-input ms-0" type="checkbox" role="switch" id="prefAlerts" data-pref="budget_alerts">
          </div>
          <div class="form-check form-switch d-flex justify-content-between ps-0">
            <label class="form-check-label fw-800 text-terracotta" for="prefBills">Bill reminders</label>
            <input class="form-check-input ms-0" type="checkbox" role="switch" id="prefBills" data-pref="bill_reminders">
          </div>
        </div>
        <div class="ll-card">
          <h2 class="ll-card-title mb-2">Your data</h2>
          <button class="btn-link-ll d-block mb-1" id="exportAll">Export all transactions (CSV)</button>
          <button class="btn-link-ll text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">Delete account</button>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="ll-card">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="ll-card-title">Inbox</h2>
            <button class="see-all btn btn-link p-0" id="markAllRead">Mark all read</button>
          </div>
          <div id="notifList"></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="ll-card">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="ll-card-title">Support tickets</h2>
            <button class="btn btn-ll btn-sm" data-action="new-ticket"><i class="bi bi-plus-lg"></i> New ticket</button>
          </div>
          <div id="ticketList"></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ MODALS ============ -->
<div class="modal fade" id="txModal" tabindex="-1" aria-labelledby="txModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <form id="txForm" novalidate>
      <div class="modal-header"><h2 class="modal-title fs-4" id="txModalTitle">Add transaction</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="alert alert-danger py-2 form-error" hidden></div>
        <input type="hidden" name="id">
        <div class="btn-group w-100 mb-3" role="radiogroup" aria-label="Type">
          <input type="radio" class="btn-check" name="type" id="txTypeExpense" value="expense" checked><label class="btn btn-ll-outline" for="txTypeExpense">Expense</label>
          <input type="radio" class="btn-check" name="type" id="txTypeIncome" value="income"><label class="btn btn-ll-outline" for="txTypeIncome">Income</label>
        </div>
        <div class="mb-2"><label class="form-label" for="txDesc">Description<span class="req">*</span></label><input class="form-control" id="txDesc" name="description" maxlength="120" required placeholder="e.g. SM Supermarket"></div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label" for="txAmount">Amount (₱)<span class="req">*</span></label><input class="form-control" type="number" id="txAmount" name="amount" min="0.01" step="0.01" required inputmode="decimal"></div>
          <div class="col-6"><label class="form-label" for="txDate">Date<span class="req">*</span></label><input class="form-control" type="date" id="txDate" name="transaction_date" required></div>
        </div>
        <div class="row g-2">
          <div class="col-6"><label class="form-label" for="txAccount">Account<span class="req">*</span></label><select class="form-select" id="txAccount" name="account_id" required></select></div>
          <div class="col-6"><label class="form-label" for="txCategory">Category</label><select class="form-select" id="txCategory" name="category_id"></select></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-ll-outline" data-bs-dismiss="modal">Cancel</button><button class="btn btn-ll" type="submit">Save</button></div>
    </form>
  </div></div>
</div>

<div class="modal fade" id="accountModal" tabindex="-1" aria-labelledby="accountModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <form id="accountForm" novalidate>
      <div class="modal-header"><h2 class="modal-title fs-4" id="accountModalTitle">Add account</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="alert alert-danger py-2 form-error" hidden></div>
        <input type="hidden" name="id">
        <div class="mb-2"><label class="form-label" for="accName">Account name<span class="req">*</span></label><input class="form-control" id="accName" name="name" maxlength="80" required placeholder="e.g. BPI Savings, GCash"></div>
        <div class="mb-2"><label class="form-label" for="accType">Type<span class="req">*</span></label>
          <select class="form-select" id="accType" name="type" required>
            <option value="cash">Cash</option><option value="bank">Bank</option><option value="e_wallet">E-wallet</option><option value="savings">Savings</option><option value="credit_card">Credit card</option>
          </select></div>
        <div class="mb-2"><label class="form-label" for="accOpening" id="accOpeningLabel">Starting balance (₱)</label><input class="form-control" type="number" id="accOpening" name="opening_balance" min="0" step="0.01" value="0" inputmode="decimal"></div>
        <div class="mb-2" id="accDueWrap" hidden><label class="form-label" for="accDue">Payment due date</label><input class="form-control" type="date" id="accDue" name="due_date"></div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-danger fw-bold" id="accDelete" hidden>Delete</button>
        <div class="d-flex gap-2 ms-auto"><button type="button" class="btn btn-ll-outline" data-bs-dismiss="modal">Cancel</button><button class="btn btn-ll" type="submit">Save</button></div>
      </div>
    </form>
  </div></div>
</div>

<div class="modal fade" id="budgetModal" tabindex="-1" aria-labelledby="budgetModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <form id="budgetForm" novalidate>
      <div class="modal-header"><h2 class="modal-title fs-4" id="budgetModalTitle">New budget</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="alert alert-danger py-2 form-error" hidden></div>
        <input type="hidden" name="id">
        <div class="mb-2"><label class="form-label" for="bCategory">Category<span class="req">*</span></label><select class="form-select" id="bCategory" name="category_id" required></select></div>
        <div class="mb-2"><label class="form-label" for="bLimit">Monthly limit (₱)<span class="req">*</span></label><input class="form-control" type="number" id="bLimit" name="amount_limit" min="0.01" step="0.01" required inputmode="decimal"></div>
        <p class="small fw-bold text-muted-ll mb-0">Applies to <span id="bMonthLabel"></span>.</p>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-danger fw-bold" id="budgetDelete" hidden>Delete</button>
        <div class="d-flex gap-2 ms-auto"><button type="button" class="btn btn-ll-outline" data-bs-dismiss="modal">Cancel</button><button class="btn btn-ll" type="submit">Save</button></div>
      </div>
    </form>
  </div></div>
</div>

<div class="modal fade" id="totalModal" tabindex="-1" aria-labelledby="totalModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
    <form id="totalForm" novalidate>
      <div class="modal-header"><h2 class="modal-title fs-5" id="totalModalTitle">Total monthly budget</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="alert alert-danger py-2 form-error" hidden></div>
        <label class="form-label" for="totalAmount">Amount (₱)</label>
        <input class="form-control" type="number" id="totalAmount" name="monthly_budget" min="0.01" step="0.01" inputmode="decimal">
        <p class="small text-muted-ll fw-bold mt-2 mb-0">Leave blank to use the sum of your category budgets.</p>
      </div>
      <div class="modal-footer"><button class="btn btn-ll w-100" type="submit">Save</button></div>
    </form>
  </div></div>
</div>

<div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <form id="requestForm" novalidate>
      <div class="modal-header"><h2 class="modal-title fs-4" id="requestModalTitle">Request a new category</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="alert alert-danger py-2 form-error" hidden></div>
        <p class="small fw-bold text-muted-ll">Our staff reviews requests, usually within a day. You'll get a notification when it's approved.</p>
        <div class="mb-2"><label class="form-label" for="rName">Category name<span class="req">*</span></label><input class="form-control" id="rName" name="requested_name" maxlength="60" required></div>
        <div class="mb-2"><label class="form-label" for="rType">Type<span class="req">*</span></label><select class="form-select" id="rType" name="requested_type"><option value="expense">Expense</option><option value="income">Income</option></select></div>
        <div class="mb-2"><label class="form-label" for="rReason">Why do you need it?</label><textarea class="form-control" id="rReason" name="reason" maxlength="255" rows="2"></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-ll-outline" data-bs-dismiss="modal">Cancel</button><button class="btn btn-ll" type="submit">Submit request</button></div>
    </form>
  </div></div>
</div>

<div class="modal fade" id="ticketModal" tabindex="-1" aria-labelledby="ticketModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <form id="ticketForm" novalidate>
      <div class="modal-header"><h2 class="modal-title fs-4" id="ticketModalTitle">Contact support</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="alert alert-danger py-2 form-error" hidden></div>
        <div class="mb-2"><label class="form-label" for="tSubject">Subject<span class="req">*</span></label><input class="form-control" id="tSubject" name="subject" maxlength="120" required></div>
        <div class="mb-2"><label class="form-label" for="tMessage">Message<span class="req">*</span></label><textarea class="form-control" id="tMessage" name="message" rows="4" maxlength="2000" required></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-ll-outline" data-bs-dismiss="modal">Cancel</button><button class="btn btn-ll" type="submit">Send</button></div>
    </form>
  </div></div>
</div>

<div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <form id="passwordForm" novalidate>
      <div class="modal-header"><h2 class="modal-title fs-4" id="passwordModalTitle">Change password</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="alert alert-danger py-2 form-error" hidden></div>
        <div class="mb-2"><label class="form-label" for="pwCurrent">Current password</label><input class="form-control" type="password" id="pwCurrent" name="current_password" autocomplete="current-password" required></div>
        <div class="mb-2"><label class="form-label" for="pwNew">New password</label><input class="form-control" type="password" id="pwNew" name="new_password" autocomplete="new-password" required><div class="form-text">At least 8 characters, with a letter and a number.</div></div>
        <div class="mb-2"><label class="form-label" for="pwConfirm">Confirm new password</label><input class="form-control" type="password" id="pwConfirm" name="confirm_password" autocomplete="new-password" required></div>
      </div>
      <div class="modal-footer"><button class="btn btn-ll w-100" type="submit">Update password</button></div>
    </form>
  </div></div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <form id="deleteForm" novalidate>
      <div class="modal-header"><h2 class="modal-title fs-4 text-danger" id="deleteModalTitle">Delete your account?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="alert alert-danger py-2 form-error" hidden></div>
        <p class="fw-bold">This permanently deletes your accounts, transactions, budgets and requests. It cannot be undone.</p>
        <label class="form-label" for="delPassword">Enter your password to confirm</label>
        <input class="form-control" type="password" id="delPassword" name="password" autocomplete="current-password" required>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-ll-outline" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger fw-bold" type="submit">Delete permanently</button></div>
    </form>
  </div></div>
</div>

<?php render_shell_end([
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
    '/assets/js/api.js',
    '/assets/js/customer.js',
]); ?>

-- LazyLedger schema (MySQL 8+, InnoDB, utf8mb4)
-- Idempotent: safe to run on every boot.

CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(60)  NOT NULL,
    last_name       VARCHAR(60)  NOT NULL DEFAULT '',
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
    status          ENUM('active','suspended')       NOT NULL DEFAULT 'active',
    birth_date      DATE NULL,
    gender          ENUM('male','female','non_binary','prefer_not_to_say') NULL,
    currency        CHAR(3) NOT NULL DEFAULT 'PHP',  -- every amount this user stores is in this currency
    monthly_budget  DECIMAL(12,2) NULL,
    budget_alerts   TINYINT(1) NOT NULL DEFAULT 1,
    bill_reminders  TINYINT(1) NOT NULL DEFAULT 1,
    privacy_consent_at DATETIME NULL,              -- when the user accepted the Data Privacy notice
    tour_completed_at  DATETIME NULL,              -- dashboard tutorial finished or skipped
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    name             VARCHAR(80) NOT NULL,
    type             ENUM('cash','bank','e_wallet','savings','credit_card') NOT NULL DEFAULT 'cash',
    opening_balance  DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_date         DATE NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_accounts_user (user_id),
    CONSTRAINT fk_accounts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,            -- NULL for global (admin-managed) categories
    name        VARCHAR(60) NOT NULL,
    type        ENUM('income','expense') NOT NULL,
    is_global   TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categories_owner_name (user_id, name, type),
    CONSTRAINT fk_categories_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id        INT UNSIGNED NOT NULL,
    category_id       INT UNSIGNED NULL,
    type              ENUM('income','expense') NOT NULL,
    amount            DECIMAL(12,2) NOT NULL,
    description       VARCHAR(120) NOT NULL,
    transaction_date  DATE NOT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tx_account_date (account_id, transaction_date),
    KEY idx_tx_category (category_id),
    CONSTRAINT chk_tx_amount_positive CHECK (amount > 0),
    CONSTRAINT fk_tx_account  FOREIGN KEY (account_id)  REFERENCES accounts(id)   ON DELETE CASCADE,
    CONSTRAINT fk_tx_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budgets (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    category_id   INT UNSIGNED NOT NULL,
    amount_limit  DECIMAL(12,2) NOT NULL,
    start_date    DATE NOT NULL,
    end_date      DATE NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_budgets_period (user_id, category_id, start_date),
    CONSTRAINT chk_budget_positive CHECK (amount_limit > 0),
    CONSTRAINT chk_budget_period CHECK (end_date >= start_date),
    CONSTRAINT fk_budgets_user     FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    CONSTRAINT fk_budgets_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS category_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    handled_by      INT UNSIGNED NULL,
    requested_name  VARCHAR(60) NOT NULL,
    requested_type  ENUM('income','expense') NOT NULL,
    reason          VARCHAR(255) NOT NULL DEFAULT '',
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at     DATETIME NULL,
    KEY idx_catreq_status (status),
    CONSTRAINT fk_catreq_user    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_catreq_handler FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    handled_by   INT UNSIGNED NULL,
    subject      VARCHAR(120) NOT NULL,
    message      TEXT NOT NULL,
    staff_reply  TEXT NULL,
    status       ENUM('pending','in_progress','resolved') NOT NULL DEFAULT 'pending',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at  DATETIME NULL,
    KEY idx_tickets_status (status),
    CONSTRAINT fk_tickets_user    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tickets_handler FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    message     VARCHAR(255) NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user (user_id, is_read),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only: the application exposes no update/delete for this table.
CREATE TABLE IF NOT EXISTS audit_log (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id     INT UNSIGNED NULL,
    action       VARCHAR(60) NOT NULL,
    target_type  VARCHAR(40) NOT NULL,
    target_id    INT UNSIGNED NULL,
    details      VARCHAR(255) NOT NULL DEFAULT '',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

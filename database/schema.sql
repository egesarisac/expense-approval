CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(16) NOT NULL,
    manager_id INT NULL,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_manager (manager_id),
    CONSTRAINT fk_users_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT ck_users_role CHECK (role IN ('employee', 'manager'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount INT NOT NULL,
    currency CHAR(3) NOT NULL,
    status VARCHAR(16) NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_expenses_employee (employee_id),
    CONSTRAINT fk_expenses_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT ck_expenses_amount CHECK (amount > 0),
    CONSTRAINT ck_expenses_status CHECK (status IN ('pending', 'approved'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expense_approvals (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    expense_id INT NOT NULL,
    approved_by INT NOT NULL,
    comment VARCHAR(500) NULL,
    approved_at DATETIME NOT NULL,
    UNIQUE KEY uq_approvals_expense (expense_id),
    KEY idx_approvals_user (approved_by),
    CONSTRAINT fk_approvals_expense FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_approvals_user FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE access_tokens (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY uq_tokens_hash (token_hash),
    KEY idx_tokens_user (user_id),
    KEY idx_tokens_expiry (expires_at),
    CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT ck_tokens_expiry CHECK (expires_at > created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

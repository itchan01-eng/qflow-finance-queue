
-- QFlow: RMMCMI Finance Queuing Management System
-- Initial prototype schema
-- Database qflow_finance must already exist.

CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS department (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    account_id VARCHAR(100) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NULL UNIQUE,
    department_id INT NULL,
    password VARCHAR(255) NOT NULL,
    account_status ENUM('active', 'inactive', 'locked')
        NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id),
    FOREIGN KEY (department_id) REFERENCES department(department_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
    service_id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_counters (
    counter_id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    counter_name VARCHAR(50) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_counter_service_name (service_id, counter_name),
    FOREIGN KEY (service_id) REFERENCES services(service_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff_service_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_staff_service (user_id, service_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (service_id) REFERENCES services(service_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS queue_counter (
    queue_counter_id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    queue_date DATE NOT NULL,
    last_number INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_service_daily_sequence (service_id, queue_date),
    FOREIGN KEY (service_id) REFERENCES services(service_id)
) ENGINE=InnoDB;

-- Lookup values for current queue statuses.
CREATE TABLE IF NOT EXISTS queue_remarks (
    queue_remarks_id INT AUTO_INCREMENT PRIMARY KEY,
    queue_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS queues (
    queue_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    queue_number INT NOT NULL,
    account_id INT NULL,
    service_id INT NOT NULL,
    counter_id INT NULL,
    queue_remarks_id INT NOT NULL,
    queue_date DATE NOT NULL,
    ticket_type ENUM('student', 'guest') NOT NULL DEFAULT 'student',
    guest_name VARCHAR(100) NULL,
    created_by INT NULL,
    served_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    called_at DATETIME NULL,
    completed_at DATETIME NULL,

    UNIQUE KEY uq_service_date_number
        (service_id, queue_date, queue_number),

    INDEX idx_queue_status_date
        (queue_date, service_id, queue_remarks_id),

    FOREIGN KEY (account_id) REFERENCES users(user_id),
    FOREIGN KEY (service_id) REFERENCES services(service_id),
    FOREIGN KEY (counter_id) REFERENCES service_counters(counter_id),
    FOREIGN KEY (queue_remarks_id)
        REFERENCES queue_remarks(queue_remarks_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    FOREIGN KEY (served_by) REFERENCES users(user_id),

    CHECK (
        (ticket_type = 'student' AND account_id IS NOT NULL)
        OR
        (ticket_type = 'guest' AND account_id IS NULL)
    )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS queue_status (
    history_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    queue_id BIGINT NOT NULL,
    previous_status_id INT NULL,
    new_status_id INT NOT NULL,
    changed_by INT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_history_queue (queue_id, changed_at),

    FOREIGN KEY (queue_id) REFERENCES queues(queue_id),
    FOREIGN KEY (previous_status_id)
        REFERENCES queue_remarks(queue_remarks_id),
    FOREIGN KEY (new_status_id)
        REFERENCES queue_remarks(queue_remarks_id),
    FOREIGN KEY (changed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transaction_logs (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NULL,
    action_type VARCHAR(100) NOT NULL,
    table_name VARCHAR(64) NOT NULL,
    record_id BIGINT NULL,
    description VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_logs_created (created_at),

    FOREIGN KEY (account_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- Initial roles
INSERT IGNORE INTO roles (role_name, description) VALUES
('Student', 'Student account'),
('Staff', 'Finance staff account'),
('Administrator', 'System administrator');

-- Initial department
INSERT IGNORE INTO department (department_name)
VALUES ('Finance Office');

-- Finance services
INSERT IGNORE INTO services (service_name, description) VALUES
('Cashier', 'Cashier transactions and payments'),
('Receivables', 'Receivables inquiries and transactions');

-- Initial queue statuses
INSERT IGNORE INTO queue_remarks (queue_name) VALUES
('Waiting'),
('Serving'),
('Completed'),
('Skipped'),
('Cancelled'),
('No Show');

-- One default counter per service
INSERT IGNORE INTO service_counters
    (service_id, counter_name)
SELECT service_id, CONCAT(service_name, ' Counter 1')
FROM services
WHERE service_name IN ('Cashier', 'Receivables');

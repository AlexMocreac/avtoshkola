ALTER TABLE crm_contracts
    ADD COLUMN enrollment_id BIGINT UNSIGNED NULL,
    ADD COLUMN student_access TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN training_status VARCHAR(24) NOT NULL DEFAULT 'active',
    ADD COLUMN driving_allowed TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN training_end_on DATE NULL,
    ADD COLUMN gearbox VARCHAR(16) NULL,
    ADD COLUMN dismissed_at DATETIME NULL,
    ADD UNIQUE KEY crm_contracts_enrollment_unique (enrollment_id),
    ADD CONSTRAINT crm_contracts_enrollment_fk FOREIGN KEY (enrollment_id) REFERENCES class_enrollments (id);

INSERT INTO crm_contracts (contract_number, student_id, enrollment_id, student_access, counterparty, client_phone, subject, direction, signed_on, starts_on, status, created_by, updated_by)
SELECT CONCAT('ЗАЧИСЛЕНИЕ-', ce.id, '-', REPLACE(UUID(), '-', '')), ce.student_id, ce.id,
       IF(u.status = 'active' AND (u.access_expires_at IS NULL OR u.access_expires_at > NOW()), 1, 0),
       TRIM(CONCAT_WS(' ', u.last_name, u.first_name, u.middle_name)), u.phone,
       CONCAT('Обучение: ', c.group_number, ' · ', c.name), 'driving_school', ce.joined_on, ce.joined_on,
       CASE WHEN ce.status = 'active' THEN 'active' ELSE 'completed' END, ce.created_by, ce.created_by
FROM class_enrollments ce JOIN users u ON u.id = ce.student_id JOIN student_classes c ON c.id = ce.class_id;

UPDATE crm_contracts c JOIN class_enrollments ce ON ce.id = c.enrollment_id
JOIN student_profiles sp ON sp.student_id = ce.student_id
SET c.training_status = sp.training_status, c.driving_allowed = sp.driving_allowed,
    c.training_end_on = sp.training_end_on, c.gearbox = sp.gearbox, c.dismissed_at = sp.dismissed_at
WHERE ce.id = (SELECT MAX(latest.id) FROM class_enrollments latest WHERE latest.student_id = ce.student_id);

ALTER TABLE student_sales ADD COLUMN contract_id BIGINT UNSIGNED NULL,
    ADD KEY student_sales_contract_index (contract_id),
    ADD CONSTRAINT student_sales_contract_fk FOREIGN KEY (contract_id) REFERENCES crm_contracts (id);
ALTER TABLE student_payments ADD COLUMN contract_id BIGINT UNSIGNED NULL,
    ADD KEY student_payments_contract_index (contract_id),
    ADD CONSTRAINT student_payments_contract_fk FOREIGN KEY (contract_id) REFERENCES crm_contracts (id);

UPDATE student_sales s JOIN crm_contracts c ON c.enrollment_id = (SELECT MAX(ce.id) FROM class_enrollments ce WHERE ce.student_id = s.student_id)
SET s.contract_id = c.id;
UPDATE student_payments p JOIN crm_contracts c ON c.enrollment_id = (SELECT MAX(ce.id) FROM class_enrollments ce WHERE ce.student_id = p.student_id)
SET p.contract_id = c.id;

CREATE TABLE contract_checkpoint_states (
    contract_id BIGINT UNSIGNED NOT NULL,
    checkpoint_id BIGINT UNSIGNED NOT NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (contract_id, checkpoint_id),
    CONSTRAINT contract_checkpoint_contract_fk FOREIGN KEY (contract_id) REFERENCES crm_contracts (id),
    CONSTRAINT contract_checkpoint_checkpoint_fk FOREIGN KEY (checkpoint_id) REFERENCES learning_checkpoints (id),
    CONSTRAINT contract_checkpoint_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO contract_checkpoint_states (contract_id, checkpoint_id, is_completed, completed_at, updated_by, updated_at)
SELECT c.id, s.checkpoint_id, s.is_completed, s.completed_at, s.updated_by, s.updated_at
FROM student_checkpoint_states s JOIN crm_contracts c ON c.enrollment_id = (SELECT MAX(ce.id) FROM class_enrollments ce WHERE ce.student_id = s.student_id);

CREATE TABLE contract_balances (
    contract_id BIGINT UNSIGNED NOT NULL,
    balance_type_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (contract_id, balance_type_id),
    CONSTRAINT contract_balance_contract_fk FOREIGN KEY (contract_id) REFERENCES crm_contracts (id),
    CONSTRAINT contract_balance_type_fk FOREIGN KEY (balance_type_id) REFERENCES student_balance_types (id),
    CONSTRAINT contract_balance_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO contract_balances (contract_id, balance_type_id, amount, updated_by, updated_at)
SELECT c.id, b.balance_type_id, b.amount, b.updated_by, b.updated_at
FROM student_balances b JOIN crm_contracts c ON c.enrollment_id = (SELECT MAX(ce.id) FROM class_enrollments ce WHERE ce.student_id = b.student_id);

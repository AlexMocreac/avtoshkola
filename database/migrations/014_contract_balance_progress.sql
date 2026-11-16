CREATE TABLE contract_balance_manual_usage (
    contract_id BIGINT UNSIGNED NOT NULL,
    balance_type_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (contract_id, balance_type_id),
    CONSTRAINT contract_manual_usage_contract_fk FOREIGN KEY (contract_id) REFERENCES crm_contracts (id),
    CONSTRAINT contract_manual_usage_type_fk FOREIGN KEY (balance_type_id) REFERENCES student_balance_types (id),
    CONSTRAINT contract_manual_usage_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

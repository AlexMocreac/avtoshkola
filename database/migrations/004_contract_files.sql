CREATE TABLE IF NOT EXISTS contract_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contract_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(190) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY contract_files_contract_index (contract_id, id),
    KEY contract_files_uploader_index (uploaded_by, created_at),
    CONSTRAINT contract_files_contract_fk FOREIGN KEY (contract_id) REFERENCES crm_contracts (id) ON DELETE CASCADE,
    CONSTRAINT contract_files_uploader_fk FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

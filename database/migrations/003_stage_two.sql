CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_key VARCHAR(80) NOT NULL,
    action_name VARCHAR(190) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    actor_name VARCHAR(190) NOT NULL,
    actor_role VARCHAR(80) NULL,
    entity_type VARCHAR(60) NULL,
    entity_id BIGINT UNSIGNED NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY activity_logs_created_index (created_at, id),
    KEY activity_logs_actor_index (actor_user_id, created_at),
    KEY activity_logs_action_index (action_key, created_at),
    CONSTRAINT activity_logs_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log_targets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    activity_log_id BIGINT UNSIGNED NOT NULL,
    impacted_user_id BIGINT UNSIGNED NULL,
    impacted_name VARCHAR(190) NOT NULL,
    PRIMARY KEY (id),
    KEY activity_log_targets_user_index (impacted_user_id, activity_log_id),
    CONSTRAINT activity_log_targets_log_fk FOREIGN KEY (activity_log_id) REFERENCES activity_logs (id) ON DELETE CASCADE,
    CONSTRAINT activity_log_targets_user_fk FOREIGN KEY (impacted_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_leads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NULL,
    middle_name VARCHAR(80) NULL,
    company_name VARCHAR(190) NULL,
    phone VARCHAR(32) NULL,
    email VARCHAR(190) NULL,
    source VARCHAR(80) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'new',
    notes TEXT NULL,
    next_contact_at DATETIME NULL,
    assigned_to BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY crm_leads_status_index (status, updated_at),
    KEY crm_leads_contact_index (next_contact_at, status),
    KEY crm_leads_assigned_index (assigned_to, status),
    CONSTRAINT crm_leads_assignee_fk FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT crm_leads_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT crm_leads_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_contracts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contract_number VARCHAR(80) NOT NULL,
    lead_id BIGINT UNSIGNED NULL,
    counterparty VARCHAR(190) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    signed_on DATE NOT NULL,
    starts_on DATE NULL,
    ends_on DATE NULL,
    amount DECIMAL(12,2) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY crm_contracts_number_unique (contract_number),
    KEY crm_contracts_status_index (status, ends_on),
    KEY crm_contracts_lead_index (lead_id),
    CONSTRAINT crm_contracts_lead_fk FOREIGN KEY (lead_id) REFERENCES crm_leads (id) ON DELETE SET NULL,
    CONSTRAINT crm_contracts_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT crm_contracts_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    description TEXT NOT NULL,
    task_date DATE NOT NULL,
    due_at DATETIME NULL,
    confidential TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(24) NOT NULL DEFAULT 'open',
    recurrence_group CHAR(36) NULL,
    recurrence_rule VARCHAR(24) NULL,
    recurrence_day TINYINT UNSIGNED NULL,
    recurrence_until DATE NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    completed_by BIGINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY tasks_status_due_index (status, due_at),
    KEY tasks_recurrence_index (recurrence_group, task_date),
    KEY tasks_creator_index (created_by, created_at),
    CONSTRAINT tasks_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT tasks_completer_fk FOREIGN KEY (completed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_assignees (
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id),
    KEY task_assignees_user_index (user_id, task_id),
    CONSTRAINT task_assignees_task_fk FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
    CONSTRAINT task_assignees_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deadline_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    category VARCHAR(80) NULL,
    description TEXT NULL,
    due_at DATETIME NOT NULL,
    impacted_user_id BIGINT UNSIGNED NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'open',
    created_by BIGINT UNSIGNED NOT NULL,
    completed_by BIGINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY deadline_events_status_due_index (status, due_at),
    CONSTRAINT deadline_events_impacted_fk FOREIGN KEY (impacted_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT deadline_events_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT deadline_events_completer_fk FOREIGN KEY (completed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

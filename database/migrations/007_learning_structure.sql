CREATE TABLE IF NOT EXISTS class_folders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY class_folders_name_unique (name),
    KEY class_folders_order_index (status, sort_order, name),
    CONSTRAINT class_folders_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT class_folders_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO class_folders (name, sort_order, status, created_by, updated_by)
SELECT 'Общие классы', 0, 'active', id, id
FROM users
WHERE status <> 'deleted'
ORDER BY FIELD(role, 'director', 'deputy_director', 'admin', 'teacher', 'instructor', 'student'), id
LIMIT 1;

CREATE TABLE IF NOT EXISTS training_program_folders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY training_program_folders_name_unique (name),
    KEY training_program_folders_order_index (status, sort_order, name),
    CONSTRAINT training_program_folders_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT training_program_folders_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO training_program_folders (name, sort_order, status, created_by, updated_by)
SELECT 'Основные программы', 0, 'active', id, id
FROM users
WHERE status <> 'deleted'
ORDER BY FIELD(role, 'director', 'deputy_director', 'admin', 'teacher', 'instructor', 'student'), id
LIMIT 1;

CREATE TABLE IF NOT EXISTS training_programs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    folder_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    code VARCHAR(40) NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY training_programs_folder_name_unique (folder_id, name),
    KEY training_programs_order_index (status, folder_id, sort_order, name),
    CONSTRAINT training_programs_folder_fk FOREIGN KEY (folder_id) REFERENCES training_program_folders (id),
    CONSTRAINT training_programs_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT training_programs_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO training_programs (folder_id, name, sort_order, status, created_by, updated_by)
SELECT pf.id, legacy.study_program, 0, 'active', actor.id, actor.id
FROM (SELECT DISTINCT study_program FROM student_classes WHERE study_program <> '') legacy
INNER JOIN (SELECT id FROM training_program_folders ORDER BY id LIMIT 1) pf
INNER JOIN (
    SELECT id FROM users WHERE status <> 'deleted'
    ORDER BY FIELD(role, 'director', 'deputy_director', 'admin', 'teacher', 'instructor', 'student'), id LIMIT 1
) actor;

ALTER TABLE student_classes
    ADD COLUMN folder_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN program_id BIGINT UNSIGNED NULL AFTER study_program,
    ADD KEY student_classes_folder_index (folder_id, status, starts_on),
    ADD KEY student_classes_program_index (program_id, status),
    ADD CONSTRAINT student_classes_folder_fk FOREIGN KEY (folder_id) REFERENCES class_folders (id),
    ADD CONSTRAINT student_classes_program_fk FOREIGN KEY (program_id) REFERENCES training_programs (id);

UPDATE student_classes SET folder_id = (SELECT id FROM class_folders ORDER BY id LIMIT 1) WHERE folder_id IS NULL;
UPDATE student_classes c
INNER JOIN training_programs p ON p.name = c.study_program
SET c.program_id = p.id
WHERE c.program_id IS NULL;

CREATE TABLE IF NOT EXISTS learning_checkpoints (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    code VARCHAR(24) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY learning_checkpoints_code_unique (code),
    KEY learning_checkpoints_order_index (status, sort_order, name),
    CONSTRAINT learning_checkpoints_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT learning_checkpoints_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_checkpoint_states (
    student_id BIGINT UNSIGNED NOT NULL,
    checkpoint_id BIGINT UNSIGNED NOT NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, checkpoint_id),
    KEY student_checkpoint_states_checkpoint_index (checkpoint_id, is_completed),
    CONSTRAINT student_checkpoint_states_student_fk FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT student_checkpoint_states_checkpoint_fk FOREIGN KEY (checkpoint_id) REFERENCES learning_checkpoints (id) ON DELETE CASCADE,
    CONSTRAINT student_checkpoint_states_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_balance_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(24) NOT NULL,
    name VARCHAR(190) NOT NULL,
    unit VARCHAR(24) NOT NULL DEFAULT 'hours',
    color VARCHAR(7) NOT NULL DEFAULT '#4768db',
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY student_balance_types_code_unique (code),
    KEY student_balance_types_order_index (status, sort_order, name),
    CONSTRAINT student_balance_types_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT student_balance_types_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_balances (
    student_id BIGINT UNSIGNED NOT NULL,
    balance_type_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, balance_type_id),
    KEY student_balances_type_index (balance_type_id, amount),
    CONSTRAINT student_balances_student_fk FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT student_balances_type_fk FOREIGN KEY (balance_type_id) REFERENCES student_balance_types (id) ON DELETE CASCADE,
    CONSTRAINT student_balances_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

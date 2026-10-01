CREATE TABLE IF NOT EXISTS student_classes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    group_number VARCHAR(80) NOT NULL,
    classroom VARCHAR(190) NULL,
    study_program VARCHAR(190) NOT NULL,
    teacher_id BIGINT UNSIGNED NOT NULL,
    starts_on DATE NOT NULL,
    driving_starts_on DATE NULL,
    internal_exam_on DATE NULL,
    inspection_registration_on DATE NULL,
    planned_lesson_count SMALLINT UNSIGNED NOT NULL,
    lesson_duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY student_classes_group_number_unique (group_number),
    KEY student_classes_teacher_status_index (teacher_id, status),
    KEY student_classes_start_index (starts_on, status),
    CONSTRAINT student_classes_teacher_fk FOREIGN KEY (teacher_id) REFERENCES users (id),
    CONSTRAINT student_classes_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT student_classes_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_schedule_patterns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id BIGINT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    starts_at TIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY class_schedule_pattern_unique (class_id, weekday, starts_at),
    CONSTRAINT class_schedule_patterns_class_fk FOREIGN KEY (class_id) REFERENCES student_classes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_holidays (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    holiday_date DATE NOT NULL,
    name VARCHAR(190) NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY school_holidays_date_unique (holiday_date),
    KEY school_holidays_year_index (holiday_date),
    CONSTRAINT school_holidays_creator_fk FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_lessons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id BIGINT UNSIGNED NOT NULL,
    teacher_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL DEFAULT 'Теоретическое занятие',
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'scheduled',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY class_lessons_class_start_unique (class_id, starts_at),
    KEY class_lessons_calendar_index (starts_at, status),
    KEY class_lessons_teacher_index (teacher_id, starts_at),
    CONSTRAINT class_lessons_class_fk FOREIGN KEY (class_id) REFERENCES student_classes (id) ON DELETE CASCADE,
    CONSTRAINT class_lessons_teacher_fk FOREIGN KEY (teacher_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_enrollments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    joined_on DATE NOT NULL,
    left_on DATE NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY class_enrollments_class_status_index (class_id, status),
    KEY class_enrollments_student_status_index (student_id, status),
    CONSTRAINT class_enrollments_class_fk FOREIGN KEY (class_id) REFERENCES student_classes (id),
    CONSTRAINT class_enrollments_student_fk FOREIGN KEY (student_id) REFERENCES users (id),
    CONSTRAINT class_enrollments_creator_fk FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_profiles (
    student_id BIGINT UNSIGNED NOT NULL,
    training_end_on DATE NULL,
    gearbox VARCHAR(16) NULL,
    gender VARCHAR(16) NULL,
    birth_date DATE NULL,
    citizenship VARCHAR(120) NULL,
    registration_number VARCHAR(80) NULL,
    birth_place VARCHAR(190) NULL,
    permanent_address VARCHAR(255) NULL,
    temporary_address VARCHAR(255) NULL,
    snils VARCHAR(32) NULL,
    customer_type VARCHAR(24) NOT NULL DEFAULT 'student',
    customer_name VARCHAR(190) NULL,
    customer_details TEXT NULL,
    notes TEXT NULL,
    photo_path VARCHAR(500) NULL,
    driving_allowed TINYINT(1) NOT NULL DEFAULT 0,
    training_status VARCHAR(24) NOT NULL DEFAULT 'active',
    dismissed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id),
    KEY student_profiles_status_index (training_status, driving_allowed),
    CONSTRAINT student_profiles_student_fk FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO student_profiles (student_id)
SELECT id FROM users WHERE role = 'student' AND status <> 'deleted';

CREATE TABLE IF NOT EXISTS student_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    document_type VARCHAR(120) NOT NULL,
    series VARCHAR(40) NULL,
    number VARCHAR(80) NULL,
    issued_on DATE NULL,
    expires_on DATE NULL,
    issued_by VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY student_documents_student_index (student_id, id),
    CONSTRAINT student_documents_student_fk FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT student_documents_creator_fk FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(190) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY student_files_student_index (student_id, id),
    CONSTRAINT student_files_student_fk FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT student_files_uploader_fk FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kassas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY kassas_name_unique (name),
    KEY kassas_status_index (status, name),
    CONSTRAINT kassas_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT kassas_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS education_services (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    price DECIMAL(12,2) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY education_services_status_name_index (status, name),
    CONSTRAINT education_services_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT education_services_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_sales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NULL,
    service_name VARCHAR(190) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    sold_on DATE NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    sold_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY student_sales_student_index (student_id, status, sold_on),
    CONSTRAINT student_sales_student_fk FOREIGN KEY (student_id) REFERENCES users (id),
    CONSTRAINT student_sales_service_fk FOREIGN KEY (service_id) REFERENCES education_services (id) ON DELETE SET NULL,
    CONSTRAINT student_sales_seller_fk FOREIGN KEY (sold_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NULL,
    kassa_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    paid_on DATETIME NOT NULL,
    notes VARCHAR(255) NULL,
    accepted_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY student_payments_student_index (student_id, paid_on),
    KEY student_payments_sale_index (sale_id, paid_on),
    KEY student_payments_kassa_index (kassa_id, paid_on),
    CONSTRAINT student_payments_student_fk FOREIGN KEY (student_id) REFERENCES users (id),
    CONSTRAINT student_payments_sale_fk FOREIGN KEY (sale_id) REFERENCES student_sales (id) ON DELETE SET NULL,
    CONSTRAINT student_payments_kassa_fk FOREIGN KEY (kassa_id) REFERENCES kassas (id),
    CONSTRAINT student_payments_acceptor_fk FOREIGN KEY (accepted_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE crm_contracts
    ADD COLUMN student_id BIGINT UNSIGNED NULL AFTER lead_id,
    ADD KEY crm_contracts_student_index (student_id),
    ADD CONSTRAINT crm_contracts_student_fk FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE SET NULL;

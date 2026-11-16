ALTER TABLE student_classes
    ADD COLUMN default_balance_type_id BIGINT UNSIGNED NULL,
    ADD CONSTRAINT class_default_balance_type_fk FOREIGN KEY (default_balance_type_id) REFERENCES student_balance_types (id);

CREATE TABLE class_lesson_attendance (
    lesson_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    balance_type_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (lesson_id, student_id),
    KEY lesson_attendance_contract_index (contract_id, balance_type_id),
    CONSTRAINT lesson_attendance_lesson_fk FOREIGN KEY (lesson_id) REFERENCES class_lessons (id) ON DELETE RESTRICT,
    CONSTRAINT lesson_attendance_student_fk FOREIGN KEY (student_id) REFERENCES users (id),
    CONSTRAINT lesson_attendance_contract_fk FOREIGN KEY (contract_id) REFERENCES crm_contracts (id),
    CONSTRAINT lesson_attendance_balance_fk FOREIGN KEY (balance_type_id) REFERENCES student_balance_types (id),
    CONSTRAINT lesson_attendance_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

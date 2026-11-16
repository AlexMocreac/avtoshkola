CREATE TABLE student_balance_type_folders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY balance_folders_parent_index (parent_id),
    CONSTRAINT balance_folders_parent_fk FOREIGN KEY (parent_id) REFERENCES student_balance_type_folders (id),
    CONSTRAINT balance_folders_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT balance_folders_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO student_balance_type_folders (name, created_by, updated_by)
SELECT 'Общие балансы', id, id FROM users WHERE status <> 'deleted'
ORDER BY FIELD(role, 'director', 'deputy_director', 'admin', 'teacher', 'instructor', 'student'), id LIMIT 1;

ALTER TABLE student_balance_types
    ADD COLUMN folder_id BIGINT UNSIGNED NULL AFTER id,
    ADD KEY balance_types_folder_index (folder_id),
    ADD CONSTRAINT balance_types_folder_fk FOREIGN KEY (folder_id) REFERENCES student_balance_type_folders (id);

UPDATE student_balance_types SET folder_id = (SELECT id FROM student_balance_type_folders ORDER BY id LIMIT 1);

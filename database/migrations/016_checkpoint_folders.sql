CREATE TABLE learning_checkpoint_folders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY checkpoint_folders_parent_index (parent_id),
    CONSTRAINT checkpoint_folders_parent_fk FOREIGN KEY (parent_id) REFERENCES learning_checkpoint_folders (id),
    CONSTRAINT checkpoint_folders_creator_fk FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT checkpoint_folders_updater_fk FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO learning_checkpoint_folders (name, created_by, updated_by)
SELECT 'Общие контрольные точки', id, id FROM users WHERE status <> 'deleted'
ORDER BY FIELD(role, 'director', 'deputy_director', 'admin', 'teacher', 'instructor', 'student'), id LIMIT 1;

ALTER TABLE learning_checkpoints
    ADD COLUMN folder_id BIGINT UNSIGNED NULL AFTER id,
    ADD KEY checkpoints_folder_index (folder_id),
    ADD CONSTRAINT checkpoints_folder_fk FOREIGN KEY (folder_id) REFERENCES learning_checkpoint_folders (id);

UPDATE learning_checkpoints SET folder_id = (SELECT id FROM learning_checkpoint_folders ORDER BY id LIMIT 1);

CREATE TABLE education_service_folders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    CONSTRAINT service_folder_parent_fk FOREIGN KEY (parent_id) REFERENCES education_service_folders(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE education_services ADD COLUMN folder_id BIGINT UNSIGNED NULL, ADD COLUMN service_data JSON NULL, ADD CONSTRAINT service_folder_fk FOREIGN KEY (folder_id) REFERENCES education_service_folders(id) ON DELETE SET NULL;

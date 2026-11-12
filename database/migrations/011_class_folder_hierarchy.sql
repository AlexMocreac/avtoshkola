ALTER TABLE class_folders
    ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER id,
    ADD KEY class_folders_parent_index (parent_id),
    ADD CONSTRAINT class_folders_parent_fk FOREIGN KEY (parent_id) REFERENCES class_folders (id) ON DELETE RESTRICT;

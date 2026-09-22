ALTER TABLE users
    ADD COLUMN access_months SMALLINT UNSIGNED NULL AFTER role,
    ADD COLUMN access_expires_at DATETIME NULL AFTER access_months,
    ADD KEY users_access_expiry_index (role, status, access_expires_at);

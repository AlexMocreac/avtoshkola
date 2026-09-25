ALTER TABLE crm_leads
    ADD COLUMN direction VARCHAR(32) NOT NULL DEFAULT 'driving_school' AFTER source,
    ADD KEY crm_leads_direction_status_index (direction, status, updated_at);

ALTER TABLE crm_contracts
    ADD COLUMN registration_number VARCHAR(80) NULL AFTER contract_number,
    ADD COLUMN client_phone VARCHAR(32) NULL AFTER counterparty,
    ADD COLUMN direction VARCHAR(32) NOT NULL DEFAULT 'driving_school' AFTER subject,
    ADD KEY crm_contracts_signed_index (signed_on, id),
    ADD KEY crm_contracts_phone_index (client_phone);

UPDATE crm_contracts c
LEFT JOIN crm_leads l ON l.id = c.lead_id
SET c.client_phone = COALESCE(c.client_phone, l.phone),
    c.direction = COALESCE(l.direction, 'driving_school');

UPDATE crm_contracts SET status = 'active' WHERE status = 'draft';

ALTER TABLE crm_contracts MODIFY status VARCHAR(32) NOT NULL DEFAULT 'active';

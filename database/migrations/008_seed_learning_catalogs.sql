ALTER TABLE learning_checkpoints
    DROP INDEX learning_checkpoints_code_unique,
    ADD UNIQUE KEY learning_checkpoints_entry_unique (name, code, sort_order),
    ADD KEY learning_checkpoints_code_index (code);

ALTER TABLE student_balance_types
    DROP INDEX student_balance_types_code_unique,
    ADD UNIQUE KEY student_balance_types_entry_unique (name, code, sort_order),
    ADD KEY student_balance_types_code_index (code);

INSERT INTO learning_checkpoints (name, code, sort_order, status, created_by, updated_by)
SELECT seed.name, seed.code, seed.sort_order, 'active', actor.id, actor.id
FROM (
    SELECT 'Теоретический зачёт №1' AS name, 'Зт1' AS code, 1 AS sort_order
    UNION ALL SELECT 'Теоретический зачёт №2', 'Зт2', 2
    UNION ALL SELECT 'Теоретический зачёт №3', 'Зт3', 3
    UNION ALL SELECT 'Теоретический зачёт №4', 'Зт4', 4
    UNION ALL SELECT 'Теоретический зачёт №5', 'Зт5', 5
    UNION ALL SELECT 'Теоретический зачёт №6', 'Зт6', 6
    UNION ALL SELECT 'Теоретический зачёт №7', 'Зт7', 7
    UNION ALL SELECT 'Внутренний теоретический экзамен', 'ВнТ', 8
    UNION ALL SELECT 'Внутренний экзамен по управлению ТС', 'ВнВ', 9
    UNION ALL SELECT 'Допуск к внутреннему практическому экзамену', 'ДЭ', 9
    UNION ALL SELECT 'Теоретический экзамен', 'Т', 10
    UNION ALL SELECT 'Экзамен по управлению ТС', 'В', 11
    UNION ALL SELECT 'Внутренний теоретический экзамен', 'ВнТ', 100
    UNION ALL SELECT 'Внутренний практический экзамен', 'ВнГ', 300
    UNION ALL SELECT 'Регистрация на ГосУслугах', 'ГсУ', 400
    UNION ALL SELECT 'Теоретический экзамен', 'Т', 500
    UNION ALL SELECT 'Экзамен по управлению ТС в условиях дорожного движения', 'Г', 700
) seed
CROSS JOIN (
    SELECT id
    FROM users
    WHERE status <> 'deleted'
    ORDER BY FIELD(role, 'director', 'deputy_director', 'admin', 'teacher', 'instructor', 'student'), id
    LIMIT 1
) actor
LEFT JOIN learning_checkpoints existing
    ON existing.name = seed.name
    AND existing.code = seed.code
    AND existing.sort_order = seed.sort_order
WHERE existing.id IS NULL;

INSERT INTO student_balance_types (code, name, unit, color, sort_order, status, created_by, updated_by)
SELECT seed.code, seed.name, 'hours', seed.color, seed.sort_order, seed.status, actor.id, actor.id
FROM (
    SELECT 'VIP' AS code, 'Категория "B" (VIP)' AS name, '#12dfe0' AS color, 0 AS sort_order, 'active' AS status
    UNION ALL SELECT 'МПО', 'МПОВ вождение', '#ff7358', 0, 'active'
    UNION ALL SELECT 'A', 'Аренда МОТО', '#a90070', 0, 'active'
    UNION ALL SELECT 'Вжд', 'Практическое вождение', '#236b13', 1, 'archived'
    UNION ALL SELECT 'A', 'Категория "A" (мотоцикл)', '#374151', 1, 'active'
    UNION ALL SELECT 'B', 'Категория "B" (легковой автомобиль)', '#4caf50', 2, 'active'
    UNION ALL SELECT 'A', 'Категория "A" ЗИМА', '#0bd7d4', 2, 'active'
    UNION ALL SELECT 'Д90', 'Докат 90мин', '#ec009d', 3, 'active'
    UNION ALL SELECT 'BMW', 'Категория "Б" (Комфорт)', '#55b8e8', 3, 'active'
    UNION ALL SELECT 'A', 'Аренда ТС', '#2516ed', 4, 'active'
    UNION ALL SELECT 'Д90', 'Докат 90 мин свое ТС', '#ec009d', 4, 'active'
    UNION ALL SELECT 'A1', 'Подкатегория A1', '#4615ed', 5, 'active'
    UNION ALL SELECT 'ДД', 'Категория "A" в условиях города', '#57b7e5', 5, 'active'
    UNION ALL SELECT 'Э', 'Внутренний экзамен', '#008c95', 6, 'active'
    UNION ALL SELECT 'Э', 'Экзамен ГАИ', '#0bd7d4', 6, 'active'
    UNION ALL SELECT 'A', 'Категория "A" КТМ', '#4219e8', 6, 'active'
    UNION ALL SELECT 'Доп', 'Дополнительные часы вождения', '#d88a00', 7, 'active'
    UNION ALL SELECT 'ПОД', 'Подготовка к экзамену', '#ef3340', 17, 'active'
) seed
CROSS JOIN (
    SELECT id
    FROM users
    WHERE status <> 'deleted'
    ORDER BY FIELD(role, 'director', 'deputy_director', 'admin', 'teacher', 'instructor', 'student'), id
    LIMIT 1
) actor
LEFT JOIN student_balance_types existing
    ON existing.name = seed.name
    AND existing.code = seed.code
    AND existing.sort_order = seed.sort_order
WHERE existing.id IS NULL;

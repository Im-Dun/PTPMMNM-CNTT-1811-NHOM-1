SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'password_hash'
);
SET @add_password_hash := IF(
    @column_exists = 0,
    'ALTER TABLE users ADD password_hash VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE add_password_hash FROM @add_password_hash;
EXECUTE add_password_hash;
DEALLOCATE PREPARE add_password_hash;

UPDATE users
SET password_hash = CASE email
    WHEN 'alice@example.test' THEN '$2y$10$tj6C2tTPM/XaFahf/dlB1uLEKDyp9Vi1Cu1tmdfj/KtR9KGMG2qsS'
    WHEN 'bob@example.test' THEN '$2y$10$aa1JLgrs5NBSZKAesb4B5uM3NqodTRv2WHS1Shp2DUGvcWI.CDV.6'
    WHEN 'eve@example.test' THEN '$2y$10$31wQDSlyHvrz6gBzsoSOjeHibsc5l9CmNh/exDF8VlQQfP46VvWBS'
END
WHERE password_hash IS NULL;

ALTER TABLE users MODIFY password_hash VARCHAR(255) NOT NULL;

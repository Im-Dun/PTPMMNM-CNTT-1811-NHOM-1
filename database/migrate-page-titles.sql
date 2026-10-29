SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'saved_pages'
      AND column_name = 'title'
);
SET @add_title := IF(
    @column_exists = 0,
    'ALTER TABLE saved_pages ADD title VARCHAR(500) NOT NULL DEFAULT '''' AFTER source_url',
    'SELECT 1'
);
PREPARE add_title FROM @add_title;
EXECUTE add_title;
DEALLOCATE PREPARE add_title;

UPDATE saved_pages p
SET p.title = COALESCE((
    SELECT s.title
    FROM snapshots s
    WHERE s.saved_page_id = p.id
    ORDER BY s.version_no DESC
    LIMIT 1
), '')
WHERE p.title = '';

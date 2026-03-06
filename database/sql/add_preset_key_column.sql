-- Add preset_key column to draw_settings table
-- Run this SQL on your remote database

ALTER TABLE `draw_settings` 
ADD COLUMN `preset_key` VARCHAR(255) NULL 
AFTER `playoff_config`;

-- Optional: Add an index for faster lookups
CREATE INDEX `idx_preset_key` ON `draw_settings` (`preset_key`);

-- ============================================================
-- IMPORTANT: Clear any invalid/old preset keys
-- ============================================================
-- If you have old data (like preset_key = '1' instead of '1_group_4s'),
-- you should clear them so the system will work correctly:

UPDATE `draw_settings` 
SET `preset_key` = NULL 
WHERE `preset_key` IS NOT NULL 
  AND `preset_key` NOT LIKE '%_group%'
  AND `preset_key` NOT LIKE '%groups%';

-- This clears preset keys that are just numbers like '1', '2', etc.
-- Valid preset keys look like: '1_group_4s', '4_groups_standard', etc.

-- ============================================================
-- CLEAN UP INVALID POSITIONS IN PLAYOFF CONFIG
-- ============================================================
-- This query finds all playoff configs and checks if positions exceed actual group sizes
-- Run this to see which draws have invalid positions:

SELECT 
    ds.id,
    ds.draw_id,
    ds.boxes as num_groups,
    ds.preset_key,
    ds.playoff_config,
    (SELECT MIN(player_count) FROM (
        SELECT COUNT(dgr.registration_id) as player_count
        FROM draw_groups dg
        LEFT JOIN draw_group_registrations dgr ON dg.id = dgr.draw_group_id
        WHERE dg.draw_id = ds.draw_id
        GROUP BY dg.id
    ) as group_counts) as min_players_per_group
FROM draw_settings ds
WHERE ds.playoff_config IS NOT NULL;

-- ⚠️ WARNING: If you see positions higher than min_players_per_group, 
-- those positions are INVALID and will cause counting errors!
-- You'll need to manually edit the playoff_config JSON to remove invalid positions.

-- ============================================================
-- Verify the column was added
-- ============================================================
DESCRIBE `draw_settings`;

-- Check if any data exists
SELECT id, draw_id, boxes, preset_key 
FROM `draw_settings` 
LIMIT 10;



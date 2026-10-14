-- LPG POS: configurable source filled-cylinder selection
USE perfect_lpg;

ALTER TABLE shop_settings
    ADD COLUMN IF NOT EXISTS allow_pos_source_cylinder_selection BOOLEAN NOT NULL DEFAULT 0
    AFTER individual_cylinder_tracking;

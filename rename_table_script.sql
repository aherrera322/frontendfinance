-- SQL script to rename site_a_reservations table to zbcom_reservations
-- Run this script in your MySQL database to rename the existing table

-- Check if the old table exists and rename it
RENAME TABLE IF EXISTS site_a_reservations TO zbcom_reservations;

-- Update the data source name in data_sources table
UPDATE data_sources SET source_name = 'zbcom', display_name = 'ZB.com' WHERE source_name = 'site_a';

-- Recreate the view with the new table name
CREATE OR REPLACE VIEW all_reservations AS
    SELECT 'zbcom' as source, id, source_id, res_date, agency, pay_mode, api_value, credit, cpc, prepay, status, imported_at FROM zbcom_reservations
    UNION ALL
    SELECT 'site_b' as source, id, source_id, res_date, agency, pay_mode, api_value, credit, cpc, prepay, status, imported_at FROM site_b_reservations
    UNION ALL
    SELECT 'site_c' as source, id, source_id, res_date, agency, pay_mode, api_value, credit, cpc, prepay, status, imported_at FROM site_c_reservations;

-- Show confirmation
SELECT 'Table renamed successfully' as status;

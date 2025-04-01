-- Script to remove Blood Inventory Management components

-- Drop triggers first
DROP TRIGGER IF EXISTS blood_request_status_trigger ON blood_requests;
DROP TRIGGER IF EXISTS sync_donations_trigger ON eligibility;

-- Drop functions
DROP FUNCTION IF EXISTS handle_blood_request_status_change();
DROP FUNCTION IF EXISTS sync_donations_to_inventory();
DROP FUNCTION IF EXISTS check_blood_availability(TEXT, TEXT, TEXT, INTEGER);
DROP FUNCTION IF EXISTS update_blood_inventory(TEXT, TEXT, TEXT, INTEGER, TEXT, INTEGER);

-- Drop tables (drop the table with foreign keys first)
DROP TABLE IF EXISTS inventory_audit_log;
DROP TABLE IF EXISTS blood_inventory; 
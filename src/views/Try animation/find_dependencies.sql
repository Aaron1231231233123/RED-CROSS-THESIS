-- Find triggers that depend on the function
SELECT 
    event_object_schema as table_schema,
    event_object_table as table_name,
    trigger_name,
    event_manipulation,
    action_statement
FROM information_schema.triggers
WHERE action_statement LIKE '%update_donor_eligibility_after_donation%';

-- Find other dependencies
SELECT dependent_ns.nspname as dependent_schema,
       dependent_view.relname as dependent_view
FROM pg_depend 
JOIN pg_rewrite ON pg_depend.objid = pg_rewrite.oid 
JOIN pg_class as dependent_view ON pg_rewrite.ev_class = dependent_view.oid 
JOIN pg_class as source_table ON pg_depend.refobjid = source_table.oid 
JOIN pg_namespace dependent_ns ON dependent_view.relnamespace = dependent_ns.oid 
JOIN pg_namespace source_ns ON source_table.relnamespace = source_ns.oid
WHERE source_ns.nspname = 'public' 
  AND source_table.relname = 'update_donor_eligibility_after_donation'; 
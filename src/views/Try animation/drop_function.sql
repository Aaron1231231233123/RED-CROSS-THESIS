-- Drop triggers that might be using the function
DROP TRIGGER IF EXISTS update_donor_eligibility_trigger ON blood_collection;
DROP TRIGGER IF EXISTS donor_eligibility_update_trigger ON blood_collection;
DROP TRIGGER IF EXISTS update_donor_eligibility_after_donation_trigger ON blood_collection;

-- Drop the function with CASCADE to force removal of dependencies
DROP FUNCTION IF EXISTS public.update_donor_eligibility_after_donation() CASCADE; 
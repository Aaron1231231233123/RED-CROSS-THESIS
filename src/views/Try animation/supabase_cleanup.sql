-- First, drop all existing objects
DROP TRIGGER IF EXISTS blood_collection_eligibility_trigger ON blood_collection;
DROP TRIGGER IF EXISTS update_eligibility_status_trigger ON eligibility;
DROP FUNCTION IF EXISTS get_donor_eligibility_status(INTEGER);
DROP FUNCTION IF EXISTS get_eligibility_status(INTEGER);
DROP FUNCTION IF EXISTS get_approved_screenings();
DROP FUNCTION IF EXISTS handle_blood_collection_eligibility() CASCADE;
DROP FUNCTION IF EXISTS update_eligibility_status() CASCADE;
DROP FUNCTION IF EXISTS calculate_deferral_period(TEXT);
DROP FUNCTION IF EXISTS calculate_eligibility_end_date(BOOLEAN, TEXT);
DROP TABLE IF EXISTS donor_eligibility CASCADE;
DROP TABLE IF EXISTS eligibility CASCADE;

-- Create new eligibility table
CREATE TABLE IF NOT EXISTS eligibility (
    eligibility_id BIGSERIAL PRIMARY KEY,
    donor_id INTEGER REFERENCES donor_form(donor_id),
    medical_history_id BIGINT REFERENCES medical_history(medical_history_id),
    screening_id UUID REFERENCES screening_form(screening_id),
    physical_exam_id UUID REFERENCES physical_examination(physical_exam_id),
    blood_collection_id UUID REFERENCES blood_collection(blood_collection_id),
    blood_type TEXT,
    donation_type TEXT,
    blood_bag_type TEXT,
    blood_bag_brand TEXT,
    amount_collected DECIMAL,
    collection_successful BOOLEAN,
    donor_reaction TEXT,
    management_done TEXT,
    collection_start_time TIMESTAMP WITH TIME ZONE,
    collection_end_time TIMESTAMP WITH TIME ZONE,
    unit_serial_number TEXT,
    disapproval_reason TEXT,
    start_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP WITH TIME ZONE,
    status TEXT DEFAULT 'ineligible',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
); 
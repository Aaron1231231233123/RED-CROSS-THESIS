-- Drop all existing objects first
DROP TRIGGER IF EXISTS blood_collection_eligibility_trigger ON blood_collection;
DROP TRIGGER IF EXISTS update_eligibility_status_trigger ON eligibility;
DROP FUNCTION IF EXISTS get_eligibility_status(INTEGER);
DROP FUNCTION IF EXISTS get_approved_screenings();
DROP FUNCTION IF EXISTS handle_blood_collection_eligibility() CASCADE;
DROP FUNCTION IF EXISTS update_eligibility_status() CASCADE;
DROP FUNCTION IF EXISTS calculate_deferral_period(TEXT);
DROP FUNCTION IF EXISTS calculate_eligibility_end_date(BOOLEAN, TEXT);
DROP TABLE IF EXISTS eligibility CASCADE;
DROP TABLE IF EXISTS donor_eligibility CASCADE;

CREATE OR REPLACE FUNCTION get_approved_screenings()
RETURNS TABLE (
    screening_id UUID,
    interview_date date,
    blood_type text,
    donation_type text,
    donor_id INTEGER,
    surname text,
    first_name text,
    disapproval_reason text
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        sf.screening_id,
        sf.interview_date,
        sf.blood_type,
        sf.donation_type,
        sf.donor_id,
        d.surname,
        d.first_name,
        sf.disapproval_reason
    FROM screening_form sf
    JOIN donor_form d ON d.donor_id = sf.donor_id
    WHERE sf.status != 'disapproved'
    AND sf.physical_exam_id IS NULL
    ORDER BY sf.interview_date DESC;
END;
$$ LANGUAGE plpgsql;

-- Create eligibility table with all foreign keys
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

-- Function to calculate deferral period based on disapproval reason
CREATE OR REPLACE FUNCTION calculate_deferral_period(p_disapproval_reason TEXT)
RETURNS INTERVAL AS $$
BEGIN
    -- Convert reason to lowercase for case-insensitive matching
    p_disapproval_reason := LOWER(p_disapproval_reason);
    
    -- Permanent deferrals
    IF p_disapproval_reason LIKE ANY(ARRAY[
        '%hiv%', '%hepatitis%', '%aids%', '%cancer%', '%leukemia%',
        '%lymphoma%', '%tuberculosis%', '%chronic heart disease%',
        '%severe lung disease%', '%bleeding disorder%'
    ]) THEN
        RETURN INTERVAL '100 years'; -- Effectively permanent
    
    -- 3 year deferral
    ELSIF p_disapproval_reason LIKE ANY(ARRAY[
        '%malaria%', '%babesiosis%'
    ]) THEN
        RETURN INTERVAL '3 years';
    
    -- 1 year deferrals
    ELSIF p_disapproval_reason LIKE ANY(ARRAY[
        '%blood transfusion%', '%organ transplant%', '%tissue transplant%',
        '%heart surgery%', '%major surgery%'
    ]) THEN
        RETURN INTERVAL '1 year';
    
    -- 6 month deferrals
    ELSIF p_disapproval_reason LIKE ANY(ARRAY[
        '%tattoo%', '%body piercing%', '%acupuncture%',
        '%hepatitis exposure%', '%needle stick%'
    ]) THEN
        RETURN INTERVAL '6 months';
    
    -- 3 month deferrals
    ELSIF p_disapproval_reason LIKE ANY(ARRAY[
        '%dental work%', '%dental surgery%', '%minor surgery%',
        '%ear piercing%'
    ]) THEN
        RETURN INTERVAL '3 months';
    
    -- 2 week deferrals
    ELSIF p_disapproval_reason LIKE ANY(ARRAY[
        '%cold%', '%flu%', '%fever%', '%infection%',
        '%antibiotics%', '%minor illness%'
    ]) THEN
        RETURN INTERVAL '14 days';
    
    -- 1 week deferrals
    ELSIF p_disapproval_reason LIKE ANY(ARRAY[
        '%low hemoglobin%', '%anemia%', '%low iron%',
        '%insufficient rest%', '%lack of sleep%'
    ]) THEN
        RETURN INTERVAL '7 days';
    
    -- Default deferral period if reason doesn't match any specific case
    ELSE
        RETURN INTERVAL '14 days';
    END IF;
END;
$$ LANGUAGE plpgsql;

-- Create function to calculate eligibility end date based on success or disapproval
CREATE OR REPLACE FUNCTION calculate_eligibility_end_date(
    p_is_successful BOOLEAN,
    p_disapproval_reason TEXT DEFAULT NULL
) RETURNS TIMESTAMP WITH TIME ZONE AS $$
BEGIN
    -- If disapproved, calculate based on reason
    IF p_disapproval_reason IS NOT NULL THEN
        RETURN CURRENT_TIMESTAMP + calculate_deferral_period(p_disapproval_reason);
    END IF;

    -- If collection was not successful (but not disapproved), check again in 3 days
    IF NOT p_is_successful THEN
        RETURN CURRENT_TIMESTAMP + INTERVAL '3 days';
    END IF;

    -- Successful donations require 3 months waiting period
    RETURN CURRENT_TIMESTAMP + INTERVAL '3 months';
END;
$$ LANGUAGE plpgsql;

-- Create function to handle eligibility tracking
CREATE OR REPLACE FUNCTION handle_blood_collection_eligibility()
RETURNS TRIGGER AS $$
DECLARE
    v_donor_id INTEGER;
    v_medical_history_id BIGINT;
    v_screening_id UUID;
    v_physical_exam_id UUID;
    v_donation_type TEXT;
    v_blood_type TEXT;
    v_end_date TIMESTAMP WITH TIME ZONE;
BEGIN
    -- Get all related IDs and information through the chain of relationships
    SELECT 
        df.donor_id,
        sf.medical_history_id,
        sf.screening_id,
        pe.physical_exam_id,
        sf.donation_type,
        sf.blood_type
    INTO 
        v_donor_id,
        v_medical_history_id,
        v_screening_id,
        v_physical_exam_id,
        v_donation_type,
        v_blood_type
    FROM blood_collection bc
    JOIN screening_form sf ON sf.screening_id = bc.screening_id
    JOIN donor_form df ON df.donor_id = sf.donor_form_id
    LEFT JOIN physical_examination pe ON pe.donor_id = df.donor_id
    WHERE bc.blood_collection_id = NEW.blood_collection_id;

    -- Calculate end date based on status
    v_end_date := calculate_eligibility_end_date(
        NEW.is_successful,
        NEW.disapproval_reason
    );

    -- If the screening is disapproved, update all related records
    IF NEW.disapproval_reason IS NOT NULL THEN
        -- Update screening form disapproval_reason
        UPDATE screening_form
        SET disapproval_reason = NEW.disapproval_reason
        WHERE screening_id = v_screening_id;

        -- Update medical history disapproval_reason
        UPDATE medical_history
        SET disapproval_reason = NEW.disapproval_reason
        WHERE medical_history_id = v_medical_history_id;

        -- Update physical examination disapproval_reason if it exists
        UPDATE physical_examination
        SET disapproval_reason = NEW.disapproval_reason
        WHERE screening_id = v_screening_id;

        -- Create disapproved eligibility record
        INSERT INTO eligibility (
            donor_id,
            medical_history_id,
            screening_id,
            physical_exam_id,
            blood_collection_id,
            blood_type,
            donation_type,
            disapproval_reason,
            status,
            start_date,
            end_date
        ) VALUES (
            v_donor_id,
            v_medical_history_id,
            v_screening_id,
            v_physical_exam_id,
            NEW.blood_collection_id,
            v_blood_type,
            v_donation_type,
            NEW.disapproval_reason,
            'disapproved',
            CURRENT_TIMESTAMP,
            v_end_date
        );
        
        RETURN NEW;
    END IF;

    -- Create new eligibility record with all form data
    INSERT INTO eligibility (
        donor_id,
        medical_history_id,
        screening_id,
        physical_exam_id,
        blood_collection_id,
        blood_type,
        donation_type,
        blood_bag_type,
        blood_bag_brand,
        amount_collected,
        collection_successful,
        donor_reaction,
        management_done,
        collection_start_time,
        collection_end_time,
        unit_serial_number,
        start_date,
        end_date,
        status
    ) VALUES (
        v_donor_id,
        v_medical_history_id,
        v_screening_id,
        v_physical_exam_id,
        NEW.blood_collection_id,
        v_blood_type,
        v_donation_type,
        NEW.blood_bag_type,
        NEW.blood_bag_brand,
        NEW.amount_taken,
        NEW.is_successful,
        NEW.donor_reaction,
        NEW.management_done,
        NEW.start_time,
        NEW.end_time,
        NEW.unit_serial_number,
        CURRENT_TIMESTAMP,
        v_end_date,
        CASE 
            WHEN NEW.is_successful THEN 'ineligible'
            ELSE 'failed_collection'
        END
    );

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create trigger to handle blood collection eligibility
CREATE TRIGGER blood_collection_eligibility_trigger
    AFTER INSERT ON blood_collection
    FOR EACH ROW
    EXECUTE FUNCTION handle_blood_collection_eligibility();

-- Create function to automatically update eligibility status
CREATE OR REPLACE FUNCTION update_eligibility_status()
RETURNS TRIGGER AS $$
BEGIN
    -- Update status to 'eligible' when end_date is reached
    UPDATE eligibility
    SET status = 'eligible',
        updated_at = CURRENT_TIMESTAMP
    WHERE end_date <= CURRENT_TIMESTAMP
    AND status = 'ineligible';
    
    -- Do not update disapproved or failed collection statuses
    -- They need to be handled separately through the approval process
    
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Create trigger to periodically check and update eligibility status
CREATE TRIGGER update_eligibility_status_trigger
    AFTER UPDATE ON eligibility
    FOR EACH STATEMENT
    EXECUTE FUNCTION update_eligibility_status();

-- Create function to get donor eligibility status with waiting period message
CREATE OR REPLACE FUNCTION get_eligibility_status(p_donor_id INTEGER)
RETURNS TABLE (
    is_eligible BOOLEAN,
    status_message TEXT,
    remaining_days INTEGER,
    end_date TIMESTAMP WITH TIME ZONE
) AS $$
DECLARE
    v_latest_eligibility eligibility%ROWTYPE;
    v_days_remaining INTEGER;
    v_years_remaining INTEGER;
    v_months_remaining INTEGER;
BEGIN
    -- First update any eligibility records that have passed their end_date
    UPDATE eligibility
    SET status = 'eligible',
        updated_at = CURRENT_TIMESTAMP
    WHERE end_date <= CURRENT_TIMESTAMP
    AND status = 'ineligible'
    AND donor_id = p_donor_id;

    -- Get the latest eligibility record for the donor
    SELECT *
    INTO v_latest_eligibility
    FROM eligibility
    WHERE donor_id = p_donor_id
    ORDER BY created_at DESC
    LIMIT 1;

    IF v_latest_eligibility IS NULL THEN
        -- No donation history found
        RETURN QUERY
        SELECT 
            TRUE as is_eligible,
            'Eligible to donate'::TEXT as status_message,
            0 as remaining_days,
            NULL::TIMESTAMP WITH TIME ZONE as end_date;
        RETURN;
    END IF;

    -- Calculate remaining time
    v_days_remaining := EXTRACT(DAY FROM (v_latest_eligibility.end_date - CURRENT_TIMESTAMP));
    v_months_remaining := EXTRACT(MONTH FROM (v_latest_eligibility.end_date - CURRENT_TIMESTAMP));
    v_years_remaining := EXTRACT(YEAR FROM (v_latest_eligibility.end_date - CURRENT_TIMESTAMP));

    IF v_latest_eligibility.status = 'disapproved' THEN
        -- Format message based on deferral period
        RETURN QUERY
        SELECT 
            FALSE as is_eligible,
            CASE 
                WHEN v_years_remaining >= 99 THEN 
                    'Permanently deferred: ' || COALESCE(v_latest_eligibility.disapproval_reason, 'No reason provided')
                WHEN v_years_remaining > 0 THEN
                    'Deferred for ' || v_years_remaining || ' years: ' || COALESCE(v_latest_eligibility.disapproval_reason, 'No reason provided')
                WHEN v_months_remaining > 0 THEN
                    'Deferred for ' || v_months_remaining || ' months: ' || COALESCE(v_latest_eligibility.disapproval_reason, 'No reason provided')
                ELSE
                    'Deferred for ' || v_days_remaining || ' days: ' || COALESCE(v_latest_eligibility.disapproval_reason, 'No reason provided')
            END::TEXT,
            v_days_remaining,
            v_latest_eligibility.end_date;
    ELSIF v_latest_eligibility.status = 'failed_collection' THEN
        RETURN QUERY
        SELECT 
            FALSE as is_eligible,
            'Previous collection incomplete. Please wait ' || v_days_remaining || ' days before next attempt.'::TEXT,
            v_days_remaining,
            v_latest_eligibility.end_date;
    ELSIF v_latest_eligibility.status = 'ineligible' THEN
        RETURN QUERY
        SELECT 
            FALSE,
            'Ineligible until 3 months from last donation (' || v_days_remaining || ' days remaining)'::TEXT,
            v_days_remaining,
            v_latest_eligibility.end_date;
    ELSE
        -- Status is 'eligible'
        RETURN QUERY
        SELECT 
            TRUE as is_eligible,
            'Eligible to donate'::TEXT,
            0,
            v_latest_eligibility.end_date;
    END IF;
END;
$$ LANGUAGE plpgsql; 

-- Drop existing trigger if it exists
DROP TRIGGER IF EXISTS handle_user_creation ON users;
DROP FUNCTION IF EXISTS handle_user_creation();

-- Create the trigger function
CREATE OR REPLACE FUNCTION handle_user_creation()
RETURNS TRIGGER AS $$
DECLARE
    new_user UUID;
BEGIN
    -- Insert into Supabase auth.users with hashed password
    INSERT INTO auth.users (id, email, encrypted_password)
    VALUES (
        NEW.user_id, 
        NEW.email, 
        crypt(NEW.password_hash, gen_salt('bf'))
    )
    RETURNING id INTO new_user;

    -- Ensure the user ID is not null before inserting into user_roles
    IF new_user IS NOT NULL THEN
        INSERT INTO user_roles (user_id, role_id)
        VALUES (new_user, NEW.role_id);
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Create the trigger
CREATE TRIGGER handle_user_creation
    AFTER INSERT ON users
    FOR EACH ROW
    EXECUTE FUNCTION handle_user_creation();

-- Grant necessary permissions
GRANT EXECUTE ON FUNCTION handle_user_creation() TO authenticated;
GRANT EXECUTE ON FUNCTION handle_user_creation() TO service_role; 
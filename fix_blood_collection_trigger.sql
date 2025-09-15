-- First, drop any existing triggers that might interfere
DROP TRIGGER IF EXISTS create_blood_bank_unit_trigger ON blood_collection;
DROP TRIGGER IF EXISTS blood_collection_after_trigger ON blood_collection;

-- Create a trigger that ONLY updates eligibility when blood collection is successful
CREATE OR REPLACE FUNCTION handle_blood_collection()
RETURNS TRIGGER AS $$
BEGIN
    -- Only proceed if collection is successful
    IF NEW.is_successful = true THEN
        -- Get donor_id from physical examination
        INSERT INTO eligibility (
            donor_id,
            blood_collection_id,
            status,
            start_date,
            end_date
        )
        SELECT 
            pe.donor_id,
            NEW.blood_collection_id,
            'Eligible',
            CURRENT_DATE,
            CURRENT_DATE + INTERVAL '3 months'
        FROM physical_examination pe
        WHERE pe.physical_exam_id = NEW.physical_exam_id
        ON CONFLICT (blood_collection_id) DO UPDATE
        SET 
            status = 'Eligible',
            start_date = CURRENT_DATE,
            end_date = CURRENT_DATE + INTERVAL '3 months',
            updated_at = CURRENT_TIMESTAMP;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create new trigger that only handles eligibility
CREATE TRIGGER blood_collection_after_trigger
    AFTER INSERT OR UPDATE ON blood_collection
    FOR EACH ROW
    EXECUTE FUNCTION handle_blood_collection();

-- Create a separate trigger on eligibility to handle blood bank units
CREATE OR REPLACE FUNCTION create_blood_bank_unit()
RETURNS TRIGGER AS $$
DECLARE
    v_blood_type TEXT;
    v_collection_record RECORD;
BEGIN
    -- Get blood type from screening form
    SELECT blood_type INTO v_blood_type
    FROM screening_form sf
    WHERE sf.donor_id = NEW.donor_id
    ORDER BY sf.created_at DESC
    LIMIT 1;

    -- Get blood collection details
    SELECT * INTO v_collection_record
    FROM blood_collection
    WHERE blood_collection_id = NEW.blood_collection_id;

    -- Only create blood bank unit if we have all required data
    IF v_blood_type IS NOT NULL AND v_collection_record.is_successful = true THEN
        INSERT INTO blood_bank_units (
            blood_collection_id,
            donor_id,
            blood_type,
            unit_serial_number,
            expiration_date,
            status
        ) VALUES (
            NEW.blood_collection_id,
            NEW.donor_id,
            v_blood_type,
            v_collection_record.unit_serial_number,
            CURRENT_DATE + INTERVAL '35 days',
            'Valid'
        )
        ON CONFLICT (blood_collection_id) DO UPDATE
        SET 
            blood_type = EXCLUDED.blood_type,
            unit_serial_number = EXCLUDED.unit_serial_number,
            expiration_date = EXCLUDED.expiration_date,
            updated_at = CURRENT_TIMESTAMP;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create trigger on eligibility table
CREATE TRIGGER create_blood_bank_unit_trigger
    AFTER INSERT OR UPDATE ON eligibility
    FOR EACH ROW
    EXECUTE FUNCTION create_blood_bank_unit();





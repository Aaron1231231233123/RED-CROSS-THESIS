-- Drop existing trigger first
DROP TRIGGER IF EXISTS create_blood_bank_unit_trigger ON blood_collection;

-- Function to get blood type from screening form through physical exam
CREATE OR REPLACE FUNCTION get_blood_type_for_collection()
RETURNS TRIGGER AS $$
DECLARE
    v_blood_type TEXT;
    v_donor_id UUID;
BEGIN
    -- Get donor_id from physical exam
    SELECT donor_id INTO v_donor_id
    FROM physical_examination
    WHERE physical_exam_id = NEW.physical_exam_id;

    -- Get blood type from most recent screening form for this donor
    SELECT blood_type INTO v_blood_type
    FROM screening_form
    WHERE donor_id = v_donor_id
    ORDER BY created_at DESC
    LIMIT 1;

    -- Only create blood bank unit if collection was successful
    IF NEW.is_successful = true THEN
        -- Create blood bank unit with blood type from screening form
        INSERT INTO blood_bank_units (
            blood_collection_id,
            donor_id,
            blood_type,
            unit_serial_number,
            expiration_date,
            status
        ) VALUES (
            NEW.blood_collection_id,
            v_donor_id,
            v_blood_type,
            NEW.unit_serial_number,
            (NEW.created_at + INTERVAL '35 days')::date,
            'Valid'
        );
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create new trigger
CREATE TRIGGER create_blood_bank_unit_trigger
    AFTER INSERT OR UPDATE ON blood_collection
    FOR EACH ROW
    EXECUTE FUNCTION get_blood_type_for_collection();

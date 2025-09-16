-- Function to get blood type from screening form
CREATE OR REPLACE FUNCTION get_blood_type_for_collection()
RETURNS TRIGGER AS $$
DECLARE
    v_blood_type TEXT;
BEGIN
    -- Get blood type from screening form
    SELECT blood_type INTO v_blood_type
    FROM screening_form
    WHERE screening_id = NEW.screening_id;

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
        NEW.donor_id,
        v_blood_type,
        NEW.unit_serial_number,
        (NEW.created_at + INTERVAL '35 days')::date,
        'Valid'
    );

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create trigger
DROP TRIGGER IF EXISTS create_blood_bank_unit_trigger ON blood_collection;
CREATE TRIGGER create_blood_bank_unit_trigger
    AFTER INSERT ON blood_collection
    FOR EACH ROW
    EXECUTE FUNCTION get_blood_type_for_collection();






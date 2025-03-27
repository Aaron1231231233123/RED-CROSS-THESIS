CREATE OR REPLACE FUNCTION get_approved_screenings()
RETURNS TABLE (
    screening_id bigint,
    interview_date date,
    blood_type text,
    donation_type text,
    donor_id bigint,
    surname text,
    first_name text
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        s.screening_id,
        s.interview_date,
        s.blood_type,
        s.donation_type,
        s.donor_id,
        d.surname,
        d.first_name
    FROM screening_form s
    JOIN donor_form d ON s.donor_id = d.donor_id
    WHERE s.disapproval_reason IS NULL
    AND s.physical_exam_id IS NULL
    ORDER BY s.interview_date DESC;
END;
$$ LANGUAGE plpgsql; 
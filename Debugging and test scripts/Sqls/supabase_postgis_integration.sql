-- =====================================================
-- POSTGIS INTEGRATION FOR RED CROSS BLOOD BANK SYSTEM
-- Run this in Supabase SQL editor to enable PostGIS
-- =====================================================

-- 1. Enable PostGIS extension
CREATE EXTENSION IF NOT EXISTS postgis;

-- 2. Add geography columns to donor_form table for spatial indexing
ALTER TABLE donor_form 
ADD COLUMN IF NOT EXISTS permanent_latitude DECIMAL(10, 8),
ADD COLUMN IF NOT EXISTS permanent_longitude DECIMAL(11, 8),
ADD COLUMN IF NOT EXISTS permanent_geom GEOGRAPHY(Point, 4326);

ALTER TABLE donor_form
ADD COLUMN IF NOT EXISTS office_latitude DECIMAL(10, 8),
ADD COLUMN IF NOT EXISTS office_longitude DECIMAL(11, 8),
ADD COLUMN IF NOT EXISTS office_geom GEOGRAPHY(Point, 4326);

-- 3. Create spatial indexes for fast location queries
CREATE INDEX IF NOT EXISTS donor_form_permanent_geom_idx 
ON donor_form 
USING GIST (permanent_geom);

CREATE INDEX IF NOT EXISTS donor_form_office_geom_idx 
ON donor_form 
USING GIST (office_geom);

-- 4. Function to update geography from lat/lon coordinates
CREATE OR REPLACE FUNCTION update_donor_geography()
RETURNS TRIGGER AS $$
BEGIN
    -- Update permanent address geography
    IF NEW.permanent_latitude IS NOT NULL AND NEW.permanent_longitude IS NOT NULL THEN
        NEW.permanent_geom = ST_SetSRID(ST_MakePoint(NEW.permanent_longitude, NEW.permanent_latitude), 4326)::geography;
    END IF;
    
    -- Update office address geography
    IF NEW.office_latitude IS NOT NULL AND NEW.office_longitude IS NOT NULL THEN
        NEW.office_geom = ST_SetSRID(ST_MakePoint(NEW.office_longitude, NEW.office_latitude), 4326)::geography;
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 5. Create trigger to automatically update geography when coordinates change
DROP TRIGGER IF EXISTS update_donor_geography_trigger ON donor_form;
CREATE TRIGGER update_donor_geography_trigger
    BEFORE INSERT OR UPDATE ON donor_form
    FOR EACH ROW
    EXECUTE FUNCTION update_donor_geography();

-- 6. View for easy access to geocoded donor data
CREATE OR REPLACE VIEW donor_locations AS
SELECT 
    df.donor_id,
    df.permanent_address,
    df.office_address,
    df.permanent_latitude,
    df.permanent_longitude,
    df.office_latitude,
    df.office_longitude,
    df.permanent_geom,
    df.office_geom,
    CASE 
        WHEN df.permanent_geom IS NOT NULL THEN 'permanent'
        WHEN df.office_geom IS NOT NULL THEN 'office'
        ELSE 'none'
    END as location_source
FROM donor_form df;

-- 7. Indexes for common queries
CREATE INDEX IF NOT EXISTS donor_form_donor_id_idx ON donor_form(donor_id);
CREATE INDEX IF NOT EXISTS blood_bank_units_donor_id_idx ON blood_bank_units(donor_id);
CREATE INDEX IF NOT EXISTS blood_bank_units_status_idx ON blood_bank_units(status);

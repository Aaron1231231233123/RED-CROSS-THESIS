-- =====================================================
-- POSTGIS OPTIMIZATION FOR RED CROSS BLOOD BANK SYSTEM
-- =====================================================

-- 1. Enable PostGIS extension
CREATE EXTENSION IF NOT EXISTS postgis;

-- 2. Add geography columns to donor_form table for spatial indexing
-- (This stores the geocoded coordinates from permanent_address and office_address)

-- Add columns for permanent address coordinates
ALTER TABLE donor_form 
ADD COLUMN IF NOT EXISTS permanent_latitude DECIMAL(10, 8),
ADD COLUMN IF NOT EXISTS permanent_longitude DECIMAL(11, 8),
ADD COLUMN IF NOT EXISTS permanent_geom GEOGRAPHY(Point, 4326);

-- Add columns for office address coordinates  
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

-- 6. Sample queries for optimized location search

-- Query 1: Find donors within 10km of a specific location (e.g., Iloilo City center)
-- Usage: Replace 122.5621, 10.7202 with your search coordinates
/*
SELECT 
    df.donor_id,
    df.permanent_address,
    df.office_address,
    df.permanent_latitude,
    df.permanent_longitude,
    ST_Distance(df.permanent_geom, ST_MakePoint(122.5621, 10.7202)::geography) as distance_meters
FROM donor_form df
WHERE df.permanent_geom IS NOT NULL
    AND ST_DWithin(
        df.permanent_geom,
        ST_MakePoint(122.5621, 10.7202)::geography,
        10000  -- 10km radius
    )
ORDER BY ST_Distance(df.permanent_geom, ST_MakePoint(122.5621, 10.7202)::geography)
LIMIT 50;
*/

-- Query 2: Find donors by city/municipality (using spatial containment)
-- This finds donors within Iloilo City boundaries
/*
SELECT 
    df.donor_id,
    df.permanent_address,
    df.permanent_latitude,
    df.permanent_longitude
FROM donor_form df
WHERE df.permanent_geom IS NOT NULL
    AND ST_Within(
        df.permanent_geom::geometry,
        ST_GeomFromText('POLYGON((122.4 10.6, 122.7 10.6, 122.7 10.8, 122.4 10.8, 122.4 10.6))', 4326)
    )
ORDER BY df.donor_id;
*/

-- Query 3: Get donor density by area (for heatmap data)
-- This groups donors by 1km grid cells
/*
SELECT 
    ST_AsText(ST_Centroid(ST_Collect(df.permanent_geom::geometry))) as center_point,
    COUNT(*) as donor_count,
    ST_X(ST_Centroid(ST_Collect(df.permanent_geom::geometry))) as center_lng,
    ST_Y(ST_Centroid(ST_Collect(df.permanent_geom::geometry))) as center_lat
FROM donor_form df
WHERE df.permanent_geom IS NOT NULL
GROUP BY ST_SnapToGrid(df.permanent_geom::geometry, 0.01, 0.01)  -- ~1km grid
HAVING COUNT(*) > 0
ORDER BY donor_count DESC;
*/

-- Query 4: Find nearest blood donors to a hospital/emergency location
-- Usage: Replace coordinates with hospital location
/*
SELECT 
    df.donor_id,
    df.permanent_address,
    ST_Distance(df.permanent_geom, ST_MakePoint(122.5621, 10.7202)::geography) as distance_meters,
    bbu.blood_type,
    bbu.status
FROM donor_form df
JOIN blood_bank_units bbu ON df.donor_id = bbu.donor_id
WHERE df.permanent_geom IS NOT NULL
    AND bbu.status = 'Valid'  -- Only available blood
    AND ST_DWithin(
        df.permanent_geom,
        ST_MakePoint(122.5621, 10.7202)::geography,
        50000  -- 50km radius
    )
ORDER BY ST_Distance(df.permanent_geom, ST_MakePoint(122.5621, 10.7202)::geography)
LIMIT 20;
*/

-- 7. Function to geocode and store coordinates (for batch processing)
CREATE OR REPLACE FUNCTION geocode_donor_address(donor_id_param TEXT)
RETURNS JSON AS $$
DECLARE
    donor_record RECORD;
    result JSON;
BEGIN
    -- Get donor record
    SELECT * INTO donor_record 
    FROM donor_form 
    WHERE donor_form.donor_id = donor_id_param;
    
    IF NOT FOUND THEN
        RETURN json_build_object('error', 'Donor not found');
    END IF;
    
    -- Here you would call your geocoding service
    -- For now, return the donor info for manual geocoding
    result := json_build_object(
        'donor_id', donor_record.donor_id,
        'permanent_address', donor_record.permanent_address,
        'office_address', donor_record.office_address,
        'needs_geocoding', 
        CASE 
            WHEN donor_record.permanent_latitude IS NULL THEN 'permanent_address'
            WHEN donor_record.office_latitude IS NULL THEN 'office_address'
            ELSE 'none'
        END
    );
    
    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- 8. View for easy access to geocoded donor data
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

-- 9. Indexes for common queries
CREATE INDEX IF NOT EXISTS donor_form_donor_id_idx ON donor_form(donor_id);
CREATE INDEX IF NOT EXISTS blood_bank_units_donor_id_idx ON blood_bank_units(donor_id);
CREATE INDEX IF NOT EXISTS blood_bank_units_status_idx ON blood_bank_units(status);

-- =====================================================
-- USAGE INSTRUCTIONS:
-- =====================================================

-- 1. Run this entire script in Supabase SQL editor
-- 2. Update your geocoding process to store lat/lng in the new columns
-- 3. Use the optimized queries for location-based searches
-- 4. The spatial indexes will make location queries extremely fast

-- Example of updating coordinates after geocoding:
-- UPDATE donor_form 
-- SET permanent_latitude = 10.7202, permanent_longitude = 122.5621
-- WHERE donor_id = 'your_donor_id';

-- The trigger will automatically update the geography column!

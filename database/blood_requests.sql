-- Create enum type for request status
CREATE TYPE request_status AS ENUM (
    'Pending',
    'Approved'
);


-- Create enum type for blood components
CREATE TYPE blood_component AS ENUM (
    'Whole Blood',
    'Platelet Concentrate',
    'Fresh Frozen Plasma',
    'Packed Red Blood Cells'
);

-- Create enum type for RH factor
CREATE TYPE rh_factor AS ENUM (
    'Positive',
    'Negative'
);

-- Create table for blood requests
CREATE TABLE blood_requests (
    request_id SERIAL PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(user_id),
    patient_name VARCHAR(100) NOT NULL,
    patient_age INTEGER NOT NULL,
    patient_gender VARCHAR(10) NOT NULL,
    patient_diagnosis TEXT NOT NULL,
    patient_blood_type VARCHAR(3) NOT NULL,
    rh_factor rh_factor NOT NULL,
    component blood_component NOT NULL,
    units_requested INTEGER NOT NULL,
    is_asap BOOLEAN DEFAULT false,
    when_needed TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    hospital_admitted VARCHAR(100) NOT NULL,
    physician_name VARCHAR(100) NOT NULL,
    physician_signature TEXT,  -- Stores the base64 signature image data
    status request_status DEFAULT 'Pending',
    requested_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


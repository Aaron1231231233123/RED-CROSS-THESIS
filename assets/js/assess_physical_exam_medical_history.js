/**
 * Assessment functions for Physical Examination Results
 * Used in dashboard-staff-medical-history-submissions.php
 */

/**
 * Assess Physical Exam Notes (Skin, HEENT, Lungs, etc.)
 * 
 * @param {Object} data Physical examination data with keys: skin, heent, heart_and_lungs, gen_appearance
 * @returns {string} Assessment result: "Normal", "Normal with mild abnormalities", or "With major abnormalities"
 */
function assessPhysicalExamNotes(data) {
    const exams = {
        skin: (data.skin || '').toLowerCase().trim(),
        heent: (data.heent || '').toLowerCase().trim(),
        heart_and_lungs: (data.heart_and_lungs || '').toLowerCase().trim(),
        gen_appearance: (data.gen_appearance || '').toLowerCase().trim()
    };
    
    // Normal keywords that indicate a normal finding
    const normalKeywords = ['normal', 'clear', 'unremarkable', 'no abnormalities', 'no abnormal findings', 
                      'healthy', 'good', 'regular', 'appropriate', 'within normal limits', 'wnl'];
    
    // Abnormal keywords
    const abnormalKeywords = ['abnormal', 'irregular', 'decreased', 'increased', 'abnormality', 'anomaly',
                        'disorder', 'disease', 'infection', 'inflammation', 'lesion', 'rash', 
                        'tenderness', 'swelling', 'mass', 'tumor', 'tachycardia', 'bradycardia',
                        'hypertension', 'hypotension', 'fever', 'hypothermia', 'dyspnea', 'wheezing'];
    
    let normalCount = 0;
    let abnormalCount = 0;
    let totalExams = 0;
    
    for (const [key, value] of Object.entries(exams)) {
        if (!value) {
            continue; // Skip empty values
        }
        
        totalExams++;
        let isNormal = false;
        let isAbnormal = false;
        
        // Check for normal keywords
        for (const keyword of normalKeywords) {
            if (value.includes(keyword)) {
                isNormal = true;
                break;
            }
        }
        
        // Check for abnormal keywords
        for (const keyword of abnormalKeywords) {
            if (value.includes(keyword)) {
                isAbnormal = true;
                break;
            }
        }
        
        // If explicit abnormal keyword found, count as abnormal
        if (isAbnormal) {
            abnormalCount++;
        } else if (isNormal) {
            normalCount++;
        } else {
            // If neither normal nor abnormal keywords found, consider it potentially abnormal
            // but not definitively - count as neutral/normal to be conservative
            normalCount++;
        }
    }
    
    if (totalExams === 0) {
        return 'N/A';
    }
    
    // All normal = "Normal"
    if (abnormalCount === 0) {
        return 'Normal';
    }
    
    // Calculate percentage abnormal
    const abnormalPercentage = (abnormalCount / totalExams) * 100;
    
    // Most abnormal (>= 50%) = "With major abnormalities"
    if (abnormalPercentage >= 50) {
        return 'With major abnormalities';
    }
    
    // 1-2 abnormal (1-2 out of total) = "Normal with mild abnormalities"
    if (abnormalCount >= 1 && abnormalCount <= 2) {
        return 'Normal with mild abnormalities';
    }
    
    // Default to major if more than 2
    return 'With major abnormalities';
}

/**
 * Assess Blood Pressure
 * 
 * @param {string} bloodPressure BP value in format "systolic/diastolic" (e.g., "120/80")
 * @returns {string} Assessment result
 */
function assessBloodPressure(bloodPressure) {
    if (!bloodPressure || bloodPressure === 'N/A') {
        return 'N/A';
    }
    
    // Parse BP format (e.g., "120/80" or "120 / 80")
    const bp = bloodPressure.replace(/\s+/g, '');
    const parts = bp.split('/');
    
    if (parts.length !== 2) {
        return 'N/A';
    }
    
    const systolic = parseInt(parts[0], 10);
    const diastolic = parseInt(parts[1], 10);
    
    if (systolic <= 0 || diastolic <= 0 || isNaN(systolic) || isNaN(diastolic)) {
        return 'N/A';
    }
    
    // Normal: ≤140/90 mmHg
    if (systolic <= 140 && diastolic <= 90) {
        // Check if it's elevated (121-139/81-89) vs truly normal (≤120/80)
        if (systolic >= 121 && systolic <= 139 && diastolic >= 81 && diastolic <= 89) {
            return 'Elevated';
        }
        // Low: <90/60
        if (systolic < 90 || diastolic < 60) {
            return 'Low (Hypotension)';
        }
        return 'Normal (≤140/90 mmHg)';
    }
    
    // High: >140/90
    return 'High (Hypertension)';
}

/**
 * Assess Weight
 * 
 * @param {number|string} weight Weight in kg
 * @returns {string} Assessment result
 */
function assessWeight(weight) {
    if (!weight || weight === 'N/A' || weight === null) {
        return 'N/A';
    }
    
    const weightValue = parseFloat(weight);
    
    if (weightValue <= 0 || isNaN(weightValue)) {
        return 'N/A';
    }
    
    if (weightValue >= 50) {
        return '≥ 50 kg (Eligible)';
    }
    
    return '< 50 kg (Below Minimum – Defer)';
}

/**
 * Assess Pulse Rate
 * 
 * @param {number|string} pulseRate Pulse rate in bpm
 * @param {string} pulseQuality Optional pulse quality/regularity note
 * @returns {string} Assessment result
 */
function assessPulse(pulseRate, pulseQuality = '') {
    if (!pulseRate || pulseRate === 'N/A' || pulseRate === null) {
        return 'N/A';
    }
    
    const pulseValue = parseInt(pulseRate, 10);
    
    if (pulseValue <= 0 || isNaN(pulseValue)) {
        return 'N/A';
    }
    
    // Check for irregularity in pulse quality if provided
    const pulseQualityLower = (pulseQuality || '').toLowerCase().trim();
    if (pulseQuality && (
        pulseQualityLower.includes('irregular') ||
        pulseQualityLower.includes('arrhythmia') ||
        pulseQualityLower.includes('irregularity')
    )) {
        return 'Irregular';
    }
    
    // Tachycardia: >100 bpm
    if (pulseValue > 100) {
        return 'Tachycardia (>100 bpm)';
    }
    
    // Bradycardia: <60 bpm
    if (pulseValue < 60) {
        return 'Bradycardia (<60 bpm)';
    }
    
    // Normal: 60-100 bpm
    return 'Normal (60–100 bpm)';
}

/**
 * Assess Body Temperature
 * 
 * @param {number|string} temperature Temperature in Celsius
 * @returns {string} Assessment result
 */
function assessTemperature(temperature) {
    if (!temperature || temperature === 'N/A' || temperature === null) {
        return 'N/A';
    }
    
    const tempValue = parseFloat(temperature);
    
    if (tempValue <= 0 || tempValue > 50 || isNaN(tempValue)) {
        return 'N/A';
    }
    
    // Normal: 36.5°C – 37.5°C
    if (tempValue >= 36.5 && tempValue <= 37.5) {
        return 'Normal (36.5°C – 37.5°C)';
    }
    
    // Low: <36.5°C
    if (tempValue < 36.5) {
        return 'Low';
    }
    
    // Elevated: >37.5°C
    return 'Elevated (>37.5°C)';
}

/**
 * Get comprehensive assessment for all physical examination parameters
 * 
 * @param {Object} data Physical examination data
 * @returns {Object} Assessment results for all parameters
 */
function getPhysicalExaminationAssessment(data) {
    return {
        physical_exam_notes: assessPhysicalExamNotes(data),
        blood_pressure: assessBloodPressure(data.blood_pressure),
        weight: assessWeight(data.body_weight),
        pulse: assessPulse(data.pulse_rate, data.pulse_quality),
        temperature: assessTemperature(data.body_temp)
    };
}


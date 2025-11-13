<?php
/**
 * Assessment functions for Physical Examination Results
 * Used in dashboard-staff-medical-history-submissions.php
 */

/**
 * Assess Physical Exam Notes (Skin, HEENT, Lungs, etc.)
 * 
 * @param array $data Physical examination data with keys: skin, heent, heart_and_lungs, gen_appearance
 * @return string Assessment result: "Normal", "Normal with mild abnormalities", or "With major abnormalities"
 */
function assessPhysicalExamNotes($data) {
    $exams = [
        'skin' => strtolower(trim($data['skin'] ?? '')),
        'heent' => strtolower(trim($data['heent'] ?? '')),
        'heart_and_lungs' => strtolower(trim($data['heart_and_lungs'] ?? '')),
        'gen_appearance' => strtolower(trim($data['gen_appearance'] ?? ''))
    ];
    
    // Normal keywords that indicate a normal finding
    $normalKeywords = ['normal', 'clear', 'unremarkable', 'no abnormalities', 'no abnormal findings', 
                      'healthy', 'good', 'regular', 'appropriate', 'within normal limits', 'wnl'];
    
    // Abnormal keywords
    $abnormalKeywords = ['abnormal', 'irregular', 'decreased', 'increased', 'abnormality', 'anomaly',
                        'disorder', 'disease', 'infection', 'inflammation', 'lesion', 'rash', 
                        'tenderness', 'swelling', 'mass', 'tumor', 'tachycardia', 'bradycardia',
                        'hypertension', 'hypotension', 'fever', 'hypothermia', 'dyspnea', 'wheezing'];
    
    $normalCount = 0;
    $abnormalCount = 0;
    $totalExams = 0;
    
    foreach ($exams as $key => $value) {
        if (empty($value)) {
            continue; // Skip empty values
        }
        
        $totalExams++;
        $isNormal = false;
        $isAbnormal = false;
        
        // Check for normal keywords
        foreach ($normalKeywords as $keyword) {
            if (strpos($value, $keyword) !== false) {
                $isNormal = true;
                break;
            }
        }
        
        // Check for abnormal keywords
        foreach ($abnormalKeywords as $keyword) {
            if (strpos($value, $keyword) !== false) {
                $isAbnormal = true;
                break;
            }
        }
        
        // If explicit abnormal keyword found, count as abnormal
        if ($isAbnormal) {
            $abnormalCount++;
        } elseif ($isNormal) {
            $normalCount++;
        } else {
            // If neither normal nor abnormal keywords found, consider it potentially abnormal
            // but not definitively - count as neutral/normal to be conservative
            $normalCount++;
        }
    }
    
    if ($totalExams === 0) {
        return 'N/A';
    }
    
    // All normal = "Normal"
    if ($abnormalCount === 0) {
        return 'Normal';
    }
    
    // Calculate percentage abnormal
    $abnormalPercentage = ($abnormalCount / $totalExams) * 100;
    
    // Most abnormal (>= 50%) = "With major abnormalities"
    if ($abnormalPercentage >= 50) {
        return 'With major abnormalities';
    }
    
    // 1-2 abnormal (1-2 out of total) = "Normal with mild abnormalities"
    if ($abnormalCount >= 1 && $abnormalCount <= 2) {
        return 'Normal with mild abnormalities';
    }
    
    // Default to major if more than 2
    return 'With major abnormalities';
}

/**
 * Assess Blood Pressure
 * 
 * @param string $bloodPressure BP value in format "systolic/diastolic" (e.g., "120/80")
 * @return string Assessment result
 */
function assessBloodPressure($bloodPressure) {
    if (empty($bloodPressure)) {
        return 'N/A';
    }
    
    // Parse BP format (e.g., "120/80" or "120 / 80")
    $bp = preg_replace('/\s+/', '', $bloodPressure);
    $parts = explode('/', $bp);
    
    if (count($parts) !== 2) {
        return 'N/A';
    }
    
    $systolic = intval($parts[0]);
    $diastolic = intval($parts[1]);
    
    if ($systolic <= 0 || $diastolic <= 0) {
        return 'N/A';
    }
    
    // Normal: ≤140/90 mmHg
    if ($systolic <= 140 && $diastolic <= 90) {
        // Check if it's elevated (121-139/81-89) vs truly normal (≤120/80)
        if ($systolic >= 121 && $systolic <= 139 && $diastolic >= 81 && $diastolic <= 89) {
            return 'Elevated';
        }
        // Low: <90/60
        if ($systolic < 90 || $diastolic < 60) {
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
 * @param float|string $weight Weight in kg
 * @return string Assessment result
 */
function assessWeight($weight) {
    if (empty($weight)) {
        return 'N/A';
    }
    
    $weightValue = floatval($weight);
    
    if ($weightValue <= 0) {
        return 'N/A';
    }
    
    if ($weightValue >= 50) {
        return '≥ 50 kg (Eligible)';
    }
    
    return '< 50 kg (Below Minimum – Defer)';
}

/**
 * Assess Pulse Rate
 * 
 * @param int|string $pulseRate Pulse rate in bpm
 * @param string $pulseQuality Optional pulse quality/regularity note
 * @return string Assessment result
 */
function assessPulse($pulseRate, $pulseQuality = '') {
    if (empty($pulseRate) || $pulseRate === 'N/A' || $pulseRate === null) {
        return 'N/A';
    }
    
    $pulseValue = intval($pulseRate);
    
    if ($pulseValue <= 0) {
        return 'N/A';
    }
    
    // Check for irregularity in pulse quality if provided
    $pulseQualityLower = strtolower(trim($pulseQuality));
    if (!empty($pulseQuality) && (
        strpos($pulseQualityLower, 'irregular') !== false ||
        strpos($pulseQualityLower, 'arrhythmia') !== false ||
        strpos($pulseQualityLower, 'irregularity') !== false
    )) {
        return 'Irregular';
    }
    
    // Tachycardia: >100 bpm
    if ($pulseValue > 100) {
        return 'Tachycardia (>100 bpm)';
    }
    
    // Bradycardia: <60 bpm
    if ($pulseValue < 60) {
        return 'Bradycardia (<60 bpm)';
    }
    
    // Normal: 60-100 bpm
    return 'Normal (60–100 bpm)';
}

/**
 * Assess Body Temperature
 * 
 * @param float|string $temperature Temperature in Celsius
 * @return string Assessment result
 */
function assessTemperature($temperature) {
    if (empty($temperature) || $temperature === 'N/A' || $temperature === null) {
        return 'N/A';
    }
    
    $tempValue = floatval($temperature);
    
    if ($tempValue <= 0 || $tempValue > 50) {
        return 'N/A';
    }
    
    // Normal: 36.5°C – 37.5°C
    if ($tempValue >= 36.5 && $tempValue <= 37.5) {
        return 'Normal (36.5°C – 37.5°C)';
    }
    
    // Low: <36.5°C
    if ($tempValue < 36.5) {
        return 'Low';
    }
    
    // Elevated: >37.5°C
    return 'Elevated (>37.5°C)';
}

/**
 * Get comprehensive assessment for all physical examination parameters
 * 
 * @param array $data Physical examination data
 * @return array Assessment results for all parameters
 */
function getPhysicalExaminationAssessment($data) {
    return [
        'physical_exam_notes' => assessPhysicalExamNotes($data),
        'blood_pressure' => assessBloodPressure($data['blood_pressure'] ?? ''),
        'weight' => assessWeight($data['body_weight'] ?? ''),
        'pulse' => assessPulse($data['pulse_rate'] ?? '', $data['pulse_quality'] ?? ''),
        'temperature' => assessTemperature($data['body_temp'] ?? '')
    ];
}

// Handle direct API calls if needed
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'assess') {
    header('Content-Type: application/json');
    
    $data = json_decode($_GET['data'] ?? '{}', true);
    
    if (empty($data)) {
        echo json_encode(['success' => false, 'message' => 'No data provided']);
        exit;
    }
    
    $assessment = getPhysicalExaminationAssessment($data);
    
    echo json_encode([
        'success' => true,
        'assessment' => $assessment
    ]);
    exit;
}
?>











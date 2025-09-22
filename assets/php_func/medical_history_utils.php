<?php
/**
 * Medical History Utility Functions
 * Shared functions for medical history form processing
 */

/**
 * Get field name based on question number for medical history forms
 * @param int $count Question number (1-37)
 * @return string|null Field name or null if not found
 */
function getMedicalHistoryFieldName($count) {
    $fields = [
        1 => 'feels_well', 2 => 'previously_refused', 3 => 'testing_purpose_only', 4 => 'understands_transmission_risk',
        5 => 'recent_alcohol_consumption', 6 => 'recent_aspirin', 7 => 'recent_medication', 8 => 'recent_donation',
        9 => 'zika_travel', 10 => 'zika_contact', 11 => 'zika_sexual_contact', 12 => 'blood_transfusion',
        13 => 'surgery_dental', 14 => 'tattoo_piercing', 15 => 'risky_sexual_contact', 16 => 'unsafe_sex',
        17 => 'hepatitis_contact', 18 => 'imprisonment', 19 => 'uk_europe_stay', 20 => 'foreign_travel',
        21 => 'drug_use', 22 => 'clotting_factor', 23 => 'positive_disease_test', 24 => 'malaria_history',
        25 => 'std_history', 26 => 'cancer_blood_disease', 27 => 'heart_disease', 28 => 'lung_disease',
        29 => 'kidney_disease', 30 => 'chicken_pox', 31 => 'chronic_illness', 32 => 'recent_fever',
        33 => 'pregnancy_history', 34 => 'last_childbirth', 35 => 'recent_miscarriage', 36 => 'breastfeeding',
        37 => 'last_menstruation'
    ];
    return $fields[$count] ?? null;
}
?>

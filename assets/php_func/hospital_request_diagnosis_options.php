<?php
/**
 * Hospital Request Diagnosis Options
 * Provides diagnosis dropdown options with urgency mapping for blood requests
 */

/**
 * Get diagnosis options with urgency classification
 * @return array Array of diagnosis options with 'label' and 'urgency' keys
 */
function getHospitalRequestDiagnosisOptions() {
    return [
        [
            'value' => 'Anemia (Severe / Chronic)',
            'label' => 'Anemia (Severe / Chronic)',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Thalassemia',
            'label' => 'Thalassemia',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Leukemia',
            'label' => 'Leukemia',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Platelet Deficiency (Thrombocytopenia)',
            'label' => 'Platelet Deficiency (Thrombocytopenia)',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Coagulation Disorder (Hemophilia / DIC)',
            'label' => 'Coagulation Disorder (Hemophilia / DIC)',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Acute Blood Loss / Hemorrhage',
            'label' => 'Acute Blood Loss / Hemorrhage',
            'urgency' => 'urgent'
        ],
        [
            'value' => 'Preoperative / Surgical Blood Loss',
            'label' => 'Preoperative / Surgical Blood Loss',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Trauma / Accident',
            'label' => 'Trauma / Car Accident',
            'urgency' => 'urgent'
        ],
        [
            'value' => 'Gastrointestinal Bleeding (GI Bleed)',
            'label' => 'Gastrointestinal Bleeding (GI Bleed)',
            'urgency' => 'urgent'
        ],
        [
            'value' => 'Postpartum Hemorrhage (PPH)',
            'label' => 'Postpartum Hemorrhage',
            'urgency' => 'urgent'
        ],
        [
            'value' => 'Obstetric Complication (Ectopic Pregnancy / Placental Abruption)',
            'label' => 'Complicated Labor / Antepartum Hemorrhage',
            'urgency' => 'urgent'
        ],
        [
            'value' => 'Pediatric / Neonatal Transfusion',
            'label' => 'Neonatal Emergency / Severe Anemia',
            'urgency' => 'urgent'
        ],
        [
            'value' => 'Sepsis / Septic Shock',
            'label' => 'Septic Shock with Bleeding',
            'urgency' => 'urgent'
        ],
        [
            'value' => 'Cancer / Chemotherapy-Induced Anemia',
            'label' => 'Cancer / Chemotherapy-Induced Anemia',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Kidney Failure / Chronic Renal Disease',
            'label' => 'Kidney Failure / Chronic Renal Disease',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Liver Disease / Cirrhosis',
            'label' => 'Liver Disease / Cirrhosis',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Cardiac Surgery',
            'label' => 'Cardiac Surgery',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Burns with Blood Loss',
            'label' => 'Burns / Massive Tissue Injury (Supportive)',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Plasma Exchange / Fresh Frozen Plasma Requirement',
            'label' => 'Plasma Exchange / Fresh Frozen Plasma Requirement',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Other',
            'label' => 'Other (Specify)',
            'urgency' => 'non-urgent'
        ],
        // Additional urgent diagnoses from the label list
        [
            'value' => 'Mass Casualty / Natural Disaster',
            'label' => 'Mass Casualty / Natural Disaster (Typhoon, Earthquake, Flood)',
            'urgency' => 'urgent'
        ],
        [
            'value' => 'Emergency Surgery / Ruptured Aneurysm',
            'label' => 'Emergency Surgery / Ruptured Aneurysm',
            'urgency' => 'urgent'
        ],
        [
            'value' => 'Severe Hemolytic Crisis',
            'label' => 'Severe Hemolytic Crisis (Sickle Cell, Hemolysis)',
            'urgency' => 'urgent'
        ],
        // Additional non-urgent diagnoses from the label list
        [
            'value' => 'Chronic Anemia (Iron Deficiency, Chronic Disease)',
            'label' => 'Chronic Anemia (Iron Deficiency, Chronic Disease)',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Sickle Cell Disease (Non-Crisis)',
            'label' => 'Sickle Cell Disease (Non-Crisis)',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Aplastic Anemia / Bone Marrow Failure',
            'label' => 'Aplastic Anemia / Bone Marrow Failure',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Major Surgery (Elective)',
            'label' => 'Major Surgery (Elective)',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Myelodysplastic Syndromes',
            'label' => 'Myelodysplastic Syndromes',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Coagulopathy (Non-Critical)',
            'label' => 'Coagulopathy (Non-Critical)',
            'urgency' => 'non-urgent'
        ],
        [
            'value' => 'Anemia in Pregnancy (Non-Critical)',
            'label' => 'Anemia in Pregnancy (Non-Critical)',
            'urgency' => 'non-urgent'
        ]
    ];
}

/**
 * Get urgency for a specific diagnosis
 * @param string $diagnosis The diagnosis value
 * @return string 'urgent' or 'non-urgent'
 */
function getDiagnosisUrgency($diagnosis) {
    $options = getHospitalRequestDiagnosisOptions();
    foreach ($options as $option) {
        if ($option['value'] === $diagnosis) {
            return $option['urgency'];
        }
    }
    // Default to non-urgent if not found
    return 'non-urgent';
}

/**
 * Render diagnosis dropdown HTML
 * @param string $selectedValue Currently selected value (optional)
 * @param string $name Field name (default: 'patient_diagnosis')
 * @param string $id Field ID (default: 'patient_diagnosis')
 * @param bool $includeOther Specify if "Other" option should be included (default: true)
 * @return string HTML string for the dropdown
 */
function renderDiagnosisDropdown($selectedValue = '', $name = 'patient_diagnosis', $id = 'patient_diagnosis', $includeOther = true) {
    $options = getHospitalRequestDiagnosisOptions();
    $urgentOptions = [];
    $nonUrgentOptions = [];
    foreach ($options as $option) {
        if (!$includeOther && $option['value'] === 'Other') {
            continue;
        }
        if ($option['urgency'] === 'urgent') {
            $urgentOptions[] = $option;
        } else {
            $nonUrgentOptions[] = $option;
        }
    }
    $orderedOptions = array_merge($urgentOptions, $nonUrgentOptions);
    $html = '<select class="form-select" name="' . htmlspecialchars($name) . '" id="' . htmlspecialchars($id) . '" required>';
    $html .= '<option value="">Select Diagnosis</option>';
    foreach ($orderedOptions as $option) {
        $selected = ($selectedValue === $option['value']) ? ' selected' : '';
        $prefix = ($option['urgency'] === 'urgent') ? '[!]' : '[ ]';
        $label = $prefix . ' ' . $option['label'];
        $html .= '<option value="' . htmlspecialchars($option['value']) . '" data-urgency="' . htmlspecialchars($option['urgency']) . '"' . $selected . '>';
        $html .= htmlspecialchars($label);
        $html .= '</option>';
    }
    $html .= '</select>';
    return $html;
}
?>


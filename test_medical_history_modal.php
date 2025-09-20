<?php
// Test file to check medical history modal content
echo "<h2>Testing Medical History Modal Content</h2>";

// Test with a sample donor ID
$test_donor_id = '162'; // Using the donor ID from the error

echo "<p>Testing with Donor ID: $test_donor_id</p>";

// Test the modal content URL
$modal_url = "http://localhost/REDCROSS/src/views/forms/medical-history-modal-content.php?donor_id=" . urlencode($test_donor_id);

echo "<h3>Modal Content URL:</h3>";
echo "<p><a href='$modal_url' target='_blank'>$modal_url</a></p>";

// Test the modal content
echo "<h3>Modal Content Response:</h3>";
$response = file_get_contents($modal_url);
if ($response === false) {
    echo "<p style='color: red;'>Failed to fetch modal content</p>";
} else {
    echo "<h4>Raw Response Length:</h4>";
    echo "<p>Response length: " . strlen($response) . " characters</p>";
    
    // Check if response contains expected elements
    echo "<h4>Content Analysis:</h4>";
    echo "<ul>";
    echo "<li>Contains 'HEALTH & RISK ASSESSMENT': " . (strpos($response, 'HEALTH & RISK ASSESSMENT') !== false ? 'Yes' : 'No') . "</li>";
    echo "<li>Contains 'Do you feel well and healthy today?': " . (strpos($response, 'Do you feel well and healthy today?') !== false ? 'Yes' : 'No') . "</li>";
    echo "<li>Contains 'modalData': " . (strpos($response, 'modalData') !== false ? 'Yes' : 'No') . "</li>";
    echo "<li>Contains 'renderMedicalHistoryQuestions': " . (strpos($response, 'renderMedicalHistoryQuestions') !== false ? 'Yes' : 'No') . "</li>";
    echo "<li>Contains JavaScript errors: " . (strpos($response, 'error') !== false ? 'Yes' : 'No') . "</li>";
    echo "</ul>";
    
    // Show first 1000 characters of response
    echo "<h4>First 1000 characters of response:</h4>";
    echo "<pre>" . htmlspecialchars(substr($response, 0, 1000)) . "...</pre>";
    
    // Check for PHP errors
    if (strpos($response, '<br /><b>') !== false) {
        echo "<p style='color: red;'>⚠️ Response contains PHP errors!</p>";
    }
    
    // Check if it's valid HTML
    if (strpos($response, '<!DOCTYPE') === false && strpos($response, '<html') === false) {
        echo "<p style='color: orange;'>⚠️ Response doesn't appear to be complete HTML</p>";
    }
}

// Also test if we can access the file directly
echo "<hr><h3>Direct File Access Test:</h3>";
$file_path = "src/views/forms/medical-history-modal-content.php";
if (file_exists($file_path)) {
    echo "<p>✅ File exists: $file_path</p>";
    echo "<p>File size: " . filesize($file_path) . " bytes</p>";
    echo "<p>Last modified: " . date('Y-m-d H:i:s', filemtime($file_path)) . "</p>";
} else {
    echo "<p style='color: red;'>❌ File not found: $file_path</p>";
}
?>

<?php
// Simple test to check medical history modal content
echo "<h2>Simple Medical History Modal Test</h2>";

// Test the modal content URL directly
$test_donor_id = '162';
$modal_url = "src/views/forms/medical-history-modal-content.php?donor_id=" . urlencode($test_donor_id);

echo "<h3>Testing Modal Content:</h3>";
echo "<p>Donor ID: $test_donor_id</p>";
echo "<p>Modal URL: <a href='$modal_url' target='_blank'>$modal_url</a></p>";

// Test if we can include the file directly
echo "<h3>Direct Include Test:</h3>";
try {
    // Set the GET parameter
    $_GET['donor_id'] = $test_donor_id;
    
    // Capture output
    ob_start();
    include $modal_url;
    $content = ob_get_clean();
    
    if ($content) {
        echo "<p style='color: green;'>✅ Successfully included modal content</p>";
        echo "<p>Content length: " . strlen($content) . " characters</p>";
        
        // Check for key elements
        $checks = [
            'HEALTH & RISK ASSESSMENT' => strpos($content, 'HEALTH & RISK ASSESSMENT') !== false,
            'Do you feel well and healthy today?' => strpos($content, 'Do you feel well and healthy today?') !== false,
            'modalData' => strpos($content, 'modalData') !== false,
            'renderMedicalHistoryQuestions' => strpos($content, 'renderMedicalHistoryQuestions') !== false,
            'form-container' => strpos($content, 'form-container') !== false
        ];
        
        echo "<h4>Content Analysis:</h4>";
        echo "<ul>";
        foreach ($checks as $check => $result) {
            $status = $result ? '✅' : '❌';
            echo "<li>$status $check</li>";
        }
        echo "</ul>";
        
        // Show a preview of the content
        echo "<h4>Content Preview (first 500 characters):</h4>";
        echo "<pre>" . htmlspecialchars(substr($content, 0, 500)) . "...</pre>";
        
    } else {
        echo "<p style='color: red;'>❌ No content returned</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error including file: " . $e->getMessage() . "</p>";
}

// Also test the file exists and is readable
echo "<h3>File System Test:</h3>";
$file_path = "src/views/forms/medical-history-modal-content.php";
if (file_exists($file_path)) {
    echo "<p>✅ File exists: $file_path</p>";
    echo "<p>File size: " . filesize($file_path) . " bytes</p>";
    echo "<p>Readable: " . (is_readable($file_path) ? 'Yes' : 'No') . "</p>";
} else {
    echo "<p style='color: red;'>❌ File not found: $file_path</p>";
}
?>

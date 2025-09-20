<!DOCTYPE html>
<html>
<head>
    <title>Medical History Modal Direct Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Medical History Modal Direct Test</h1>
    
    <div class="test-section">
        <h2>Test 1: Direct File Access</h2>
        <?php
        $test_donor_id = '162';
        $file_path = 'src/views/forms/medical-history-modal-content.php';
        
        echo "<p><strong>Testing file:</strong> $file_path</p>";
        echo "<p><strong>Donor ID:</strong> $test_donor_id</p>";
        
        if (file_exists($file_path)) {
            echo "<p class='success'>✅ File exists</p>";
            echo "<p class='info'>File size: " . filesize($file_path) . " bytes</p>";
            echo "<p class='info'>Last modified: " . date('Y-m-d H:i:s', filemtime($file_path)) . "</p>";
        } else {
            echo "<p class='error'>❌ File not found</p>";
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>Test 2: Include Test</h2>
        <?php
        try {
            // Set the GET parameter
            $_GET['donor_id'] = $test_donor_id;
            
            echo "<p class='info'>Attempting to include the file...</p>";
            
            // Capture output
            ob_start();
            include $file_path;
            $content = ob_get_clean();
            
            if ($content) {
                echo "<p class='success'>✅ Successfully included file</p>";
                echo "<p class='info'>Content length: " . strlen($content) . " characters</p>";
                
                // Check for key elements
                $checks = [
                    'HEALTH & RISK ASSESSMENT' => strpos($content, 'HEALTH & RISK ASSESSMENT') !== false,
                    'Do you feel well and healthy today?' => strpos($content, 'Do you feel well and healthy today?') !== false,
                    'modalData' => strpos($content, 'modalData') !== false,
                    'renderMedicalHistoryQuestions' => strpos($content, 'renderMedicalHistoryQuestions') !== false,
                    'form-container' => strpos($content, 'form-container') !== false,
                    'JavaScript' => strpos($content, '<script>') !== false
                ];
                
                echo "<h3>Content Analysis:</h3>";
                echo "<ul>";
                foreach ($checks as $check => $result) {
                    $status = $result ? '✅' : '❌';
                    echo "<li>$status $check</li>";
                }
                echo "</ul>";
                
                // Show first part of content
                echo "<h3>Content Preview (first 1000 characters):</h3>";
                echo "<pre>" . htmlspecialchars(substr($content, 0, 1000)) . "...</pre>";
                
            } else {
                echo "<p class='error'>❌ No content returned</p>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
        } catch (Error $e) {
            echo "<p class='error'>❌ Fatal Error: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>Test 3: Live Modal Test</h2>
        <p>Click the button below to test the modal in a live environment:</p>
        <button onclick="testModal()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
            Test Medical History Modal
        </button>
        
        <div id="modalResult" style="margin-top: 10px;"></div>
    </div>
    
    <script>
    function testModal() {
        const resultDiv = document.getElementById('modalResult');
        resultDiv.innerHTML = '<p class="info">Loading modal content...</p>';
        
        fetch('src/views/forms/medical-history-modal-content.php?donor_id=162')
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(html => {
                console.log('Content loaded, length:', html.length);
                resultDiv.innerHTML = `
                    <p class="success">✅ Modal content loaded successfully</p>
                    <p class="info">Content length: ${html.length} characters</p>
                    <p class="info">Contains questions: ${html.includes('Do you feel well and healthy today?') ? 'Yes' : 'No'}</p>
                    <p class="info">Contains JavaScript: ${html.includes('renderMedicalHistoryQuestions') ? 'Yes' : 'No'}</p>
                `;
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = `<p class="error">❌ Error loading modal: ${error.message}</p>`;
            });
    }
    </script>
</body>
</html>

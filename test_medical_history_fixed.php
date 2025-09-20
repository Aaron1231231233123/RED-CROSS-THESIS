<!DOCTYPE html>
<html>
<head>
    <title>Medical History Modal Test - Fixed Version</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        .modal { display: none; }
        .modal.show { display: block; }
        .modal-content { background: white; padding: 20px; border: 1px solid #ccc; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Medical History Modal Test - Fixed Version</h1>
    
    <div class="test-section">
        <h2>Test the Fixed Medical History Modal</h2>
        <p>This test will load the medical history modal content and display it in a modal to verify it's working correctly.</p>
        
        <button onclick="testModal()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
            Load Medical History Modal
        </button>
        
        <div id="testResults" style="margin-top: 20px;"></div>
    </div>
    
    <!-- Modal Container -->
    <div id="testModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Medical History Modal Test</h3>
                <button onclick="closeModal()" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Close</button>
            </div>
            <div id="modalContent">
                <div class="d-flex justify-content-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function testModal() {
        const resultsDiv = document.getElementById('testResults');
        resultsDiv.innerHTML = '<p class="info">Loading modal content...</p>';
        
        // Show modal
        document.getElementById('testModal').classList.add('show');
        
        // Load content
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
                
                // Update modal content
                const modalContent = document.getElementById('modalContent');
                modalContent.innerHTML = html;
                
                // Execute scripts (like the admin dashboard does)
                const scripts = modalContent.querySelectorAll('script');
                scripts.forEach(script => {
                    try {
                        const newScript = document.createElement('script');
                        if (script.type) newScript.type = script.type;
                        if (script.src) {
                            newScript.src = script.src;
                        } else {
                            newScript.text = script.textContent || '';
                        }
                        document.body.appendChild(newScript);
                    } catch (e) {
                        console.log('Script execution error:', e);
                    }
                });
                
                // Call question generation function
                setTimeout(() => {
                    if (typeof window.generateAdminMedicalHistoryQuestions === 'function') {
                        console.log('Calling generateAdminMedicalHistoryQuestions...');
                        window.generateAdminMedicalHistoryQuestions();
                        
                        // Check results after a short delay
                        setTimeout(() => {
                            checkResults();
                        }, 1000);
                    } else {
                        console.error('generateAdminMedicalHistoryQuestions function not found');
                        checkResults();
                    }
                }, 100);
            })
            .catch(error => {
                console.error('Error:', error);
                resultsDiv.innerHTML = `<p class="error">❌ Error loading modal: ${error.message}</p>`;
            });
    }
    
    function checkResults() {
        const resultsDiv = document.getElementById('testResults');
        const totalQuestions = document.querySelectorAll('.question-text').length;
        const formContainers = document.querySelectorAll('.form-container[data-step-container]');
        const hasQuestions = totalQuestions > 0;
        
        let results = `
            <h3>Test Results:</h3>
            <p class="success">✅ Modal content loaded successfully</p>
            <p class="info">Content length: ${document.getElementById('modalContent').innerHTML.length} characters</p>
            <p class="info">Contains questions: ${document.getElementById('modalContent').innerHTML.includes('Do you feel well and healthy today?') ? 'Yes' : 'No'}</p>
            <p class="info">Contains JavaScript: ${document.getElementById('modalContent').innerHTML.includes('generateAdminMedicalHistoryQuestions') ? 'Yes' : 'No'}</p>
            <p class="info">Contains form containers: ${formContainers.length > 0 ? 'Yes' : 'No'}</p>
        `;
        
        if (hasQuestions) {
            results += `<p class="success">✅ Questions rendered successfully: ${totalQuestions} questions found</p>`;
            results += `<p class="info">Form containers: ${formContainers.length}</p>`;
            
            // Check each step
            formContainers.forEach((container, index) => {
                const questions = container.querySelectorAll('.question-text');
                results += `<p class="info">Step ${index + 1}: ${questions.length} questions</p>`;
            });
        } else {
            results += `<p class="error">❌ No questions rendered - JavaScript may not be executing</p>`;
        }
        
        resultsDiv.innerHTML = results;
    }
    
    function closeModal() {
        document.getElementById('testModal').classList.remove('show');
    }
    </script>
</body>
</html>

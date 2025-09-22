<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../unauthorized.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Workflow Progression - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-card {
            border-left: 4px solid #dc3545;
            margin-bottom: 20px;
        }
        .btn-fix {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-fix:hover {
            background: linear-gradient(135deg, #c82333, #a71e2a);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        .result-card {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card status-card">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-tools me-2"></i>
                            Fix Workflow Progression
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Issue Identified</h6>
                            <p class="mb-0">
                                Some donors are stuck between Physical Examination and Blood Collection stages. 
                                This tool will identify and fix these workflow progression issues.
                            </p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6>What this fix does:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Finds completed physical exams</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Creates missing blood collection records</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Updates status flags properly</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Ensures smooth workflow progression</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Expected results:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-arrow-right text-primary me-2"></i>Pending (Collection) status appears</li>
                                    <li><i class="fas fa-arrow-right text-primary me-2"></i>Blood collection forms become available</li>
                                    <li><i class="fas fa-arrow-right text-primary me-2"></i>Workflow progresses smoothly</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button id="fixWorkflowBtn" class="btn btn-fix">
                                <i class="fas fa-wrench me-2"></i>
                                Fix Workflow Progression
                            </button>
                        </div>
                        
                        <div id="resultContainer" class="result-card" style="display: none;">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Fix Results</h6>
                                </div>
                                <div class="card-body">
                                    <div id="resultContent"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="dashboard-Inventory-System-list-of-donations.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
    <script>
        document.getElementById('fixWorkflowBtn').addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            
            // Show loading state
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Fixing Workflow...';
            btn.disabled = true;
            
            // Call the fix API
            fetch('../../assets/php_func/fix_workflow_progression.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                // Show results
                const resultContainer = document.getElementById('resultContainer');
                const resultContent = document.getElementById('resultContent');
                
                if (data.success) {
                    resultContent.innerHTML = `
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i>Fix Completed Successfully!</h6>
                            <p class="mb-2">Fixed <strong>${data.fixed_count}</strong> workflow progression issues.</p>
                            ${data.errors.length > 0 ? `
                                <div class="mt-3">
                                    <h6>Warnings:</h6>
                                    <ul class="mb-0">
                                        ${data.errors.map(error => `<li class="text-warning">${error}</li>`).join('')}
                                    </ul>
                                </div>
                            ` : ''}
                        </div>
                        <div class="text-center">
                            <a href="dashboard-Inventory-System-list-of-donations.php" class="btn btn-success">
                                <i class="fas fa-eye me-2"></i>
                                View Updated Dashboard
                            </a>
                        </div>
                    `;
                } else {
                    resultContent.innerHTML = `
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-exclamation-circle me-2"></i>Fix Failed</h6>
                            <p class="mb-0">${data.message}</p>
                        </div>
                    `;
                }
                
                resultContainer.style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                const resultContainer = document.getElementById('resultContainer');
                const resultContent = document.getElementById('resultContent');
                
                resultContent.innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-circle me-2"></i>Error</h6>
                        <p class="mb-0">Failed to fix workflow progression. Please try again.</p>
                    </div>
                `;
                
                resultContainer.style.display = 'block';
            })
            .finally(() => {
                // Reset button
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>

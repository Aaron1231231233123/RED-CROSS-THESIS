/**
 * Duplicate Donor Diagnostic Modal
 * A reusable modal component for testing and diagnosing duplicate donor checks
 */
class DuplicateDonorDiagnosticModal {
    constructor(options = {}) {
        this.apiEndpoint = options.apiEndpoint || 'assets/php_func/check_duplicate_donor.php';
        this.testApiEndpoint = options.testApiEndpoint || 'assets/php_func/test_duplicate_donor_database.php';
        this.modalId = options.modalId || 'duplicateDonorDiagnosticModal';
        this.isInitialized = false;
    }

    /**
     * Initialize the modal - create HTML and attach event listeners
     */
    init() {
        if (this.isInitialized) {
            return;
        }

        this.createModal();
        this.attachEventListeners();
        this.isInitialized = true;
    }

    /**
     * Create the modal HTML structure
     */
    createModal() {
        // Check if modal already exists
        if (document.getElementById(this.modalId)) {
            return;
        }

        const modalHTML = `
            <div class="modal fade" id="${this.modalId}" tabindex="-1" aria-labelledby="${this.modalId}Label" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #a00000 100%); border-bottom: none;">
                            <h5 class="modal-title fw-bold text-white" id="${this.modalId}Label">
                                <i class="fas fa-database me-2"></i>Duplicate Donor Check - Database Diagnostic Tool
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" style="max-height: 80vh; overflow-y: auto;">
                            <!-- Connection Test Section -->
                            <div class="test-form mb-3">
                                <h5><i class="fas fa-plug me-2"></i>Database Connection Test</h5>
                                <div class="btn-group me-2 mb-2">
                                    <button class="btn btn-primary btn-sm" id="testConnectionBtn">
                                        <i class="fas fa-check-circle me-2"></i>Test Connection
                                    </button>
                                    <button class="btn btn-info btn-sm" id="getStatsBtn">
                                        <i class="fas fa-chart-bar me-2"></i>Get Database Stats
                                    </button>
                                </div>
                                <div id="connectionResult"></div>
                                <div id="databaseStats"></div>
                            </div>

                            <!-- Test Form Section -->
                            <div class="test-form mb-3">
                                <h5><i class="fas fa-search me-2"></i>Test Duplicate Donor Check</h5>
                                <div class="mb-3">
                                    <button class="btn btn-sm btn-outline-secondary" id="loadSampleBtn">
                                        <i class="fas fa-random me-2"></i>Load Sample Donor from Database
                                    </button>
                                </div>
                                <form id="diagnosticTestForm">
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Surname *</label>
                                            <input type="text" class="form-control" id="diag_surname" required>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="diag_first_name" required>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Middle Name</label>
                                            <input type="text" class="form-control" id="diag_middle_name">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Birthdate *</label>
                                            <input type="date" class="form-control" id="diag_birthdate" required>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-search me-2"></i>Check for Duplicates
                                        <span class="spinner-border spinner-border-sm ms-2 d-none" id="checkSpinner"></span>
                                    </button>
                                </form>
                            </div>

                            <!-- Results Section -->
                            <div id="diagnosticResultsSection" style="display: none;">
                                <h5><i class="fas fa-chart-line me-2"></i>Test Results</h5>
                                
                                <!-- Metrics -->
                                <div class="row" id="diagnosticMetricsRow"></div>

                                <!-- Database Query Info -->
                                <div class="mt-4">
                                    <h6><i class="fas fa-code me-2"></i>Database Queries Executed</h6>
                                    <div id="diagnosticQueryInfo"></div>
                                </div>

                                <!-- Raw Response -->
                                <div class="mt-4">
                                    <h6><i class="fas fa-file-code me-2"></i>Raw API Response</h6>
                                    <pre id="diagnosticRawResponse" class="bg-dark text-light p-3 rounded" style="max-height: 300px; overflow-y: auto; font-size: 0.85rem;"></pre>
                                </div>

                                <!-- Donor Results -->
                                <div class="mt-4" id="diagnosticDonorResults"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Add styles
        this.addStyles();
    }

    /**
     * Add custom styles for the modal
     */
    addStyles() {
        if (document.getElementById('diagnosticModalStyles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'diagnosticModalStyles';
        style.textContent = `
            .test-form {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 20px;
            }
            .query-box {
                background: #f8f9fa;
                border-left: 4px solid #dc3545;
                padding: 15px;
                border-radius: 5px;
                font-family: 'Courier New', monospace;
                font-size: 0.85rem;
                margin: 10px 0;
                word-break: break-all;
            }
            .result-box {
                background: #f8f9fa;
                border-left: 4px solid #28a745;
                padding: 15px;
                border-radius: 5px;
                margin: 10px 0;
                max-height: 400px;
                overflow-y: auto;
            }
            .donor-card {
                background: white;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 15px;
                margin: 10px 0;
            }
            .metric-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                border-radius: 10px;
                text-align: center;
                margin: 10px 0;
            }
            .metric-value {
                font-size: 2.5rem;
                font-weight: bold;
                margin: 10px 0;
            }
            .metric-label {
                font-size: 0.9rem;
                opacity: 0.9;
            }
            .status-badge {
                padding: 8px 16px;
                border-radius: 20px;
                font-weight: 600;
                font-size: 0.9rem;
            }
            .status-success {
                background: #d4edda;
                color: #155724;
            }
            .status-warning {
                background: #fff3cd;
                color: #856404;
            }
            .status-error {
                background: #f8d7da;
                color: #721c24;
            }
            .status-info {
                background: #d1ecf1;
                color: #0c5460;
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Test connection button
        const testConnectionBtn = document.getElementById('testConnectionBtn');
        if (testConnectionBtn) {
            testConnectionBtn.addEventListener('click', () => this.testConnection());
        }

        // Get stats button
        const getStatsBtn = document.getElementById('getStatsBtn');
        if (getStatsBtn) {
            getStatsBtn.addEventListener('click', () => this.getDatabaseStats());
        }

        // Load sample button
        const loadSampleBtn = document.getElementById('loadSampleBtn');
        if (loadSampleBtn) {
            loadSampleBtn.addEventListener('click', () => this.loadSampleDonor());
        }

        // Test form submission
        const testForm = document.getElementById('diagnosticTestForm');
        if (testForm) {
            testForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.testDuplicateCheck();
            });
        }

        // Auto-test on modal show
        const modal = document.getElementById(this.modalId);
        if (modal) {
            modal.addEventListener('shown.bs.modal', () => {
                this.testConnection();
                this.getDatabaseStats();
            });
        }
    }

    /**
     * Show the modal
     */
    show() {
        if (!this.isInitialized) {
            this.init();
        }

        const modalElement = document.getElementById(this.modalId);
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    }

    /**
     * Test database connection
     */
    async testConnection() {
        const resultDiv = document.getElementById('connectionResult');
        if (!resultDiv) return;

        resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Testing connection...</div>';
        
        try {
            const testUrl = 'https://nwakbxwglhxcpunrzstf.supabase.co/rest/v1/donor_form?select=donor_id&limit=1';
            const testResponse = await fetch(testUrl, {
                headers: {
                    'apikey': 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im53YWtieHdnbGh4Y3B1bnJ6c3RmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDIyODA1NzIsImV4cCI6MjA1Nzg1NjU3Mn0.y4CIbDT2UQf2ieJTQukuJRRzspSZPehSgNKivBwpvc4',
                    'Authorization': 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im53YWtieHdnbGh4Y3B1bnJ6c3RmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDIyODA1NzIsImV4cCI6MjA1Nzg1NjU3Mn0.y4CIbDT2UQf2ieJTQukuJRRzspSZPehSgNKivBwpvc4'
                }
            });
            
            if (testResponse.ok) {
                const data = await testResponse.json();
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Connection Successful!</strong><br>
                        Database is accessible. Found ${data.length} test record(s).
                    </div>
                `;
            } else {
                throw new Error(`HTTP ${testResponse.status}`);
            }
        } catch (error) {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    <strong>Connection Failed!</strong><br>
                    Error: ${error.message}
                </div>
            `;
        }
    }

    /**
     * Get database statistics
     */
    async getDatabaseStats() {
        const statsDiv = document.getElementById('databaseStats');
        if (!statsDiv) return;

        statsDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Loading database statistics...</div>';
        
        try {
            const response = await fetch(`${this.testApiEndpoint}?action=stats`);
            const data = await response.json();
            
            if (data.status === 'success') {
                let html = '<div class="alert alert-success"><h6><i class="fas fa-database me-2"></i>Database Statistics</h6>';
                html += `<p class="mb-1"><strong>Database URL:</strong> ${data.database_url}</p>`;
                html += `<p class="mb-1"><strong>API Configured:</strong> ${data.api_configured ? 'Yes ✓' : 'No ✗'}</p>`;
                
                if (data.stats && data.stats.sample_donors) {
                    html += `<p class="mb-1"><strong>Sample Donors Found:</strong> ${data.stats.sample_count}</p>`;
                    html += '<h6 class="mt-2">Sample Donor Records:</h6>';
                    html += '<div class="result-box">';
                    data.stats.sample_donors.forEach(donor => {
                        html += `
                            <div class="donor-card">
                                <strong>${donor.surname}, ${donor.first_name} ${donor.middle_name || ''}</strong><br>
                                <small>ID: ${donor.donor_id} | Age: ${donor.age} | Sex: ${donor.sex} | Birthdate: ${donor.birthdate}</small>
                            </div>
                        `;
                    });
                    html += '</div>';
                }
                html += '</div>';
                statsDiv.innerHTML = html;
            } else {
                throw new Error(data.message || 'Failed to get stats');
            }
        } catch (error) {
            statsDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Error: ${error.message}</div>`;
        }
    }

    /**
     * Load sample donor
     */
    async loadSampleDonor() {
        try {
            const response = await fetch(`${this.testApiEndpoint}?action=stats`);
            const data = await response.json();
            
            if (data.status === 'success' && data.stats && data.stats.sample_donors && data.stats.sample_donors.length > 0) {
                const randomIndex = Math.floor(Math.random() * data.stats.sample_donors.length);
                const donor = data.stats.sample_donors[randomIndex];
                
                document.getElementById('diag_surname').value = donor.surname || '';
                document.getElementById('diag_first_name').value = donor.first_name || '';
                document.getElementById('diag_middle_name').value = donor.middle_name || '';
                document.getElementById('diag_birthdate').value = donor.birthdate || '';
            } else {
                alert('No sample donors found in database.');
            }
        } catch (error) {
            alert('Error loading sample donor: ' + error.message);
        }
    }

    /**
     * Test duplicate check
     */
    async testDuplicateCheck() {
        const form = document.getElementById('diagnosticTestForm');
        const spinner = document.getElementById('checkSpinner');
        const resultsSection = document.getElementById('diagnosticResultsSection');
        
        if (!form || !spinner || !resultsSection) return;

        // Show loading
        spinner.classList.remove('d-none');
        form.querySelector('button[type="submit"]').disabled = true;
        resultsSection.style.display = 'none';
        
        // Get form data
        const formData = {
            surname: document.getElementById('diag_surname').value.trim(),
            first_name: document.getElementById('diag_first_name').value.trim(),
            middle_name: document.getElementById('diag_middle_name').value.trim(),
            birthdate: document.getElementById('diag_birthdate').value
        };
        
        try {
            const startTime = performance.now();
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const endTime = performance.now();
            const responseTime = (endTime - startTime).toFixed(2);
            
            const result = await response.json();
            
            // Show raw response
            document.getElementById('diagnosticRawResponse').textContent = JSON.stringify(result, null, 2);
            
            // Show metrics
            const metricsRow = document.getElementById('diagnosticMetricsRow');
            metricsRow.innerHTML = `
                <div class="col-md-4">
                    <div class="metric-card">
                        <div class="metric-label">Response Time</div>
                        <div class="metric-value">${responseTime}ms</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card" style="background: linear-gradient(135deg, ${result.duplicate_found ? '#dc3545' : '#28a745'} 0%, ${result.duplicate_found ? '#a00000' : '#20c997'} 100%);">
                        <div class="metric-label">Status</div>
                        <div class="metric-value">${result.duplicate_found ? 'DUPLICATE' : 'CLEAR'}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="metric-label">HTTP Status</div>
                        <div class="metric-value">${response.status}</div>
                    </div>
                </div>
            `;
            
            // Show query info
            const queryInfo = document.getElementById('diagnosticQueryInfo');
            queryInfo.innerHTML = `<div class="query-box">API Endpoint: ${this.apiEndpoint}<br>POST Data: ${JSON.stringify(formData, null, 2)}</div>`;
            
            // Show donor results
            const donorResults = document.getElementById('diagnosticDonorResults');
            if (result.duplicate_found && result.data) {
                const data = result.data;
                const statusClass = {
                    'success': 'status-success',
                    'warning': 'status-warning',
                    'danger': 'status-error',
                    'info': 'status-info'
                }[data.alert_type] || 'status-info';
                
                donorResults.innerHTML = `
                    <h6><i class="fas fa-user me-2"></i>Duplicate Donor Found</h6>
                    <div class="donor-card">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-danger">${data.full_name}</h6>
                                <p class="mb-1"><strong>Donor ID:</strong> ${data.donor_id}</p>
                                <p class="mb-1"><strong>Age:</strong> ${data.age} years old</p>
                                <p class="mb-1"><strong>Sex:</strong> ${data.sex}</p>
                                <p class="mb-1"><strong>Birthdate:</strong> ${data.birthdate}</p>
                                <p class="mb-1"><strong>Mobile:</strong> ${data.mobile || 'Not provided'}</p>
                                <p class="mb-1"><strong>Email:</strong> ${data.email || 'Not provided'}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <span class="status-badge ${statusClass}">${data.status_message}</span>
                                </p>
                                <p class="mb-1"><strong>Eligibility Status:</strong> ${data.eligibility_status || 'N/A'}</p>
                                <p class="mb-1"><strong>Has Donation History:</strong> ${data.has_eligibility_history ? 'Yes ✓' : 'No ✗'}</p>
                                <p class="mb-1"><strong>Can Donate Today:</strong> ${data.can_donate_today ? 'Yes ✓' : 'No ✗'}</p>
                                <p class="mb-1"><strong>Registered:</strong> ${data.time_description}</p>
                                ${data.donation_stage ? `<p class="mb-1"><strong>Donation Stage:</strong> ${data.donation_stage}</p>` : ''}
                                ${data.reason ? `<p class="mb-1"><strong>Reason:</strong> ${data.reason}</p>` : ''}
                                ${data.total_donations !== undefined ? `
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <div class="card bg-light border-0">
                                                <div class="card-body text-center p-2">
                                                    <h5 class="text-danger mb-0">${data.total_donations}</h5>
                                                    <small class="text-muted">Donations</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card bg-light border-0">
                                                <div class="card-body text-center p-2">
                                                    <h5 class="text-primary mb-0">${data.total_eligibility_records || 0}</h5>
                                                    <small class="text-muted">Records</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ` : ''}
                                <div class="alert ${data.can_donate_today ? 'alert-success' : 'alert-warning'} mt-2">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Suggestion:</strong> ${data.suggestion}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                donorResults.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>No Duplicate Found</strong><br>
                        ${result.message || 'This donor is not in the database.'}
                    </div>
                `;
            }
            
            // Show results section
            resultsSection.style.display = 'block';
            
        } catch (error) {
            document.getElementById('diagnosticRawResponse').textContent = `Error: ${error.message}`;
            document.getElementById('diagnosticDonorResults').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error occurred:</strong> ${error.message}
                </div>
            `;
            resultsSection.style.display = 'block';
        } finally {
            spinner.classList.add('d-none');
            form.querySelector('button[type="submit"]').disabled = false;
        }
    }
}

// Export for global use
if (typeof window !== 'undefined') {
    window.DuplicateDonorDiagnosticModal = DuplicateDonorDiagnosticModal;
}


<?php
// Static Reports Page (no DB). Mirrors dashboard layout with report widgets.
session_start();
// If your app enforces auth, keep a soft guard but don't redirect to keep this demo standalone
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 0; // demo
    $_SESSION['role_id'] = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forecast Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
body{background:#f8f9fa;font-family:Arial, sans-serif;}
main.col-md-9.ms-sm-auto.col-lg-10.px-md-4{margin-left:240px !important;margin-top:0 !important;}
.dashboard-home-header{position:fixed;top:0;left:240px;width:calc(100% - 240px);background:#f8f9fa;padding:15px;border-bottom:1px solid #ddd;z-index:1000;transition:left .3s ease,width .3s ease}
.dashboard-home-sidebar{height:100vh;overflow-y:auto;position:fixed;width:240px;background:#ffffff;border-right:1px solid #ddd;padding:15px;display:flex;flex-direction:column;transition:width .3s ease}
.sidebar-main-content{flex-grow:1;padding-bottom:80px}
.logout-container{position:absolute;bottom:0;left:0;right:0;padding:20px 15px;border-top:1px solid #ddd;background:#fff}
.logout-link{color:#dc3545 !important}
.logout-link:hover{background:#dc3545 !important;color:#fff !important}
.dashboard-home-sidebar .nav-link{color:#333;padding:10px 15px;margin:2px 0;border-radius:4px;transition:all .2s ease;font-size:.9rem;display:flex;align-items:center;justify-content:space-between;text-decoration:none}
.dashboard-home-sidebar .nav-link i{margin-right:10px;font-size:.9rem;width:16px;text-align:center}
.dashboard-home-sidebar .nav-link:hover{background:#f8f9fa;color:#dc3545}
.dashboard-home-sidebar .nav-link.active{background:#dc3545;color:#fff}
.dashboard-home-sidebar .collapse-menu{list-style:none;padding:0;margin:0;background:#f8f9fa;border-radius:4px}
.dashboard-home-sidebar .collapse-menu .nav-link{padding:8px 15px 8px 40px;font-size:.85rem;margin:0;border-radius:0}
.dashboard-home-sidebar .nav-link[aria-expanded="true"]{background:#f8f9fa;color:#dc3545}
.dashboard-home-sidebar i.fa-chevron-down{font-size:.8rem;transition:transform .2s ease}
.dashboard-home-main{margin-left:0;margin-top:0;min-height:100vh;overflow-x:hidden;padding:12px}
.dashboard-home-main .content-wrapper{width:100%;max-width:none;margin:16px 0;background:#ffffff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);padding:20px}
@media (max-width: 1400px){
  .dashboard-home-main .content-wrapper{width:calc(100% - 16px);margin:12px 8px}
}
@media (max-width: 992px){
  .dashboard-home-main{padding:8px}
  .dashboard-home-main .content-wrapper{width:96vw;margin:8px auto;padding:12px;border-radius:10px}
}
.card{border:none;box-shadow:0 4px 6px rgba(0,0,0,.1)}
.kpi-card{background:#fff;border-radius:12px}
.kpi-label{color:#941022;font-weight:600}
.kpi-value{font-size:42px;font-weight:700;color:#1f2937}
.table thead th{background:#941022;color:#fff;}
.status-badge{display:inline-flex;align-items:center;gap:6px;padding:.25rem .6rem;border-radius:999px;font-weight:600}
.status-low{background:#fff7e6;color:#b35c00}
.status-adequate{background:#e3f2fd;color:#1976d2}
.status-surplus{background:#e6fff2;color:#0f7a3a}
.action-btn{border:none;background:#e9ecef;border-radius:6px;width:32px;height:28px;display:flex;align-items:center;justify-content:center}
.chart-card{background:#fff;border:1px solid #eee;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.06)}
.chart-card h6{font-weight:700;color:#333;margin-bottom:8px}
/* Equalize chart card heights for all charts */
.charts-row .chart-card{min-height:400px;display:flex;flex-direction:column}
.charts-row .chart-card canvas{flex:1 1 auto !important}
#barChart{height:450px !important;max-height:450px !important}
#pieChart{height:450px !important;max-height:450px !important}
#lineChart{height:400px !important;max-height:400px !important}
.pagination .page-link{color:#941022}
.filters .form-select,.filters .form-control{max-width:280px}
@media(max-width:768px){.dashboard-home-sidebar{width:0;padding:0;overflow:hidden}.dashboard-home-header{left:0;width:100%}.dashboard-home-main{margin-left:0;padding:10px}}
/* Red section dividers inside content */
.content-wrapper hr{border:0;border-top:2px solid #941022;opacity:1;margin:12px 0}
/* Pagination styles to match reference */
.custom-pagination{display:inline-flex;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden}
.custom-pagination .page-item{margin:0}
.custom-pagination .page-link{border:none;border-left:1px solid #e5e7eb !important;color:#2563eb;padding:6px 12px;min-width:36px;text-align:center;background:#fff}
.custom-pagination .page-item:first-child .page-link{border-left:none}
.custom-pagination .page-item.active .page-link{background:#0d6efd;color:#fff !important}
.custom-pagination .page-link:focus{box-shadow:none}
    </style>
</head>
<body>
    <?php include '../../src/views/modals/admin-donor-registration-modal.php'; ?>
    <div class="container-fluid">
        <!-- Header copied to match main dashboard structure -->
            <div class="dashboard-home-header bg-light p-3 border-bottom" style="position: sticky; top: 0; z-index: 1000;">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Blood Donor Management System</h4>
                <button class="btn btn-danger" onclick="showConfirmationModal()">
                    <i class="fas fa-plus me-2"></i>Register Donor
                </button>
            </div>
        </div>

        <div class="row" style="margin-left: 240px;">
            <!-- Sidebar aligned with main dashboard -->
            <nav class="col-md-3 col-lg-2 d-md-block dashboard-home-sidebar" style="min-width: 240px; position: fixed; top: 0; left: 0; align-self: flex-start;">
                <div class="sidebar-main-content">
                    <div class="d-flex align-items-center ps-1 mb-4 mt-2">
                        <img src="../../assets/image/PRC_Logo.png" alt="Red Cross Logo" style="width:65px;height:65px;object-fit:contain;">
                        <span class="text-primary ms-1" style="font-size:1.5rem;font-weight:600;">Dashboard</span>
                    </div>
                    <ul class="nav flex-column">
                        <a href="dashboard-Inventory-System.php" class="nav-link"><span><i class="fas fa-home"></i>Home</span></a>
                        <a href="dashboard-Inventory-System-list-of-donations.php" class="nav-link"><span><i class="fas fa-users"></i>Donor Management</span></a>
                        <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link"><span><i class="fas fa-tint"></i>Blood Bank</span></a>
                        <a href="Dashboard-Inventory-System-Hospital-Request.php" class="nav-link"><span><i class="fas fa-list"></i>Hospital Requests</span></a>
                        <a href="#" class="nav-link active"><span><i class="fas fa-chart-line"></i>Forecast Reports</span></a>
                        <a href="Dashboard-Inventory-System-Users.php" class="nav-link"><span><i class="fas fa-user-cog"></i>Manage Users</span></a>
                    </ul>
                </div>
                <div class="logout-container">
                    <a href="../../assets/php_func/logout.php" class="nav-link logout-link"><span><i class="fas fa-sign-out-alt me-2"></i>Logout</span></a>
                </div>
            </nav>

            <main class="col-12 px-md-4" style="flex: 1 1 auto; padding-left: 0; padding-right: 0;">
                <div class="dashboard-home-main" style="margin-right: 0;">
                    <div class="content-wrapper">
                    <div class="d-flex justify-content-between align-items-center mb-2" style="padding: 8px 4px;">
                        <h2 class="mb-0" style="font-weight:700">Forecast Reports</h2>
                        <button class="btn btn-outline-danger btn-sm" id="refreshReportsBtn" title="Refresh data from database">
                            <i class="fas fa-sync-alt me-2"></i>Refresh Data
                        </button>
                    </div>

                    <div class="row g-2 mb-2 align-items-stretch">
                        <div class="col-md-2">
                            <div class="card kpi-card p-3" style="border:1px solid #eee; border-radius:14px; box-shadow:0 6px 18px rgba(0,0,0,0.06)">
                                <div class="kpi-label" style="font-size:0.85rem">Total Forecasted Demand</div>
                                <div class="kpi-value" id="kpiDemand" style="font-size:32px">520</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card kpi-card p-3" style="border:1px solid #eee; border-radius:14px; box-shadow:0 6px 18px rgba(0,0,0,0.06)">
                                <div class="kpi-label" style="font-size:0.85rem">Total Forecasted Donations</div>
                                <div class="kpi-value" id="kpiDonations" style="font-size:32px">460</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card kpi-card p-3" style="border:1px solid #eee; border-radius:14px; box-shadow:0 6px 18px rgba(0,0,0,0.06)">
                                <div class="kpi-label" style="font-size:0.85rem">Projected Balance</div>
                                <div class="kpi-value" id="kpiBalance" style="font-size:32px">-60</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card kpi-card p-3" style="border:1px solid #eee; border-radius:14px; box-shadow:0 6px 18px rgba(0,0,0,0.06)">
                                <div class="kpi-label" style="font-size:0.85rem">Target Stock Level</div>
                                <div class="kpi-value" id="kpiTargetStock" style="font-size:32px">-</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card kpi-card p-3" style="border:1px solid #eee; border-radius:14px; box-shadow:0 6px 18px rgba(0,0,0,0.06)">
                                <div class="kpi-label" style="font-size:0.85rem">Expiring Weekly</div>
                                <div class="kpi-value" id="kpiExpiringWeekly" style="font-size:32px">-</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card kpi-card p-3" style="border:1px solid #eee; border-radius:14px; box-shadow:0 6px 18px rgba(0,0,0,0.06)">
                                <div class="kpi-label" style="font-size:0.85rem">Expiring Monthly</div>
                                <div class="kpi-value" id="kpiExpiringMonthly" style="font-size:32px">-</div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-2">

                    <!-- Line Chart - Moved to top after KPI cards -->
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <div class="card chart-card p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0"><span aria-hidden="true">🩸</span> 2025 Supply vs Demand &amp; 3-Month Forecast</h6>
                                    <span class="text-muted small">Powered by Plotly</span>
                                </div>
                                <iframe id="interactiveCombinedFrame" src="" width="100%" height="420" frameborder="0" style="border:1px solid #eee;border-radius:8px;background:#fff;"></iframe>
                                <small class="text-muted">This interactive view refreshes whenever you click Refresh Data.</small>
                            </div>
                        </div>
                    </div>

                    <hr class="my-2">

                    <div class="alert alert-light border mb-3" role="alert">
                        Forecast data below refreshes directly from Supabase. Use the Refresh Data button whenever you need the latest numbers.
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle" id="reportsTable">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Blood Type</th>
                                    <th>Forecasted Demand</th>
                                    <th>Forecasted Donations</th>
                                    <th>Projected Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="text-muted mt-2 small">Showing latest forecast for every blood type.</div>

                    <hr class="my-3">

                    <!-- Two Charts - Bar and Pie -->
                    <div class="row g-3 charts-row">
                        <div class="col-lg-6 col-md-12">
                            <div class="card chart-card p-3">
                                <h6>Forecasted Blood Supply</h6>
                                <img id="supplyChartImg" src="" alt="Supply Forecast Chart" class="img-fluid rounded mb-3 border">
                                <h6 class="mt-2">Forecasted Hospital Demand</h6>
                                <img id="demandChartImg" src="" alt="Demand Forecast Chart" class="img-fluid rounded border">
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <div class="card chart-card p-3">
                                <h6>Supply vs Demand Comparison</h6>
                                <img id="comparisonChartImg" src="" alt="Supply vs Demand Chart" class="img-fluid rounded mb-3 border">
                                <h6 class="mt-2">Projected Stock Buffer</h6>
                                <img id="projectedChartImg" src="" alt="Projected Stock Chart" class="img-fluid rounded border">
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 charts-row mt-1">
                        <div class="col-lg-6 col-md-12">
                            <div class="card chart-card p-3">
                                <h6><span aria-hidden="true">🩸</span> 2025 Blood Supply &amp; 3-Month Forecast</h6>
                                <iframe id="interactiveSupplyFrame" src="" width="100%" height="360" frameborder="0" style="border:1px solid #eee;border-radius:8px;background:#fff;"></iframe>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <div class="card chart-card p-3">
                                <h6><span aria-hidden="true">📊</span> 2025 Hospital Demand &amp; 3-Month Forecast</h6>
                                <iframe id="interactiveDemandFrame" src="" width="100%" height="360" frameborder="0" style="border:1px solid #eee;border-radius:8px;background:#fff;"></iframe>
                            </div>
                        </div>
                    </div>

                    <div class="card chart-card p-3 mt-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Projected Stock Status</h6>
                            <small class="text-muted">Target buffer: 10 units</small>
                        </div>
                        <div id="projectedStockPanel" class="mt-3">
                            <p class="text-muted mb-0">Loading projected stock levels...</p>
                        </div>
                        <iframe id="projectedStockFrame" src="" width="100%" height="520" frameborder="0" class="mt-3" style="border:1px solid #eee;border-radius:8px;background:#fff;"></iframe>
                    </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">Forecast Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailsBody">
                    <!-- Filled by JS -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="exportBtn">Export Details</button>
                </div>
        </div>
    </div>

    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="../../assets/js/forecast-dashboard.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.showConfirmationModal = function() {
                if (typeof window.openAdminDonorRegistrationModal === 'function') {
                    window.openAdminDonorRegistrationModal();
                } else {
                    console.error('Admin donor registration modal not available yet');
                    alert('Registration modal is still loading. Please try again in a moment.');
                }
            };
        });
    </script>
    <script src="../../assets/js/admin-donor-registration-modal.js"></script>
    <script src="../../assets/js/admin-screening-form-modal.js"></script>
</body>
</html>
</html>

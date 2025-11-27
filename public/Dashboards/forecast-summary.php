<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Supply Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body{background:#f8f9fa;font-family:Arial, sans-serif;}
        .summary-shell{max-width:1050px;margin:0 auto;padding:24px 14px;}
        .summary-toolbar{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;margin-bottom:24px}
        .summary-toolbar .btn{min-width:140px}
        .summary-context{font-size:.9rem;color:#6c757d}
        .summary-content{max-width:1100px;margin:0 auto 40px auto}
        .summary-report h1,.summary-report h2{color:#941022}
        .summary-section{margin-bottom:32px;background:#fff;border-radius:12px;padding:24px;border:1px solid #e5e7eb;box-shadow:0 1rem 2rem rgba(0,0,0,.05)}
        .summary-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:24px}
        .summary-metric-card{border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;background:#fff}
        .summary-metric-card .label{font-size:.8rem;text-transform:uppercase;color:#6b7280;letter-spacing:.05em}
        .summary-metric-card .value{font-size:1.4rem;font-weight:600;color:#1f2937}
        @media print{
            body{background:#fff;}
            .summary-toolbar{display:none !important}
            .summary-shell{padding:0 16px}
            .summary-section{page-break-inside:auto}
            .summary-section.actionable-section{page-break-before:always;break-before:page}
        }
    </style>
</head>
<body>
    <div class="summary-shell">
        <div class="summary-toolbar">
            <button class="btn btn-outline-secondary" onclick="window.location.href='dashboard-Inventory-System-Reports-reports-admin.php'">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </button>
            <div class="summary-context" id="summaryContextLabel">Scope: —</div>
            <button class="btn btn-danger" id="summaryPrintBtn">
                <i class="fas fa-print me-2"></i>Print / Save PDF
            </button>
        </div>
        <div id="summaryContent" class="summary-content">
            <div class="alert alert-light border d-flex align-items-center gap-2">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Loading summary...</span>
            </div>
        </div>
        <div id="summaryFallback" class="alert alert-warning d-none">
            Unable to load the summary data. Please return to the dashboard and generate the summary again.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/forecast-summary-page.js"></script>
</body>
</html>


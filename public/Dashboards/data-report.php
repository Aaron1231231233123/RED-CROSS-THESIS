<?php
session_start();

// Preferred: explicit year parameter (calendar year report)
$yearParam = isset($_GET['year']) ? (int) $_GET['year'] : null;

if ($yearParam) {
    $coverageYear = max(2000, min(2100, $yearParam));
    $startDate = new DateTime($coverageYear . '-01-01');
    $endDate = new DateTime($coverageYear . '-12-31');
    $coverageLabel = 'Jan–Dec ' . $coverageYear;
} else {
    // Backwards-compatible: optional start/end range
    $startParam = isset($_GET['start']) ? $_GET['start'] : '';
    $endParam = isset($_GET['end']) ? $_GET['end'] : '';

    $startDate = DateTime::createFromFormat('Y-m-d', $startParam) ?: null;
    $endDate = DateTime::createFromFormat('Y-m-d', $endParam) ?: null;

    if (!$startDate || !$endDate || $startDate > $endDate) {
        $yearNow = (int) date('Y');
        $startDate = new DateTime($yearNow . '-01-01');
        $endDate = new DateTime($yearNow . '-12-31');
    }

    $coverageYear = (int) $endDate->format('Y');
    $isFullYear = $startDate->format('Y-m-d') === $coverageYear . '-01-01'
        && $endDate->format('Y-m-d') === $coverageYear . '-12-31';

    if ($isFullYear) {
        $coverageLabel = 'Jan–Dec ' . $coverageYear;
    } else {
        $coverageLabel = $startDate->format('M d, Y') . ' – ' . $endDate->format('M d, Y');
    }
}

$firstName = isset($_SESSION['user_first_name']) ? trim((string) $_SESSION['user_first_name']) : '';
$lastName = isset($_SESSION['user_surname']) ? trim((string) $_SESSION['user_surname']) : '';
$generatedBy = trim($firstName . ' ' . $lastName);
if ($generatedBy === '') {
    $generatedBy = 'Admin';
}

$generatedDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Services Data Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body{background:#f8f9fa;font-family:Arial,sans-serif;}
        .report-shell{max-width:1120px;margin:0 auto;padding:24px 12px 40px;}
        .report-header{text-align:center;margin-bottom:24px;}
        .report-header h1{font-size:1.6rem;font-weight:700;margin-bottom:4px;}
        .report-header h2{font-size:1.3rem;font-weight:700;margin-bottom:4px;}
        .report-header h3{font-size:1.1rem;font-weight:700;margin-bottom:10px;}
        .report-meta{font-size:.95rem;margin-top:6px;}
        .report-section{background:#fff;border-radius:10px;border:1px solid #e5e7eb;padding:18px 20px;margin-bottom:16px;}
        .report-section h4{font-size:1.05rem;font-weight:700;margin-bottom:10px;}
        .report-section table{font-size:.9rem;}
        .report-section table th{background:#f3f4f6;}
        .report-group-title{
            font-size:1rem;
            font-weight:700;
            text-transform:uppercase;
            margin:18px 0 8px;
        }
        /* Chart iframes sized to fit comfortably on screen */
        .chart-frame{
            border:1px solid #e5e7eb;
            border-radius:8px;
            background:#fff;
            width:100%;
            max-width:100%;
            height:360px;      /* base height for on‑screen view */
            overflow:hidden;   /* hide any internal scrollbars */
        }
        .section-title-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
        .small-note{font-size:.8rem;color:#6b7280;}
        @media print{
            body{background:#fff;}
            .no-print{display:none !important;}
            .report-shell{padding:0 12px;}
            .report-section{page-break-inside:avoid;}
            /* Slightly reduce iframe height on print so cards use space
               more efficiently without cutting off the charts. */
            .chart-frame{
                height:280px;
            }
        }
    </style>
</head>
<body>
    <div class="report-shell" id="dataReportRoot">
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <button class="btn btn-outline-secondary btn-sm" onclick="window.location.href='dashboard-Inventory-System-Reports-reports-admin.php'">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </button>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="reportPrintBtn">
                    <i class="fas fa-print me-2"></i>Print
                </button>
                <button class="btn btn-danger btn-sm" id="reportDownloadBtn">
                    <i class="fas fa-file-pdf me-2"></i>Save as PDF
                </button>
            </div>
        </div>

        <div class="report-header" id="reportHeader">
            <h1><strong>Philippine Red Cross – Iloilo Chapter</strong></h1>
            <h2><strong>Blood Services Facility</strong></h2>
            <h3><strong>DATA REPORT</strong></h3>
            <div class="report-meta">
                <div><strong>Coverage Period:</strong> <span id="coverageYearLabel"><?php echo htmlspecialchars($coverageLabel, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div><strong>Date Generated:</strong> <span id="generatedDateLabel"><?php echo htmlspecialchars($generatedDate, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div><strong>Generated By:</strong> <span id="generatedByLabel"><?php echo htmlspecialchars($generatedBy, ENT_QUOTES, 'UTF-8'); ?></span></div>
            </div>
        </div>

        <div id="dataReportContent">
            <div class="alert alert-light border d-flex align-items-center gap-2">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Generating data report from live analytics. This may take a few seconds…</span>
            </div>
        </div>
    </div>

    <script>
        window.__DATA_REPORT_METADATA__ = {
            coverageYear: <?php echo json_encode($coverageYear, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            coverageStart: <?php echo json_encode($startDate->format('Y-m-d'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            coverageEnd: <?php echo json_encode($endDate->format('Y-m-d'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            generatedBy: <?php echo json_encode($generatedBy, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            generatedDate: <?php echo json_encode($generatedDate, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="../../assets/js/data-report-page.js"></script>
</body>
</html>



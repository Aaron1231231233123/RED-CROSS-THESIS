<?php
// Simple entry preview for Staff Donor Submission dashboard
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: Staff Donor Submission</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 24px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; }
        .card { border-radius: 10px; border: 1px solid #e5e7eb; }
        .card-header { font-weight: 700; background: #f8f9fa; }
    </style>
    <script>
        function openFlow(kind){
            var url = 'preview-staff-medical-history-submissions.php?kind=' + encodeURIComponent(kind);
            window.location.href = url;
        }
    </script>
</head>
<body>
    <h3 class="mb-1">Preview: Staff Donor Submission</h3>
    <p class="text-muted mb-4">Choose a flow to simulate donor submission.</p>

    <div class="grid mb-4">
        <div class="card">
            <div class="card-header">New Donor</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <button class="btn btn-danger" onclick="openFlow('new')">
                    <i class="fas fa-user-plus me-1"></i>Start New Donor
                </button>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Returning Donor</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary" onclick="openFlow('returning')">
                    <i class="fas fa-undo me-1"></i>Start Returning Donor
                </button>
            </div>
        </div>
    </div>
</body>
</html>



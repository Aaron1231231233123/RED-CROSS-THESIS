<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* General Body Styling */
        body {
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        /* Reduce Left Margin for Main Content */
        main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
            margin-left: 280px !important;
        }
        /* Header */
        .dashboard-home-header {
            position: fixed;
            top: 0;
            left: 240px;
            width: calc(100% - 240px);
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            z-index: 1000;
            transition: left 0.3s ease, width 0.3s ease;
        }
        /* Sidebar Styling */
        .dashboard-home-sidebar {
            height: 100vh;
            overflow-y: auto;
            position: fixed;
            width: 240px;
            background-color: #ffffff;
            border-right: 1px solid #ddd;
            padding: 20px;
            transition: width 0.3s ease;
        }
        .dashboard-home-sidebar a {
            text-decoration: none;
            color: #333;
            display: block;
            padding: 10px;
            border-radius: 5px;
        }
        .dashboard-home-sidebar a.active, 
        .dashboard-home-sidebar a:hover {
            background-color: #e9ecef;
            font-weight: bold;
        }
        /* Main Content Styling */
        .dashboard-home-main {
            margin-left: 240px;
            margin-top: 70px;
            min-height: 100vh;
            overflow-x: hidden;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        .custom-margin {
            margin: 30px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            margin-top: 100px;
}
        .chart-container {
            width: 100%;
            height: 400px;
        }
        #scheduleDateTime {
    opacity: 0;
    transition: opacity 0.5s ease-in-out;
}
.sort-indicator{
    cursor: pointer;
}
th {
    cursor: pointer;
    position: relative;
}

.sort-indicator {
    margin-left: 5px;
    font-size: 0.8em;
    color: #666;
}

.asc .sort-indicator {
    color: #ffffff;
}

.desc .sort-indicator {
    color: #ffffff;
}
/* Timeline Styling */
.timeline {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin: 20px 0;
}

.timeline::before {
    content: "";
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 2px;
    background-color: #ddd;
    z-index: 1;
}

.timeline-step {
    position: relative;
    z-index: 2;
    background-color: #fff;
    padding: 5px 10px;
    border: 2px solid #ddd;
    border-radius: 20px;
    text-align: center;
    font-size: 14px;
    color: #666;
}

.timeline-step[data-status="active"] {
    border-color: #0d6efd;
    color: #0d6efd;
    font-weight: bold;
}

.timeline-step[data-status="completed"] {
    border-color: #198754;
    background-color: #198754;
    color: #fff;
}
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="dashboard-home-header bg-light p-3 border-bottom d-flex justify-content-between align-items-center">
            <h4 >Hospital Request Dashboard</h4>
            <!-- Request Blood Button -->
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#bloodRequestModal">Request Blood</button>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block dashboard-home-sidebar">
                <input type="text" class="form-control mb-3" placeholder="Search...">
                <a href="#"><i class="fas fa-home me-2"></i>Home</a>
                <a href="#" class="active"><i class="fas fa-tint me-2"></i>Your Requests</a>
                <a href="#"><i class="fas fa-users me-2"></i>Blood History</a>
            </nav>

            <!-- Main Content -->
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div class="container-fluid p-4 custom-margin">
                        <h2 class="card-title mb-3">Your Requests</h2>
                         <!-- Search Bar -->
        <div class="mb-3">
            <input type="text" id="searchRequest" class="form-control" placeholder="Search requests...">
        </div>

        <!-- Requests Table -->
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <thead class="table-dark">
                    <tr>
                        <th>Request ID </th>
                        <th>Blood Type </th>
                        <th>Quantity </th>
                        <th>Urgency </th>
                        <th>Status </th>
                        <th>Requested On </th>
                        <th>Expected Delivery </th>
                        <th>Action</th>
                        
                    </tr>
                </thead>
                
            </thead>
            <tbody id="requestTable">
                <tr data-bs-toggle="modal" data-bs-target="#requestModal">
                    <td>REQ-001</td>
                    <td>A+</td>
                    <td>5 Units</td>
                    <td class="text-danger fw-bold">High</td>
                    <td class="text-danger">Pending</td>
                    <td>2025-03-15</td>
                    <td>2025-03-16</td>
                    <td>
                        <button class="btn btn-sm btn-primary">Track</button>
                        <button class="btn btn-sm btn-secondary">Reorder</button>
                    </td>
                </tr>
                <tr data-bs-toggle="modal" data-bs-target="#requestModal">
                    <td>REQ-002</td>
                    <td>O+</td>
                    <td>3 Units</td>
                    <td class="text-warning fw-bold">Medium</td>
                    <td class="text-success">Completed</td>
                    <td>2025-03-14</td>
                    <td>2025-03-14</td>
                    <td>
                        <button class="btn btn-sm btn-secondary">Reorder</button>
                    </td>
                </tr>
            </tbody>
        </table>
                    </div>
                </main>
            </div>
        </div>



<!-- Blood Request Modal -->
<div class="modal fade" id="bloodRequestModal" tabindex="-1" aria-labelledby="bloodRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bloodRequestModalLabel">Referral for Blood Shipment Slip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="mb-3">
                        <label class="form-label">Patient Name</label>
                        <input type="text" class="form-control">
                    </div>
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Age</label>
                            <input type="number" class="form-control">
                        </div>
                        <div class="col">
                            <label class="form-label">Gender</label>
                            <select class="form-select">
                                <option>Male</option>
                                <option>Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Diagnosis</label>
                        <input type="text" class="form-control">
                    </div>
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Blood Type</label>
                            <select class="form-select">
                                <option>A</option>
                                <option>B</option>
                                <option>O</option>
                                <option>AB</option>
                            </select>
                        </div>
                        <div class="col">
                            <label class="form-label">RH</label>
                            <select class="form-select">
                                <option>Positive</option>
                                <option>Negative</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Component</label>
                        <input type="text" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Number of Units</label>
                        <input type="number" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">When Needed</label>
                        <select id="whenNeeded" class="form-select">
                            <option value="ASAP">ASAP</option>
                            <option value="Scheduled">Scheduled</option>
                        </select>
                    </div>
                    
                    <div id="scheduleDateTime" class="mb-3 d-none">
                        <label class="form-label">Scheduled Date & Time</label>
                        <input type="datetime-local" class="form-control">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Reorder Modal -->
<div class="modal fade" id="bloodReorderModal" tabindex="-1" aria-labelledby="bloodReorderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bloodReorderModalLabel">Reorder Blood Shipment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form>
                    <!-- Patient Name -->
                    <div class="mb-3">
                        <label class="form-label">Patient Name</label>
                        <input type="text" class="form-control" id="reorderPatientName">
                    </div>

                    <!-- Age and Gender -->
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Age</label>
                            <input type="number" class="form-control" id="reorderAge">
                        </div>
                        <div class="col">
                            <label class="form-label">Gender</label>
                            <select class="form-select" id="reorderGender">
                                <option>Male</option>
                                <option>Female</option>
                            </select>
                        </div>
                    </div>

                    <!-- Diagnosis -->
                    <div class="mb-3">
                        <label class="form-label">Diagnosis</label>
                        <input type="text" class="form-control" id="reorderDiagnosis">
                    </div>

                    <!-- Blood Type and RH -->
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Blood Type</label>
                            <select class="form-select" id="reorderBloodType">
                                <option>A</option>
                                <option>B</option>
                                <option>O</option>
                                <option>AB</option>
                            </select>
                        </div>
                        <div class="col">
                            <label class="form-label">RH</label>
                            <select class="form-select" id="reorderRH">
                                <option>Positive</option>
                                <option>Negative</option>
                            </select>
                        </div>
                    </div>

                    <!-- Component -->
                    <div class="mb-3">
                        <label class="form-label">Component</label>
                        <input type="text" class="form-control" id="reorderComponent">
                    </div>

                    <!-- Number of Units -->
                    <div class="mb-3">
                        <label class="form-label">Number of Units</label>
                        <input type="number" class="form-control" id="reorderUnits" min="1">
                    </div>

                    <!-- When Needed -->
                    <div class="mb-3">
                        <label class="form-label">When Needed</label>
                        <select id="reorderWhenNeeded" class="form-select">
                            <option value="ASAP">ASAP</option>
                            <option value="Scheduled">Scheduled</option>
                        </select>
                    </div>

                    <!-- Scheduled Date & Time (Hidden by Default) -->
                    <div id="reorderScheduleDateTime" class="mb-3 d-none">
                        <label class="form-label">Scheduled Date & Time</label>
                        <input type="datetime-local" class="form-control" id="reorderScheduledDateTime">
                    </div>

                    <!-- Buttons -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Confirm Reorder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Track Modal -->
<div class="modal fade" id="trackModal" tabindex="-1" aria-labelledby="trackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="trackModalLabel">Track Blood Shipment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Current Status -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Current Status</label>
                    <p id="trackStatus" class="text-success fw-bold">Your blood is being processed.</p>
                </div>

                <!-- Timeline of Stages -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Progress</label>
                    <div id="trackTimeline" class="timeline">
                        <div class="timeline-step" data-status="stored">In Storage</div>
                        <div class="timeline-step" data-status="distributed">Being Distributed</div>
                        <div class="timeline-step" data-status="delivered">Delivered</div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

     <script>
      document.addEventListener("DOMContentLoaded", function () {
    let headers = document.querySelectorAll("th");

    headers.forEach((header, index) => {
        if (index < headers.length - 1) { // Ignore the last column (Action)
            // Create a single sorting indicator for each column
            let icon = document.createElement("span");
            icon.classList.add("sort-indicator");
            icon.textContent = " ▼"; // Default neutral state
            header.appendChild(icon);

            // Add click event listener to sort the column
            header.addEventListener("click", function () {
                sortTable(index, header);
            });
        }
    });
});

function sortTable(columnIndex, header) {
    let table = document.querySelector("table");
    let tbody = table.querySelector("tbody");
    let rows = Array.from(tbody.rows);

    // Determine the current sorting order
    let isAscending = header.classList.contains("asc");
    header.classList.toggle("asc", !isAscending);
    header.classList.toggle("desc", isAscending);

    // Determine the data type of the column
    let type = (columnIndex === 2) ? "number" : 
               (columnIndex === 5 || columnIndex === 6) ? "date" : "text";

    // Sort the rows
    rows.sort((rowA, rowB) => {
        let cellA = rowA.cells[columnIndex].textContent.trim();
        let cellB = rowB.cells[columnIndex].textContent.trim();

        if (type === 'number') {
            return isAscending ? parseInt(cellA) - parseInt(cellB) : parseInt(cellB) - parseInt(cellA);
        } else if (type === 'date') {
            return isAscending ? new Date(cellA) - new Date(cellB) : new Date(cellB) - new Date(cellA);
        } else {
            return isAscending ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
        }
    });

    // Append sorted rows back to the table
    tbody.append(...rows);

    // Update the indicator for the active sorted column
    let sortIcon = header.querySelector(".sort-indicator");
    sortIcon.textContent = isAscending ? " ▲" : " ▼"; // Toggle between ▲ and ▼
}
//Placeholder Static Data
document.addEventListener("DOMContentLoaded", function () {
    // Add click event to all Reorder buttons
    document.querySelectorAll(".btn-secondary").forEach(button => {
        button.addEventListener("click", function (event) {
            event.stopPropagation(); // Prevent row click event from firing

            // Get the row data
            let row = this.closest("tr");
            let cells = row.querySelectorAll("td");

            // Extract data from the row
            let requestId = cells[0].textContent;
            let bloodType = cells[1].textContent;
            let quantity = cells[2].textContent.replace(" Units", ""); // Remove " Units"
            let urgency = cells[3].textContent;
            let status = cells[4].textContent;
            let requestedOn = cells[5].textContent;
            let expectedDelivery = cells[6].textContent;

            // Pre-fill the reorder modal form
            let modal = document.getElementById("bloodReorderModal");
            modal.querySelector("#reorderPatientName").value = "Patient Name"; // Replace with actual patient name if available
            modal.querySelector("#reorderAge").value = 30; // Replace with actual age if available
            modal.querySelector("#reorderGender").value = "Male"; // Replace with actual gender if available
            modal.querySelector("#reorderDiagnosis").value = "Diagnosis"; // Replace with actual diagnosis if available
            modal.querySelector("#reorderBloodType").value = bloodType.split("+")[0]; // Extract blood type (e.g., "A" from "A+")
            modal.querySelector("#reorderRH").value = bloodType.includes("+") ? "Positive" : "Negative"; // Extract RH
            modal.querySelector("#reorderComponent").value = "Component"; // Replace with actual component if available
            modal.querySelector("#reorderUnits").value = quantity; // Pre-fill quantity
            modal.querySelector("#reorderWhenNeeded").value = "ASAP"; // Default to ASAP
            modal.querySelector("#reorderScheduledDateTime").value = expectedDelivery; // Pre-fill expected delivery

            // Show/hide scheduled date & time based on "When Needed"
            let whenNeeded = modal.querySelector("#reorderWhenNeeded");
            let scheduleDateTime = modal.querySelector("#reorderScheduleDateTime");
            whenNeeded.addEventListener("change", function () {
                if (this.value === "Scheduled") {
                    scheduleDateTime.classList.remove("d-none");
                } else {
                    scheduleDateTime.classList.add("d-none");
                }
            });

            // Open the reorder modal
            new bootstrap.Modal(modal).show();
        });
    });
});
//Track modal
document.addEventListener("DOMContentLoaded", function () {
    // Add click event to all Track buttons
    document.querySelectorAll(".btn-primary").forEach(button => {
        button.addEventListener("click", function (event) {
            event.stopPropagation(); // Prevent row click event from firing

            // Get the row data
            let row = this.closest("tr");
            let cells = row.querySelectorAll("td");

            // Extract data from the row
            let requestId = cells[0].textContent;
            let status = cells[4].textContent.trim(); // Current status (e.g., "Pending", "In Transit", "Delivered")

            // Pre-fill the track modal with data
            let modal = document.getElementById("trackModal");
            let timelineSteps = modal.querySelectorAll(".timeline-step");

            // Update the current status
            modal.querySelector("#trackStatus").textContent = `Your blood is ${status.toLowerCase()}.`;

            // Update the timeline based on the current status
            timelineSteps.forEach(step => {
                step.removeAttribute("data-status"); // Reset all steps
            });

            switch (status) {
                case "Being Processed":
                    timelineSteps[0].setAttribute("data-status", "active");
                    break;
                case "In Storage":
                    timelineSteps[0].setAttribute("data-status", "completed");
                    timelineSteps[1].setAttribute("data-status", "active");
                    break;
                case "Being Distributed":
                    timelineSteps[0].setAttribute("data-status", "completed");
                    timelineSteps[1].setAttribute("data-status", "completed");
                    timelineSteps[2].setAttribute("data-status", "active");
                    break;
                case "Delivered":
                    timelineSteps.forEach(step => step.setAttribute("data-status", "completed"));
                    break;
                default:
                    timelineSteps[0].setAttribute("data-status", "active");
            }

            // Open the track modal
            new bootstrap.Modal(modal).show();
        });
    });
});
         //Shows the date and time if the Scheduled is selected
         document.getElementById('whenNeeded').addEventListener('change', function() {
            var scheduleDateTime = document.getElementById('scheduleDateTime');

            if (this.value === 'Scheduled') { // Ensure it matches exactly
                scheduleDateTime.classList.remove('d-none'); // Show the date picker
                scheduleDateTime.style.opacity = 0;
                setTimeout(() => scheduleDateTime.style.opacity = 1, 10); // Smooth fade-in
            } else {
                scheduleDateTime.style.opacity = 0;
                setTimeout(() => scheduleDateTime.classList.add('d-none'), 500); // Hide after fade-out
            }
        });

     </script>
</body>
</html>
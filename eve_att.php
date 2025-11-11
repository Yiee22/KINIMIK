<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['usertype'], ['Secretary', 'Treasurer', 'Auditor', 'Social Manager', 'Senator', 'Governor', 'Vice Governor'])) {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "csso");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Helper function
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// ===== CREATE EVENT (from modal) =====
if (isset($_POST['create_event'])) {
    $event_Name = sanitize($_POST['event_Name']);
    $event_Date = sanitize($_POST['event_Date']);
    $location   = sanitize($_POST['location']);
    
    $conn->begin_transaction();
    
    try {
        // Check duplicate
        $check = $conn->prepare("SELECT event_Name FROM event WHERE event_Name = ?");
        $check->bind_param("s", $event_Name);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("Event name already exists!");
        }
        
        $stmt = $conn->prepare("INSERT INTO event (event_Name, event_Date, location) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $event_Name, $event_Date, $location);
        $stmt->execute();
        $stmt->close();
        $check->close();
        
        $conn->commit();
        
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            window.onload = function() {
                Swal.fire({
                    title: 'Success!',
                    text: 'Event successfully created!',
                    icon: 'success',
                    confirmButtonColor: '#0ea5e9'
                }).then(() => {
                    window.location = 'eve_att.php';
                });
            };
        </script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            window.onload = function() {
                Swal.fire({
                    title: 'Error!',
                    text: '" . addslashes($e->getMessage()) . "',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            };
        </script>";
    }
}

// ===== SEARCH =====
$search = $_GET['search'] ?? '';

// Get events with search filter
if (!empty($search)) {
    $stmt = $conn->prepare("SELECT * FROM event WHERE event_Name LIKE ? OR location LIKE ? ORDER BY event_Date DESC");
    $search_param = "%$search%";
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM event ORDER BY event_Date DESC";
    $result = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Select Event for Attendance | CSSO</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: #f0f4f8;
  color: #1e3a5f;
  padding: 0;
  min-height: 100vh;
}

.container {
  padding: 24px;
  max-width: 1200px;
  margin: 0 auto;
}

/* Header */
.page-header {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 3px solid #e0f2fe;
}

.page-header i {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: white;
  padding: 14px;
  border-radius: 12px;
  font-size: 22px;
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
}

.page-header h2 {
  font-size: 26px;
  font-weight: 600;
  color: #1e3a5f;
  letter-spacing: -0.3px;
}

/* Instruction Card */
.instruction-card {
  background: linear-gradient(135deg, #dbeafe 0%, #e0f2fe 100%);
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 20px;
  border-left: 4px solid #0ea5e9;
}

.instruction-card h4 {
  font-size: 16px;
  font-weight: 600;
  color: #0c4a6e;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.instruction-card p {
  font-size: 14px;
  color: #0284c7;
  margin: 0;
}

/* Controls Section */
.controls {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  margin-bottom: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.filters {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.filters input[type="text"] {
  padding: 10px 14px;
  border-radius: 8px;
  border: 2px solid #e2e8f0;
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  background: white;
  color: #334155;
  font-weight: 500;
  min-width: 220px;
}

.filters input[type="text"]:focus {
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

/* Buttons */
.btn {
  padding: 10px 18px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none !important;
}

.search-btn {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
}

.search-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
}

.clear-btn {
  background: #64748b;
  color: white;
}

.clear-btn:hover {
  background: #475569;
  transform: translateY(-2px);
}

.continue-btn {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
}

.continue-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
}

.create-btn {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.create-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.back-btn {
  background: #64748b;
  color: white;
}

.back-btn:hover {
  background: #475569;
  transform: translateY(-2px);
}

.btn-group {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

/* Table Container */
.table-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
  overflow: hidden;
}

table {
  width: 100%;
  border-collapse: collapse;
}

thead {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
}

th {
  padding: 16px 14px;
  text-align: left;
  color: white;
  font-weight: 600;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

td {
  padding: 16px 14px;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
  font-size: 14px;
}

tbody tr {
  transition: all 0.2s ease;
  cursor: pointer;
}

tbody tr:hover {
  background: #f0f9ff;
  transform: scale(1.01);
}

tbody tr.selected {
  background: #dbeafe;
  border-left: 4px solid #0ea5e9;
}

tbody tr:last-child td {
  border-bottom: none;
}

/* Badges */
.badge {
  padding: 6px 12px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 12px;
  display: inline-block;
}

.badge-location {
  background: #d1fae5;
  color: #065f46;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #64748b;
}

.empty-state i {
  font-size: 64px;
  color: #cbd5e1;
  margin-bottom: 16px;
}

.empty-state h3 {
  font-size: 18px;
  color: #475569;
  margin-bottom: 8px;
}

.empty-state p {
  font-size: 14px;
  color: #94a3b8;
}

/* Modal Styles */
.modal-content {
  border: none;
  border-radius: 12px;
}

.modal-header {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: white;
  border-radius: 12px 12px 0 0;
  padding: 20px;
}

.modal-title {
  font-weight: 600;
  font-size: 18px;
}

.modal-body {
  padding: 24px;
}

.form-label {
  font-weight: 600;
  color: #334155;
  margin-bottom: 8px;
  font-size: 14px;
}

.form-control {
  padding: 12px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.3s;
}

.form-control:focus {
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.modal-footer {
  padding: 20px;
  border-top: 1px solid #f1f5f9;
}

.btn-primary {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  border: none;
  padding: 10px 20px;
  font-weight: 600;
}

.btn-secondary {
  background: #64748b;
  border: none;
  padding: 10px 20px;
  font-weight: 600;
}

.btn-primary:hover,
.btn-secondary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Responsive */
@media (max-width: 768px) {
  .controls {
    flex-direction: column;
    align-items: stretch;
  }
  
  .filters {
    flex-direction: column;
    width: 100%;
  }
  
  .filters input[type="text"] {
    width: 100%;
  }
  
  .btn-group {
    width: 100%;
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
    justify-content: center;
  }
  
  .table-container {
    overflow-x: auto;
  }
  
  table {
    min-width: 600px;
  }
}
</style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="page-header">
        <i class="fa-solid fa-user-check"></i>
        <h2>Select Event for Attendance</h2>
    </div>

    <!-- Instruction Card -->
    <div class="instruction-card">
        <h4>
            <i class="fa-solid fa-info-circle"></i>
            How to proceed:
        </h4>
        <p>Click on any event from the list below to select it, then click the "Continue" button to start taking attendance for that event.</p>
    </div>

    <!-- Controls with Search -->
    <div class="controls">
        <button class="btn back-btn" onclick="goBackToDashboard()">
            <i class="fa fa-arrow-left"></i> Back
        </button>

        <form method="get" class="filters">
            <input type="text" name="search" placeholder="Search event..." value="<?= htmlspecialchars($search) ?>">
            
            <button type="submit" class="btn search-btn">
                <i class="fa fa-search"></i> Search
            </button>
            
            <button type="button" class="btn clear-btn" onclick="window.location='eve_att.php'">
                <i class="fa fa-rotate"></i> Clear
            </button>
        </form>

        <div class="btn-group">
            <button class="btn continue-btn" id="continueBtn" disabled onclick="proceedToAttendance()">
                <i class="fa fa-arrow-right"></i> Continue
            </button>
            <button class="btn create-btn" data-bs-toggle="modal" data-bs-target="#createEventModal">
                <i class="fa fa-plus"></i> Create Event
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="table-container">
        <table id="eventsTable">
            <thead>
                <tr>
                    <th>Event Name</th>
                    <th>Event Date</th>
                    <th>Location</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr onclick="selectEvent(this, '<?= htmlspecialchars($row['event_Name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['event_Date'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['location'], ENT_QUOTES) ?>')">
                    <td><strong><?= htmlspecialchars($row['event_Name']) ?></strong></td>
                    <td><?= date('M d, Y', strtotime($row['event_Date'])) ?></td>
                    <td>
                        <span class="badge badge-location">
                            <i class="fa fa-map-marker-alt"></i> <?= htmlspecialchars($row['location']) ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">
                        <div class="empty-state">
                            <i class="fa fa-calendar-times"></i>
                            <h3>No Events Available</h3>
                            <p><?= !empty($search) ? 'No events match your search criteria.' : 'Create a new event to get started with attendance tracking.' ?></p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CREATE EVENT MODAL -->
<div class="modal fade" id="createEventModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Create New Event</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Event Name <span style="color:#ef4444;">*</span></label>
            <input type="text" name="event_Name" class="form-control" placeholder="Enter event name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Event Date <span style="color:#ef4444;">*</span></label>
            <input type="date" name="event_Date" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Location <span style="color:#ef4444;">*</span></label>
            <input type="text" name="location" class="form-control" placeholder="Enter location" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fa fa-times"></i> Cancel
          </button>
          <button type="submit" name="create_event" class="btn btn-primary">
            <i class="fa fa-check"></i> Save
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedEvent = null;

function selectEvent(row, eventName, eventDate, location) {
    // Remove previous selection
    document.querySelectorAll('#eventsTable tbody tr').forEach(tr => {
        tr.classList.remove('selected');
    });
    
    // Add selection to clicked row
    row.classList.add('selected');
    
    // Store selected event data
    selectedEvent = {
        name: eventName,
        date: eventDate,
        location: location
    };
    
    // Enable continue button
    document.getElementById('continueBtn').disabled = false;
}

function proceedToAttendance() {
    if (selectedEvent) {
        // Redirect to addattendance.php with event data
        window.location.href = 'addattendance.php?event=' + encodeURIComponent(selectedEvent.name) + 
                               '&date=' + encodeURIComponent(selectedEvent.date) + 
                               '&location=' + encodeURIComponent(selectedEvent.location);
    }
}

function goBackToDashboard() {
    // Redirect to user_dashboard.php with Attendance section active
    // The dashboard should load with Attendance menu highlighted
    window.location.href = 'user_dashboard.php?page=attendance';
}
</script>
</body>
</html>
<?php $conn->close(); ?>
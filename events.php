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

// Year Level Display Mapping
function formatYearLevel($yearLevel) {
    $mapping = [
        '1stYearLevel' => '1st Year',
        '2ndYearLevel' => '2nd Year',
        '3rdYearLevel' => '3rd Year',
        '4thYearLevel' => '4th Year',
        'AllLevels' => 'All Levels'
    ];
    return $mapping[$yearLevel] ?? $yearLevel;
}

// ===== CREATE EVENT =====
if (isset($_POST['create_event'])) {
    $event_Name = sanitize($_POST['event_Name']);
    $event_Date = sanitize($_POST['event_Date']);
    $location   = sanitize($_POST['location']);
    $time_session = sanitize($_POST['time_session']);
    $year_level = sanitize($_POST['year_level']);
    
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
        
        $stmt = $conn->prepare("INSERT INTO event (event_Name, event_Date, location, Time_Session, YearLevel) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $event_Name, $event_Date, $location, $time_session, $year_level);
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
                    window.location = 'events.php';
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

// ===== UPDATE EVENT =====
if (isset($_POST['update_event'])) {
    $original_name = sanitize($_POST['original_name']);
    $event_Name = sanitize($_POST['event_Name']);
    $event_Date = sanitize($_POST['event_Date']);
    $location   = sanitize($_POST['location']);
    $time_session = sanitize($_POST['time_session']);
    $year_level = sanitize($_POST['year_level']);
    
    $conn->begin_transaction();
    
    try {
        if ($event_Name !== $original_name) {
            $check = $conn->prepare("SELECT event_Name FROM event WHERE event_Name = ?");
            $check->bind_param("s", $event_Name);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Event name already exists!");
            }
            $check->close();
        }
        
        $stmt = $conn->prepare("UPDATE event SET event_Name=?, event_Date=?, location=?, Time_Session=?, YearLevel=? WHERE event_Name=?");
        $stmt->bind_param("ssssss", $event_Name, $event_Date, $location, $time_session, $year_level, $original_name);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            window.onload = function() {
                Swal.fire({
                    title: 'Success!',
                    text: 'Event successfully updated!',
                    icon: 'success',
                    confirmButtonColor: '#0ea5e9'
                }).then(() => {
                    window.location = 'events.php';
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

// ===== DELETE EVENT =====
if (isset($_GET['delete_name'])) {
    $event_Name = sanitize($_GET['delete_name']);
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("DELETE FROM event WHERE event_Name = ?");
        $stmt->bind_param("s", $event_Name);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            window.onload = function() {
                Swal.fire({
                    title: 'Deleted!',
                    text: 'Event successfully deleted!',
                    icon: 'success',
                    confirmButtonColor: '#0ea5e9'
                }).then(() => {
                    window.location = 'events.php';
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
                    text: 'Failed to delete event.',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            };
        </script>";
    }
}

// ===== SEARCH =====
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM event";
if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $sql .= " WHERE event_Name LIKE '%$search_safe%' OR location LIKE '%$search_safe%'";
}
$sql .= " ORDER BY event_Date DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Events | CSSO</title>
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
}

.container {
  padding: 24px;
  max-width: 1400px;
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

.add-btn {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.add-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
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
  padding: 14px;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
  font-size: 14px;
}

tbody tr {
  transition: all 0.2s ease;
}

tbody tr:hover {
  background: #f0f9ff;
  transform: scale(1.01);
}

tbody tr:last-child td {
  border-bottom: none;
}

.badge {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  display: inline-block;
}

.badge-time {
  background: #fef3c7;
  color: #92400e;
}

.badge-year {
  background: #e0e7ff;
  color: #3730a3;
}

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: 8px;
}

.action-btn {
  padding: 8px 12px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 13px;
}

.edit-btn {
  background: #fbbf24;
  color: #78350f;
}

.edit-btn:hover {
  background: #f59e0b;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(251, 191, 36, 0.3);
}

.delete-btn {
  background: #ef4444;
  color: white;
}

.delete-btn:hover {
  background: #dc2626;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #64748b;
  font-size: 15px;
}

.empty-state i {
  font-size: 48px;
  color: #cbd5e1;
  margin-bottom: 12px;
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

.modal-header.edit-header {
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
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

.form-control, .form-select {
  padding: 12px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.3s;
}

.form-control:focus, .form-select:focus {
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

.btn-success {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
.btn-success:hover,
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
  
  .table-container {
    overflow-x: auto;
  }
  
  table {
    min-width: 800px;
  }
}
</style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="page-header">
        <i class="fa-solid fa-calendar-alt"></i>
        <h2>Events Management</h2>
    </div>

    <!-- Controls -->
    <div class="controls">
        <form method="get" class="filters">
            <input type="text" name="search" placeholder="Search event..." value="<?= htmlspecialchars($search) ?>">
            
            <button type="submit" class="btn search-btn">
                <i class="fa fa-search"></i> Search
            </button>
            
            <button type="button" class="btn clear-btn" onclick="window.location='events.php'">
                <i class="fa fa-rotate"></i> Clear
            </button>
        </form>

        <button class="btn add-btn" data-bs-toggle="modal" data-bs-target="#addEventModal">
            <i class="fa fa-plus"></i> Add Event
        </button>
    </div>

    <!-- Table -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Event Name</th>
                    <th>Event Date</th>
                    <th>Location</th>
                    <th>Time Session</th>
                    <th>Year Level</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['event_Name']) ?></strong></td>
                    <td><?= date('M d, Y', strtotime($row['event_Date'])) ?></td>
                    <td><?= htmlspecialchars($row['location']) ?></td>
                    <td><span class="badge badge-time"><?= htmlspecialchars($row['Time_Session']) ?></span></td>
                    <td><span class="badge badge-year"><?= formatYearLevel($row['YearLevel']) ?></span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn edit-btn" 
                                    data-name="<?= htmlspecialchars($row['event_Name']) ?>"
                                    data-date="<?= $row['event_Date'] ?>"
                                    data-location="<?= htmlspecialchars($row['location']) ?>"
                                    data-time="<?= htmlspecialchars($row['Time_Session']) ?>"
                                    data-year="<?= htmlspecialchars($row['YearLevel']) ?>"
                                    onclick="editEvent(this)"
                                    title="Edit">
                                <i class="fa fa-pen"></i>
                            </button>
                            <button class="action-btn delete-btn" 
                                    onclick="confirmDelete('<?= htmlspecialchars($row['event_Name']) ?>')"
                                    title="Delete">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fa fa-calendar-times"></i>
                            <p>No events found.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD EVENT MODAL -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Add New Event</h5>
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
          <div class="mb-3">
            <label class="form-label">Time Session <span style="color:#ef4444;">*</span></label>
            <select name="time_session" class="form-select" required>
              <option value="">Select Time Session</option>
              <option value="AM Session">AM Session</option>
              <option value="PM Session">PM Session</option>
              <option value="Full Day">Full Day</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Year Level <span style="color:#ef4444;">*</span></label>
            <select name="year_level" class="form-select" required>
              <option value="">Select Year Level</option>
              <option value="1stYearLevel">1st Year</option>
              <option value="2ndYearLevel">2nd Year</option>
              <option value="3rdYearLevel">3rd Year</option>
              <option value="4thYearLevel">4th Year</option>
              <option value="AllLevels">All Levels</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="create_event" class="btn btn-primary">
            <i class="fa fa-check"></i> Create
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT EVENT MODAL -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header edit-header">
          <h5 class="modal-title">Edit Event</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="original_name" id="original_name">
          <div class="mb-3">
            <label class="form-label">Event Name <span style="color:#ef4444;">*</span></label>
            <input type="text" name="event_Name" id="edit_event_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Event Date <span style="color:#ef4444;">*</span></label>
            <input type="date" name="event_Date" id="edit_event_date" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Location <span style="color:#ef4444;">*</span></label>
            <input type="text" name="location" id="edit_location" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Time Session <span style="color:#ef4444;">*</span></label>
            <select name="time_session" id="edit_time_session" class="form-select" required>
              <option value="">Select Time Session</option>
              <option value="AM Session">AM Session</option>
              <option value="PM Session">PM Session</option>
              <option value="Full Day">Full Day</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Year Level <span style="color:#ef4444;">*</span></label>
            <select name="year_level" id="edit_year_level" class="form-select" required>
              <option value="">Select Year Level</option>
              <option value="1stYearLevel">1st Year</option>
              <option value="2ndYearLevel">2nd Year</option>
              <option value="3rdYearLevel">3rd Year</option>
              <option value="4thYearLevel">4th Year</option>
              <option value="AllLevels">All Levels</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="update_event" class="btn btn-success">
            <i class="fa fa-save"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editEvent(button) {
    const name = button.dataset.name;
    const date = button.dataset.date;
    const location = button.dataset.location;
    const time = button.dataset.time;
    const year = button.dataset.year;
    
    document.getElementById('edit_event_name').value = name;
    document.getElementById('edit_event_date').value = date;
    document.getElementById('edit_location').value = location;
    document.getElementById('edit_time_session').value = time;
    document.getElementById('edit_year_level').value = year;
    document.getElementById('original_name').value = name;
    
    new bootstrap.Modal(document.getElementById('editEventModal')).show();
}

function confirmDelete(name) {
    Swal.fire({
        title: 'Delete Event?',
        text: "This will permanently delete the event: " + name,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'events.php?delete_name=' + encodeURIComponent(name);
        }
    });
}
</script>
</body>
</html>
<?php $conn->close(); ?>
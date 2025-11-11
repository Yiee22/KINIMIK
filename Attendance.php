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

// ✅ AJAX HANDLER: Update Excuse Letter
if (isset($_POST['update_excuse'])) {
    header('Content-Type: application/json');
    
    $attendance_id = intval($_POST['attendance_id']);
    $excuse_letter = $_POST['excuse_letter'];
    
    if ($excuse_letter === 'Yes') {
        // If excuse = Yes, set penalty to 0
        $update_sql = "UPDATE attendance SET ExcuseLetter = 'Yes', TotalPenalty = 0 WHERE attendance_id = ?";
    } else {
        // If excuse = No, recalculate penalty based on missing timestamps
        $update_sql = "UPDATE attendance SET 
            ExcuseLetter = 'No',
            TotalPenalty = (
                (CASE WHEN amLogin IS NULL OR amLogin = '' THEN 50 ELSE 0 END) +
                (CASE WHEN amLogout IS NULL OR amLogout = '' THEN 50 ELSE 0 END) +
                (CASE WHEN pmLogin IS NULL OR pmLogin = '' THEN 50 ELSE 0 END) +
                (CASE WHEN pmLogout IS NULL OR pmLogout = '' THEN 50 ELSE 0 END)
            )
            WHERE attendance_id = ?";
    }
    
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $attendance_id);
    
    if ($stmt->execute()) {
        // Get updated penalty
        $get_penalty = $conn->query("SELECT TotalPenalty, ExcuseLetter FROM attendance WHERE attendance_id = $attendance_id");
        $penalty_row = $get_penalty->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'new_penalty' => $penalty_row['TotalPenalty'],
            'excuse_letter' => $penalty_row['ExcuseLetter']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
    
    exit();
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$event_filter = $_GET['event'] ?? '';
$excuse_filter = $_GET['excuse'] ?? '';

// Build SQL query
$sql = "SELECT 
    a.attendance_id,
    a.students_id,
    CONCAT(sp.LastName, ', ', sp.FirstName) as FullName,
    sp.Course,
    sp.YearLevel,
    a.event_name,
    a.event_date,
    a.amLogin,
    a.amLogout,
    a.pmLogin,
    a.pmLogout,
    a.ExcuseLetter,
    a.TotalPenalty
FROM attendance a
LEFT JOIN student_profile sp ON a.students_id = sp.students_id
WHERE 1=1";

if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $sql .= " AND (a.students_id LIKE '%$search_term%' 
              OR sp.LastName LIKE '%$search_term%' 
              OR sp.FirstName LIKE '%$search_term%'
              OR a.event_name LIKE '%$search_term%')";
}

if (!empty($event_filter)) {
    $event_filter_term = $conn->real_escape_string($event_filter);
    $sql .= " AND a.event_name = '$event_filter_term'";
}

if (!empty($excuse_filter)) {
    $sql .= " AND a.ExcuseLetter = '$excuse_filter'";
}

$sql .= " ORDER BY a.event_date DESC, a.students_id ASC";
$result = $conn->query($sql);

// Get unique events for filter
$events_sql = "SELECT DISTINCT event_name FROM attendance ORDER BY event_name";
$events_result = $conn->query($events_sql);

// Calculate statistics
$stats_sql = "SELECT 
    COUNT(*) as total_records,
    COUNT(DISTINCT students_id) as unique_students,
    COUNT(DISTINCT event_name) as total_events,
    SUM(TotalPenalty) as total_penalties
FROM attendance";
$stats = $conn->query($stats_sql)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Attendance Records | CSSO</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
  max-width: 1600px;
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

/* Stats Cards */
.stats-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.stat-card {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  border-left: 4px solid #0ea5e9;
}

.stat-card h4 {
  font-size: 13px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
}

.stat-card p {
  font-size: 24px;
  font-weight: 700;
  color: #0ea5e9;
}

/* Controls Section */
.controls {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  margin-bottom: 20px;
}

.filters {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.filters select,
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
}

.filters select:focus,
.filters input[type="text"]:focus {
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.filters input[type="text"] {
  min-width: 250px;
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

.export-btn {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.export-btn:hover {
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

.table-scroll {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 1200px;
}

thead {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  position: sticky;
  top: 0;
  z-index: 10;
}

th {
  padding: 16px 14px;
  text-align: left;
  color: white;
  font-weight: 600;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
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
}

tbody tr:last-child td {
  border-bottom: none;
}

/* Badges */
.badge {
  padding: 4px 10px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 12px;
  display: inline-block;
  white-space: nowrap;
}

.badge-course {
  background: #dbeafe;
  color: #1e40af;
}

.badge-year {
  background: #e0e7ff;
  color: #3730a3;
}

.time-badge {
  background: #fef3c7;
  color: #92400e;
  font-family: 'Courier New', monospace;
}

.penalty-badge {
  background: #fee2e2;
  color: #991b1b;
  font-weight: 700;
}

/* Excuse Dropdown Styling */
.excuse-dropdown {
  padding: 6px 10px;
  border-radius: 6px;
  border: 2px solid #e2e8f0;
  font-weight: 500;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
  outline: none;
  background: white;
  color: #334155;
}

.excuse-dropdown:focus {
  border-color: #0ea5e9;
  box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.1);
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #64748b;
  font-size: 15px;
}

.empty-state i {
  font-size: 64px;
  color: #cbd5e1;
  margin-bottom: 16px;
}

/* Responsive */
@media (max-width: 768px) {
  .controls {
    padding: 16px;
  }
  
  .filters {
    flex-direction: column;
    width: 100%;
  }
  
  .filters select,
  .filters input[type="text"],
  .btn {
    width: 100%;
  }
  
  .stats-container {
    grid-template-columns: 1fr;
  }
}
</style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="page-header">
        <i class="fa-solid fa-clipboard-list"></i>
        <h2>Attendance Records</h2>
    </div>

    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <h4>Total Records</h4>
            <p><?= number_format($stats['total_records']) ?></p>
        </div>
        <div class="stat-card">
            <h4>Unique Students</h4>
            <p><?= number_format($stats['unique_students']) ?></p>
        </div>
        <div class="stat-card">
            <h4>Total Events</h4>
            <p><?= number_format($stats['total_events']) ?></p>
        </div>
        <div class="stat-card">
            <h4>Total Penalties</h4>
            <p>₱<?= number_format($stats['total_penalties'], 2) ?></p>
        </div>
    </div>

    <!-- Controls -->
    <div class="controls">
        <form method="get" class="filters">
            <input type="text" 
                   name="search" 
                   placeholder="Search by Student ID, Name, or Event..." 
                   value="<?= htmlspecialchars($search) ?>">
            
            <select name="event">
                <option value="">All Events</option>
                <?php while($event = $events_result->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($event['event_name']) ?>" 
                            <?= $event_filter === $event['event_name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($event['event_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <select name="excuse">
                <option value="">All Excuse Status</option>
                <option value="Yes" <?= $excuse_filter === 'Yes' ? 'selected' : '' ?>>With Excuse</option>
                <option value="No" <?= $excuse_filter === 'No' ? 'selected' : '' ?>>No Excuse</option>
            </select>
            
            <button type="submit" class="btn search-btn">
                <i class="fa fa-search"></i> Search
            </button>
            
            <button type="button" class="btn clear-btn" onclick="window.location='attendance.php'">
                <i class="fa fa-rotate"></i> Clear
            </button>

            <button type="button" class="btn export-btn" onclick="exportToCSV()">
                <i class="fa fa-download"></i> Export CSV
            </button>
        </form>
    </div>

    <!-- Table -->
    <div class="table-container">
        <div class="table-scroll">
            <table id="attendanceTable">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Event Name</th>
                        <th>Event Date</th>
                        <th>AM Login</th>
                        <th>AM Logout</th>
                        <th>PM Login</th>
                        <th>PM Logout</th>
                        <th>Excuse Letter</th>
                        <th>Total Penalty</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr id="row-<?= $row['attendance_id'] ?>">
                        <td><strong><?= htmlspecialchars($row['students_id']) ?></strong></td>
                        <td><?= htmlspecialchars($row['FullName'] ?? 'N/A') ?></td>
                        <td>
                            <?php if ($row['Course']): ?>
                                <span class="badge badge-course"><?= htmlspecialchars($row['Course']) ?></span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['YearLevel']): ?>
                                <span class="badge badge-year"><?= htmlspecialchars($row['YearLevel']) ?></span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($row['event_name']) ?></strong></td>
                        <td><?= date('M d, Y', strtotime($row['event_date'])) ?></td>
                        <td>
                            <?php if ($row['amLogin']): ?>
                                <span class="time-badge"><?= htmlspecialchars($row['amLogin']) ?></span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['amLogout']): ?>
                                <span class="time-badge"><?= htmlspecialchars($row['amLogout']) ?></span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['pmLogin']): ?>
                                <span class="time-badge"><?= htmlspecialchars($row['pmLogin']) ?></span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['pmLogout']): ?>
                                <span class="time-badge"><?= htmlspecialchars($row['pmLogout']) ?></span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <select class="excuse-dropdown" 
                                    data-id="<?= $row['attendance_id'] ?>"
                                    onchange="updateExcuse(<?= $row['attendance_id'] ?>, this.value)">
                                <option value="Yes" <?= $row['ExcuseLetter'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                <option value="No" <?= $row['ExcuseLetter'] === 'No' ? 'selected' : '' ?>>No</option>
                            </select>
                        </td>
                        <td>
                            <span class="penalty-badge" id="penalty-<?= $row['attendance_id'] ?>">
                                ₱<?= number_format($row['TotalPenalty'], 2) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12">
                            <div class="empty-state">
                                <i class="fa fa-clipboard"></i>
                                <h3>No Attendance Records Found</h3>
                                <p>No records match your search criteria.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Auto-update Excuse Letter & Penalty
function updateExcuse(attendanceId, excuseLetter) {
    const dropdown = document.querySelector(`select[data-id="${attendanceId}"]`);
    const penaltyDisplay = document.getElementById(`penalty-${attendanceId}`);
    
    console.log('Updating Attendance ID:', attendanceId, 'to', excuseLetter);
    
    dropdown.disabled = true;
    
    // Send AJAX request
    fetch('attendance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `update_excuse=1&attendance_id=${attendanceId}&excuse_letter=${excuseLetter}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Server Response:', data);
        if (data.success) {
            // Update penalty display
            penaltyDisplay.textContent = '₱' + parseFloat(data.new_penalty).toFixed(2);
            console.log('Penalty updated to:', data.new_penalty);
        } else {
            console.error('Update failed:', data.message);
        }
        dropdown.disabled = false;
    })
    .catch(error => {
        console.error('AJAX Error:', error);
        dropdown.disabled = false;
    });
}

function exportToCSV() {
    const table = document.getElementById('attendanceTable');
    let csv = [];
    
    // Get headers
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent);
    csv.push(headers.join(','));
    
    // Get data rows
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        if (!row.querySelector('.empty-state')) {
            const cols = [];
            row.querySelectorAll('td').forEach((td, index) => {
                let text = '';
                
                // Handle excuse dropdown (column 10)
                if (index === 10) {
                    const dropdown = td.querySelector('.excuse-dropdown');
                    text = dropdown ? dropdown.value : td.textContent.trim();
                } else {
                    text = td.textContent.trim();
                }
                
                // Remove extra spaces and clean up
                text = text.replace(/\s+/g, ' ');
                // Escape quotes
                text = text.replace(/"/g, '""');
                cols.push(`"${text}"`);
            });
            csv.push(cols.join(','));
        }
    });
    
    if (csv.length <= 1) {
        Swal.fire('No Data', 'There are no records to export.', 'info');
        return;
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'attendance_records_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
    
    Swal.fire({
        icon: 'success',
        title: 'Exported!',
        text: 'Attendance records exported successfully!',
        timer: 2000,
        showConfirmButton: false
    });
}
</script>
</body>
</html>
<?php $conn->close(); ?>
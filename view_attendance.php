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

// Get event parameters
$event_name = $_GET['event'] ?? '';
$event_date = $_GET['date'] ?? '';

if (empty($event_name) || empty($event_date)) {
    header("Location: attendance.php");
    exit();
}

// Get event details
$event_sql = "SELECT * FROM event WHERE event_Name = ? AND event_Date = ?";
$stmt = $conn->prepare($event_sql);
$stmt->bind_param("ss", $event_name, $event_date);
$stmt->execute();
$event_result = $stmt->get_result();
$event = $event_result->fetch_assoc();

if (!$event) {
    header("Location: attendance.php");
    exit();
}

// Determine what columns to show based on Time_Session
$show_am = in_array($event['Time_Session'], ['AM Session', 'Full Day']);
$show_pm = in_array($event['Time_Session'], ['PM Session', 'Full Day']);

// Get attendance records for this event
$attendance_sql = "SELECT 
    a.*,
    sp.FirstName,
    sp.LastName,
    sp.Course,
    sp.YearLevel,
    sp.Section
FROM attendance a
LEFT JOIN student_profile sp ON a.students_id = sp.students_id
WHERE a.event_name = ? AND a.event_date = ?
ORDER BY sp.LastName, sp.FirstName";

$stmt = $conn->prepare($attendance_sql);
$stmt->bind_param("ss", $event_name, $event_date);
$stmt->execute();
$attendance_result = $stmt->get_result();

// Statistics
$total_students = $attendance_result->num_rows;
$present_am = 0;
$present_pm = 0;

while ($row = $attendance_result->fetch_assoc()) {
    if ($row['amLogin'] || $row['amLogout']) $present_am++;
    if ($row['pmLogin'] || $row['pmLogout']) $present_pm++;
}

// Reset pointer
$attendance_result->data_seek(0);

// Format Year Level for display
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Attendance - <?= htmlspecialchars($event_name) ?> | CSSO</title>
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
  max-width: 1200px;
  margin: 0 auto;
}

.back-btn {
  background: #64748b;
  color: white;
  padding: 8px 16px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
  margin-bottom: 20px;
}

.back-btn:hover {
  background: #475569;
  transform: translateY(-2px);
}

.event-header {
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
  margin-bottom: 20px;
  border-left: 4px solid #0ea5e9;
}

.event-title {
  font-size: 24px;
  font-weight: 600;
  color: #1e3a5f;
  margin-bottom: 12px;
}

.event-details {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  color: #64748b;
  font-size: 14px;
}

.event-detail {
  display: flex;
  align-items: center;
  gap: 6px;
}

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
  text-align: center;
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
  gap: 12px;
}

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
  text-decoration: none;
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

.manage-btn {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.manage-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

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

tbody tr:hover {
  background: #f0f9ff;
}

.badge {
  padding: 4px 10px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 12px;
  display: inline-block;
}

.badge-course {
  background: #dbeafe;
  color: #1e40af;
}

.badge-time {
  background: #fef3c7;
  color: #92400e;
}

.badge-year {
  background: #e0e7ff;
  color: #3730a3;
}

.time-display {
  font-size: 12px;
  color: #64748b;
  font-weight: 600;
}

.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #64748b;
}

.empty-state i {
  font-size: 48px;
  color: #cbd5e1;
  margin-bottom: 12px;
}

@media (max-width: 768px) {
  .controls {
    flex-direction: column;
    align-items: stretch;
  }
  
  .table-container {
    overflow-x: auto;
  }
  
  table {
    min-width: 800px;
  }
  
  .event-details {
    flex-direction: column;
    gap: 8px;
  }
  
  .stats-container {
    grid-template-columns: 1fr;
  }
}
</style>
</head>
<body>
<div class="container">
    <button onclick="goBackToEvents()" class="back-btn">
        <i class="fa fa-arrow-left"></i> Back to Events
    </button>

    <!-- Event Header -->
    <div class="event-header">
        <h1 class="event-title"><?= htmlspecialchars($event_name) ?></h1>
        <div class="event-details">
            <div class="event-detail">
                <i class="fa fa-calendar"></i>
                <?= date('F j, Y', strtotime($event_date)) ?>
            </div>
            <div class="event-detail">
                <i class="fa fa-map-marker-alt"></i>
                <?= htmlspecialchars($event['location']) ?>
            </div>
            <div class="event-detail">
                <i class="fa fa-clock"></i>
                <span class="badge badge-time"><?= htmlspecialchars($event['Time_Session']) ?></span>
            </div>
            <div class="event-detail">
                <i class="fa fa-users"></i>
                <span class="badge badge-year"><?= formatYearLevel($event['YearLevel']) ?></span>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-container">
        <div class="stat-card">
            <h4>Total Attendance</h4>
            <p><?= $total_students ?></p>
        </div>
        <?php if ($show_am): ?>
        <div class="stat-card">
            <h4>AM Session</h4>
            <p><?= $present_am ?></p>
        </div>
        <?php endif; ?>
        <?php if ($show_pm): ?>
        <div class="stat-card">
            <h4>PM Session</h4>
            <p><?= $present_pm ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Controls -->
    <div class="controls">
        <button class="btn export-btn" onclick="exportAttendance()">
            <i class="fa fa-download"></i> Export CSV
        </button>
        <a href="manage_attendance.php?event=<?= urlencode($event_name) ?>&date=<?= urlencode($event_date) ?>" class="btn manage-btn">
            <i class="fa fa-edit"></i> Manage Attendance
        </a>
    </div>

    <!-- Attendance Table -->
    <div class="table-container">
        <table id="attendanceTable">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Course/Year/Section</th>
                    <?php if ($show_am): ?>
                        <th>AM Login</th>
                        <th>AM Logout</th>
                    <?php endif; ?>
                    <?php if ($show_pm): ?>
                        <th>PM Login</th>
                        <th>PM Logout</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($attendance_result->num_rows > 0): ?>
                <?php while($row = $attendance_result->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['students_id']) ?></strong></td>
                    <td><?= htmlspecialchars($row['LastName'] . ', ' . $row['FirstName']) ?></td>
                    <td>
                        <span class="badge badge-course"><?= htmlspecialchars($row['Course']) ?></span>
                        <span class="badge"><?= htmlspecialchars($row['YearLevel']) ?></span>
                        <span class="badge"><?= htmlspecialchars($row['Section']) ?></span>
                    </td>
                    <?php if ($show_am): ?>
                        <td>
                            <span class="time-display">
                                <?= $row['amLogin'] ? date('g:i A', strtotime($row['amLogin'])) : '-' ?>
                            </span>
                        </td>
                        <td>
                            <span class="time-display">
                                <?= $row['amLogout'] ? date('g:i A', strtotime($row['amLogout'])) : '-' ?>
                            </span>
                        </td>
                    <?php endif; ?>
                    <?php if ($show_pm): ?>
                        <td>
                            <span class="time-display">
                                <?= $row['pmLogin'] ? date('g:i A', strtotime($row['pmLogin'])) : '-' ?>
                            </span>
                        </td>
                        <td>
                            <span class="time-display">
                                <?= $row['pmLogout'] ? date('g:i A', strtotime($row['pmLogout'])) : '-' ?>
                            </span>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= 3 + ($show_am ? 2 : 0) + ($show_pm ? 2 : 0) ?>">
                        <div class="empty-state">
                            <i class="fa fa-users-slash"></i>
                            <p>No attendance records found for this event.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Function to go back to events
function goBackToEvents() {
    if (window.top !== window.self) {
        window.top.location.href = 'user_dashboard.php';
        sessionStorage.setItem('loadAttendance', 'true');
    } else {
        window.location.href = 'user_dashboard.php';
        sessionStorage.setItem('loadAttendance', 'true');
    }
}

function exportAttendance() {
    const table = document.getElementById('attendanceTable');
    let csv = [];
    
    // Event Info Header
    csv.push('"Event: <?= addslashes($event_name) ?>"');
    csv.push('"Date: <?= date('F j, Y', strtotime($event_date)) ?>"');
    csv.push('"Location: <?= addslashes($event['location']) ?>"');
    csv.push('"Session: <?= $event['Time_Session'] ?>"');
    csv.push('"Year Level: <?= formatYearLevel($event['YearLevel']) ?>"');
    csv.push('');
    
    // Headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push('"' + th.textContent.trim() + '"');
    });
    csv.push(headers.join(','));
    
    // Data
    table.querySelectorAll('tbody tr').forEach(row => {
        if (row.querySelector('.empty-state')) return;
        
        const rowData = [];
        row.querySelectorAll('td').forEach((td, index) => {
            let text = '';
            
            // Handle badges
            const badges = td.querySelectorAll('.badge');
            if (badges.length > 0) {
                const badgeTexts = Array.from(badges).map(b => b.textContent.trim());
                text = badgeTexts.join(' ');
            } else {
                // Handle time display or regular text
                const timeDisplay = td.querySelector('.time-display');
                if (timeDisplay) {
                    text = timeDisplay.textContent.trim();
                } else {
                    text = td.textContent.trim();
                }
            }
            
            rowData.push('"' + text.replace(/"/g, '""') + '"');
        });
        
        if (rowData.length > 0) {
            csv.push(rowData.join(','));
        }
    });
    
    // Summary
    csv.push('');
    csv.push('"Summary"');
    csv.push('"Total Attendance: <?= $total_students ?>"');
    <?php if ($show_am): ?>
    csv.push('"AM Session Present: <?= $present_am ?>"');
    <?php endif; ?>
    <?php if ($show_pm): ?>
    csv.push('"PM Session Present: <?= $present_pm ?>"');
    <?php endif; ?>
    
    // Download
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'attendance_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $event_name) ?>_<?= $event_date ?>.csv';
    a.click();
    window.URL.revokeObjectURL(url);
    
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Attendance data exported successfully!',
        timer: 2000,
        showConfirmButton: false
    });
}
</script>
</body>
</html>
<?php $conn->close(); ?>
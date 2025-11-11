<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['usertype'], ['Secretary', 'Governor', 'Vice Governor'])) {
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

$message = '';
$message_type = '';

// ✅ FUNCTION: Calculate Total Penalty (HIDDEN from UI, but saves to DB)
function calculatePenalty($amLogin, $amLogout, $pmLogin, $pmLogout, $timeSession) {
    $penalty = 0;
    
    if ($timeSession === 'AM Session') {
        if (empty($amLogin)) $penalty += 50;
        if (empty($amLogout)) $penalty += 50;
    } elseif ($timeSession === 'PM Session') {
        if (empty($pmLogin)) $penalty += 50;
        if (empty($pmLogout)) $penalty += 50;
    } else {
        if (empty($amLogin)) $penalty += 50;
        if (empty($amLogout)) $penalty += 50;
        if (empty($pmLogin)) $penalty += 50;
        if (empty($pmLogout)) $penalty += 50;
    }
    
    return $penalty;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['attendance_data'])) {
        $success_count = 0;
        $error_count = 0;
        
        foreach ($_POST['attendance_data'] as $student_id => $data) {
            $students_id = $conn->real_escape_string($student_id);
            
            // Auto-clear data based on Time_Session
            if ($event['Time_Session'] === 'AM Session') {
                $amLogin = !empty($data['amLogin']) ? $conn->real_escape_string($data['amLogin']) : NULL;
                $amLogout = !empty($data['amLogout']) ? $conn->real_escape_string($data['amLogout']) : NULL;
                $pmLogin = NULL;
                $pmLogout = NULL;
            } elseif ($event['Time_Session'] === 'PM Session') {
                $amLogin = NULL;
                $amLogout = NULL;
                $pmLogin = !empty($data['pmLogin']) ? $conn->real_escape_string($data['pmLogin']) : NULL;
                $pmLogout = !empty($data['pmLogout']) ? $conn->real_escape_string($data['pmLogout']) : NULL;
            } else {
                $amLogin = !empty($data['amLogin']) ? $conn->real_escape_string($data['amLogin']) : NULL;
                $amLogout = !empty($data['amLogout']) ? $conn->real_escape_string($data['amLogout']) : NULL;
                $pmLogin = !empty($data['pmLogin']) ? $conn->real_escape_string($data['pmLogin']) : NULL;
                $pmLogout = !empty($data['pmLogout']) ? $conn->real_escape_string($data['pmLogout']) : NULL;
            }
            
            // ✅ CALCULATE PENALTY (silently in background)
            $totalPenalty = calculatePenalty($amLogin, $amLogout, $pmLogin, $pmLogout, $event['Time_Session']);
            
            // Check if record exists
            $check_sql = "SELECT attendance_id FROM attendance WHERE students_id = ? AND event_name = ? AND event_date = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("iss", $students_id, $event_name, $event_date);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // UPDATE with calculated penalty
                $update_sql = "UPDATE attendance SET amLogin = ?, amLogout = ?, pmLogin = ?, pmLogout = ?, TotalPenalty = ? WHERE students_id = ? AND event_name = ? AND event_date = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssssdiss", $amLogin, $amLogout, $pmLogin, $pmLogout, $totalPenalty, $students_id, $event_name, $event_date);
                
                if ($update_stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } else {
                // Insert new record
                if ($amLogin || $amLogout || $pmLogin || $pmLogout || $totalPenalty > 0) {
                    $user_id = $_SESSION['user_id'] ?? 1;
                    
                    $reg_sql = "SELECT registration_no FROM registration WHERE students_id = ? LIMIT 1";
                    $reg_stmt = $conn->prepare($reg_sql);
                    $reg_stmt->bind_param("s", $students_id);
                    $reg_stmt->execute();
                    $reg_result = $reg_stmt->get_result();
                    $reg_no = '';
                    if ($reg_row = $reg_result->fetch_assoc()) {
                        $reg_no = $reg_row['registration_no'];
                    }
                    
                    // INSERT with calculated penalty
                    $insert_sql = "INSERT INTO attendance (UserID, students_id, registration_no, event_name, event_date, location, amLogin, amLogout, pmLogin, pmLogout, TotalPenalty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("iissssssssd", $user_id, $students_id, $reg_no, $event_name, $event_date, $event['location'], $amLogin, $amLogout, $pmLogin, $pmLogout, $totalPenalty);
                    
                    if ($insert_stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                }
            }
        }
        
        if ($error_count === 0) {
            $message = "Attendance updated successfully for $success_count students!";
            $message_type = 'success';
        } else {
            $message = "Updated $success_count records, but $error_count records failed to update.";
            $message_type = 'error';
        }
    }
}

// ✅ CLEANUP & RECALCULATE (silent background process)
if ($event['Time_Session'] === 'AM Session') {
    $cleanup_sql = "UPDATE attendance 
                    SET pmLogin = NULL, 
                        pmLogout = NULL,
                        TotalPenalty = (
                            (CASE WHEN amLogin IS NULL THEN 50 ELSE 0 END) +
                            (CASE WHEN amLogout IS NULL THEN 50 ELSE 0 END)
                        )
                    WHERE event_name = ? AND event_date = ?";
    $cleanup_stmt = $conn->prepare($cleanup_sql);
    $cleanup_stmt->bind_param("ss", $event_name, $event_date);
    $cleanup_stmt->execute();
} elseif ($event['Time_Session'] === 'PM Session') {
    $cleanup_sql = "UPDATE attendance 
                    SET amLogin = NULL, 
                        amLogout = NULL,
                        TotalPenalty = (
                            (CASE WHEN pmLogin IS NULL THEN 50 ELSE 0 END) +
                            (CASE WHEN pmLogout IS NULL THEN 50 ELSE 0 END)
                        )
                    WHERE event_name = ? AND event_date = ?";
    $cleanup_stmt = $conn->prepare($cleanup_sql);
    $cleanup_stmt->bind_param("ss", $event_name, $event_date);
    $cleanup_stmt->execute();
} else {
    $cleanup_sql = "UPDATE attendance 
                    SET TotalPenalty = (
                        (CASE WHEN amLogin IS NULL THEN 50 ELSE 0 END) +
                        (CASE WHEN amLogout IS NULL THEN 50 ELSE 0 END) +
                        (CASE WHEN pmLogin IS NULL THEN 50 ELSE 0 END) +
                        (CASE WHEN pmLogout IS NULL THEN 50 ELSE 0 END)
                    )
                    WHERE event_name = ? AND event_date = ?";
    $cleanup_stmt = $conn->prepare($cleanup_sql);
    $cleanup_stmt->bind_param("ss", $event_name, $event_date);
    $cleanup_stmt->execute();
}

// Get registered students
$students_sql = "SELECT DISTINCT sp.students_id, sp.FirstName, sp.LastName, sp.Course, sp.YearLevel, sp.Section 
                 FROM student_profile sp
                 INNER JOIN registration r ON sp.students_id = r.students_id";

$where_added = false;
if ($event['YearLevel'] !== 'AllLevels') {
    $year_mapping = [
        '1stYearLevel' => '1stYear',
        '2ndYearLevel' => '2ndYear',
        '3rdYearLevel' => '3rdYear',
        '4thYearLevel' => '4thYear'
    ];
    
    $target_year = $year_mapping[$event['YearLevel']] ?? '';
    if ($target_year) {
        $students_sql .= " WHERE sp.YearLevel = '" . $conn->real_escape_string($target_year) . "'";
        $where_added = true;
    }
}

$students_sql .= " ORDER BY sp.LastName, sp.FirstName";
$students_result = $conn->query($students_sql);

// Get existing attendance records
$attendance_sql = "SELECT * FROM attendance WHERE event_name = ? AND event_date = ?";
$stmt = $conn->prepare($attendance_sql);
$stmt->bind_param("ss", $event_name, $event_date);
$stmt->execute();
$attendance_result = $stmt->get_result();

$existing_attendance = [];
while ($row = $attendance_result->fetch_assoc()) {
    $existing_attendance[$row['students_id']] = $row;
}

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
<title>Manage Attendance - <?= htmlspecialchars($event_name) ?> | CSSO</title>
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
  border-left: 4px solid #f59e0b;
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

.event-detail .badge {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  margin-left: 4px;
}

.badge-time {
  background: #fef3c7;
  color: #92400e;
}

.badge-year {
  background: #e0e7ff;
  color: #3730a3;
}

.message {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 20px;
  font-weight: 500;
}

.message.success {
  background: #d1fae5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.message.error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.attendance-form {
  background: white;
  border-radius: 12px;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
  overflow: hidden;
}

.form-header {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
  padding: 20px;
}

.form-header h3 {
  font-size: 18px;
  font-weight: 600;
}

.form-header p {
  font-size: 13px;
  opacity: 0.9;
  margin-top: 4px;
}

.form-actions {
  padding: 20px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  display: flex;
  gap: 12px;
  justify-content: flex-end;
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
}

.save-btn {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.save-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.cancel-btn {
  background: #64748b;
  color: white;
}

.cancel-btn:hover {
  background: #475569;
  transform: translateY(-2px);
}

.table-container {
  max-height: 600px;
  overflow-y: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

thead {
  background: #f8fafc;
  position: sticky;
  top: 0;
  z-index: 10;
}

th {
  padding: 16px 14px;
  text-align: left;
  color: #374151;
  font-weight: 600;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid #e2e8f0;
  background: #f8fafc;
}

td {
  padding: 12px 14px;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
  font-size: 14px;
}

tbody tr:hover {
  background: #f0f9ff;
}

.time-input {
  width: 100%;
  padding: 8px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 13px;
  text-align: center;
}

.time-input:focus {
  outline: none;
  border-color: #0ea5e9;
  box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.1);
}

.badge-course {
  background: #dbeafe;
  color: #1e40af;
  padding: 4px 10px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 12px;
  display: inline-block;
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
  .table-container {
    overflow-x: auto;
  }
  
  table {
    min-width: 800px;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
</head>
<body>
<div class="container">
    <a href="view_attendance.php?event=<?= urlencode($event_name) ?>&date=<?= urlencode($event_date) ?>" class="back-btn">
        <i class="fa fa-arrow-left"></i> Back to View Attendance
    </a>

    <!-- Event Header -->
    <div class="event-header">
        <h1 class="event-title">Manage Attendance - <?= htmlspecialchars($event_name) ?></h1>
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

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="attendance-form">
            <div class="form-header">
                <h3><i class="fa fa-edit"></i> Student Attendance Records</h3>
                <p>Found <?= $students_result->num_rows ?> registered students</p>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Course/Year</th>
                            <?php if ($show_am): ?>
                                <th>AM Login</th>
                                <th>AM Logout</th>
                            <?php endif; ?>
                            <?php if ($show_pm): ?>
                                <th>PM Login</th>
                                <th>PM Logout</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($students_result->num_rows > 0): ?>
                        <?php while($student = $students_result->fetch_assoc()): 
                            $attendance = $existing_attendance[$student['students_id']] ?? [];
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($student['students_id']) ?></strong></td>
                            <td><?= htmlspecialchars($student['LastName'] . ', ' . $student['FirstName']) ?></td>
                            <td>
                                <span class="badge badge-course"><?= htmlspecialchars($student['Course']) ?></span>
                                <span class="badge badge-course"><?= htmlspecialchars($student['YearLevel']) ?></span>
                            </td>
                            <?php if ($show_am): ?>
                                <td>
                                    <input type="time" 
                                           name="attendance_data[<?= $student['students_id'] ?>][amLogin]" 
                                           value="<?= htmlspecialchars($attendance['amLogin'] ?? '') ?>" 
                                           class="time-input">
                                </td>
                                <td>
                                    <input type="time" 
                                           name="attendance_data[<?= $student['students_id'] ?>][amLogout]" 
                                           value="<?= htmlspecialchars($attendance['amLogout'] ?? '') ?>" 
                                           class="time-input">
                                </td>
                            <?php endif; ?>
                            <?php if ($show_pm): ?>
                                <td>
                                    <input type="time" 
                                           name="attendance_data[<?= $student['students_id'] ?>][pmLogin]" 
                                           value="<?= htmlspecialchars($attendance['pmLogin'] ?? '') ?>" 
                                           class="time-input">
                                </td>
                                <td>
                                    <input type="time" 
                                           name="attendance_data[<?= $student['students_id'] ?>][pmLogout]" 
                                           value="<?= htmlspecialchars($attendance['pmLogout'] ?? '') ?>" 
                                           class="time-input">
                                </td>
                            <?php endif; ?>
                            <td>
                                <button type="button" class="btn" style="padding: 6px 12px; font-size: 12px; background: #ef4444; color: white;" onclick="clearRow(this)">
                                    <i class="fa fa-times"></i> Clear
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= ($show_am && $show_pm) ? 8 : 6 ?>">
                                <div class="empty-state">
                                    <i class="fa fa-user-slash"></i>
                                    <p><strong>No registered students found</strong></p>
                                    <p style="font-size: 13px; margin-top: 8px;">
                                        <?php if ($event['YearLevel'] === 'AllLevels'): ?>
                                            There are no students registered in the system yet.
                                        <?php else: ?>
                                            No students from <?= formatYearLevel($event['YearLevel']) ?> are registered yet.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions">
                <a href="view_attendance.php?event=<?= urlencode($event_name) ?>&date=<?= urlencode($event_date) ?>" class="btn cancel-btn">
                    <i class="fa fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn save-btn">
                    <i class="fa fa-save"></i> Save All Changes
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function clearRow(button) {
    const row = button.closest('tr');
    const inputs = row.querySelectorAll('input[type="time"]');
    inputs.forEach(input => {
        input.value = '';
    });
}

// Show success message with SweetAlert
<?php if ($message_type === 'success'): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?= addslashes($message) ?>',
        timer: 3000,
        showConfirmButton: false
    }).then(() => {
        window.location.href = 'view_attendance.php?event=<?= urlencode($event_name) ?>&date=<?= urlencode($event_date) ?>';
    });
<?php elseif ($message_type === 'error'): ?>
    Swal.fire({
        icon: 'error',
        title: 'Warning!',
        text: '<?= addslashes($message) ?>'
    });
<?php endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>
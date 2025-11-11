<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "csso";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// =============== DELETE FUNCTION ===============
if (isset($_GET['delete_id'])) {
    $delete_id = $conn->real_escape_string($_GET['delete_id']);
    $conn->query("DELETE FROM registration WHERE registration_no = '$delete_id'");

    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        window.onload = function() {
            Swal.fire({
                title: 'Deleted!',
                text: 'Registration record deleted successfully!',
                icon: 'success',
                confirmButtonColor: '#0ea5e9',
                confirmButtonText: 'OK',
                position: 'center'
            }).then(() => {
                window.location = 'reglist.php';
            });
        };
    </script>";
}

$course_filter = isset($_GET['course']) ? $_GET['course'] : 'BSIT';
$semester_filter = isset($_GET['semester']) ? $_GET['semester'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$courseTitle = ($course_filter === 'BSCS') 
  ? 'Bachelor of Science in Computer Science' 
  : 'Bachelor of Science in Information Technology';

$sql = "SELECT r.registration_no, r.students_id, r.registration_date, r.semester, 
               r.amount, r.payment_type, r.payment_status,
               s.FirstName, s.LastName, s.MI, s.Suffix, s.Course
        FROM registration r
        LEFT JOIN student_profile s ON r.students_id = s.students_id
        WHERE s.Course LIKE '%$course_filter%'";

if (!empty($semester_filter)) {
  $sql .= " AND r.semester = '$semester_filter'";
}

if (!empty($search)) {
  $sql .= " AND (s.FirstName LIKE '%$search%' 
              OR s.LastName LIKE '%$search%' 
              OR r.students_id LIKE '%$search%' 
              OR r.registration_no LIKE '%$search%')";
}

$sql .= " ORDER BY r.registration_date DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registration List | CSSO</title>
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
  min-width: 220px;
}

.filters select {
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23334155' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 36px;
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
  text-decoration: none;
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

/* Course Badge */
.course-badge {
  background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
  padding: 12px 20px;
  border-radius: 10px;
  text-align: center;
  margin-bottom: 16px;
  border-left: 4px solid #0ea5e9;
}

.course-badge h3 {
  font-size: 17px;
  color: #0c4a6e;
  font-weight: 600;
  margin: 0;
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
  white-space: nowrap;
}

th.center {
  text-align: center;
}

td {
  padding: 14px;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
  font-size: 14px;
}

td.center {
  text-align: center;
}

tbody tr {
  transition: all 0.2s ease;
}

tbody tr:hover {
  background: #f0f9ff;
  transform: scale(1.005);
}

tbody tr:last-child td {
  border-bottom: none;
}

/* Status Badges */
.status-badge {
  padding: 6px 12px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 12px;
  display: inline-block;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.status-paid {
  background: #d1fae5;
  color: #065f46;
}

.status-unpaid {
  background: #fee2e2;
  color: #991b1b;
}

.status-partialpaid {
  background: #fef3c7;
  color: #92400e;
}

/* Payment Type Badge */
.payment-badge {
  padding: 4px 10px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 12px;
  background: #dbeafe;
  color: #1e40af;
  display: inline-block;
}

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.action-btn {
  padding: 8px 12px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.view-btn {
  background: #fbbf24;
  color: #78350f;
}

.view-btn:hover {
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
  padding: 60px 20px;
  color: #64748b;
}

.empty-state i {
  font-size: 64px;
  color: #cbd5e1;
  margin-bottom: 16px;
}

.empty-state h4 {
  font-size: 18px;
  color: #475569;
  margin-bottom: 8px;
}

.empty-state p {
  font-size: 14px;
  color: #94a3b8;
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
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.stat-card h4 {
  font-size: 13px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
  font-weight: 600;
}

.stat-card .stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #0ea5e9;
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
  
  .filters select,
  .filters input[type="text"] {
    width: 100%;
  }
  
  .table-container {
    overflow-x: auto;
  }
  
  table {
    min-width: 900px;
  }
  
  .stats-container {
    grid-template-columns: 1fr;
  }
}

/* Print Styles */
@media print {
  .controls,
  .page-header,
  .action-buttons {
    display: none;
  }
  
  body {
    background: white;
  }
  
  .table-container {
    box-shadow: none;
  }
}
</style>
</head>
<body>

<div class="container">
  <!-- Header -->
  <div class="page-header">
    <i class="fa-solid fa-clipboard-list"></i>
    <h2>Registration List</h2>
  </div>

  <!-- Statistics Cards -->
  <?php
  // Calculate statistics
  $totalQuery = "SELECT COUNT(*) as total, SUM(amount) as total_amount FROM registration r 
                 LEFT JOIN student_profile s ON r.students_id = s.students_id 
                 WHERE s.Course LIKE '%$course_filter%'";
  if (!empty($semester_filter)) {
    $totalQuery .= " AND r.semester = '$semester_filter'";
  }
  $statsResult = $conn->query($totalQuery);
  $stats = $statsResult->fetch_assoc();
  
  $paidQuery = "SELECT COUNT(*) as paid_count FROM registration r 
                LEFT JOIN student_profile s ON r.students_id = s.students_id 
                WHERE s.Course LIKE '%$course_filter%' AND r.payment_status = 'Paid'";
  if (!empty($semester_filter)) {
    $paidQuery .= " AND r.semester = '$semester_filter'";
  }
  $paidResult = $conn->query($paidQuery);
  $paidCount = $paidResult->fetch_assoc()['paid_count'];
  ?>
  
  <div class="stats-container">
    <div class="stat-card">
      <h4>Total Registrations</h4>
      <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
    </div>
    <div class="stat-card" style="border-left-color: #10b981;">
      <h4>Paid</h4>
      <div class="stat-value" style="color: #10b981;"><?php echo $paidCount; ?></div>
    </div>
    <div class="stat-card" style="border-left-color: #f59e0b;">
      <h4>Total Revenue</h4>
      <div class="stat-value" style="color: #f59e0b;">₱<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></div>
    </div>
  </div>

  <!-- Controls -->
  <div class="controls">
    <form method="GET" class="filters">
      <select name="course" onchange="this.form.submit()">
        <option value="BSIT" <?= $course_filter === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
        <option value="BSCS" <?= $course_filter === 'BSCS' ? 'selected' : '' ?>>BSCS</option>
      </select>

      <select name="semester" onchange="this.form.submit()">
        <option value="">All Semesters</option>
        <option value="First Semester" <?= $semester_filter === 'First Semester' ? 'selected' : '' ?>>First Semester</option>
        <option value="Second Semester" <?= $semester_filter === 'Second Semester' ? 'selected' : '' ?>>Second Semester</option>
      </select>

      <input type="text" name="search" placeholder="Search registration..." value="<?= htmlspecialchars($search) ?>">
      
      <a href="reglist.php" class="btn clear-btn">
        <i class="fa fa-rotate"></i> Clear
      </a>
    </form>

    <button class="btn add-btn" onclick="window.location.href='registration.php'">
      <i class="fa fa-plus"></i> New Registration
    </button>
  </div>

  <!-- Course Badge -->
  <div class="course-badge">
    <h3><?= $courseTitle ?></h3>
  </div>

  <!-- Table -->
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>Reg. No</th>
          <th>Student ID</th>
          <th>Student Name</th>
          <th>Date</th>
          <th>Semester</th>
          <th class="center">Amount</th>
          <th class="center">Payment Type</th>
          <th class="center">Status</th>
          <th class="center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($result && $result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
            $fullname = trim($row['LastName'] . ', ' . $row['FirstName'] . ' ' . ($row['MI'] ? $row['MI'] . '.' : '') . ' ' . $row['Suffix']);
            $statusClass = strtolower(str_replace(' ', '', $row['payment_status']));
            
            echo "<tr>
                    <td><strong style='color: #0ea5e9;'>{$row['registration_no']}</strong></td>
                    <td>{$row['students_id']}</td>
                    <td><strong>{$fullname}</strong></td>
                    <td>" . date('M d, Y', strtotime($row['registration_date'])) . "</td>
                    <td>{$row['semester']}</td>
                    <td class='center'><strong>₱" . number_format($row['amount'], 2) . "</strong></td>
                    <td class='center'><span class='payment-badge'>{$row['payment_type']}</span></td>
                    <td class='center'><span class='status-badge status-{$statusClass}'>{$row['payment_status']}</span></td>
                    <td>
                      <div class='action-buttons'>
                        <button class='action-btn delete-btn' onclick=\"confirmDelete('{$row['registration_no']}')\" title='Delete'>
                          <i class='fa fa-trash'></i>
                        </button>
                      </div>
                    </td>
                  </tr>";
          }
        } else {
          echo "<tr>
                  <td colspan='9'>
                    <div class='empty-state'>
                      <i class='fa fa-inbox'></i>
                      <h4>No Registration Records Found</h4>
                      <p>Try adjusting your filters or add a new registration.</p>
                    </div>
                  </td>
                </tr>";
        }
        ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function confirmDelete(regNo) {
  Swal.fire({
    title: 'Delete Registration?',
    text: "This will permanently delete the registration record.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel',
    reverseButtons: true
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = 'reglist.php?delete_id=' + regNo;
    }
  });
}
</script>

</body>
</html>
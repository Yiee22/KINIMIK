<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['usertype'], ['Governor', 'Vice Governor'])) {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "csso");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ DELETE FUNCTION (with related tables)
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $conn->begin_transaction();

    try {
        // Delete related records first (respect foreign key relationship)
        $conn->query("DELETE FROM family_background WHERE students_id = $delete_id");
        $conn->query("DELETE FROM educational_background WHERE students_id = $delete_id");
        $conn->query("DELETE FROM student_profile WHERE students_id = $delete_id");

        $conn->commit();

        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            window.onload = function() {
                Swal.fire({
                    title: 'Deleted!',
                    text: 'Student record deleted successfully!',
                    icon: 'success',
                    confirmButtonColor: '#0ea5e9',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location = 'students.php';
                });
            };
        </script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            Swal.fire({
                title: 'Error!',
                text: 'Failed to delete student: " . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
        </script>";
    }
}

$course = $_GET['course'] ?? 'BSIT';
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

$sql = "SELECT * FROM student_profile WHERE Course = '$course'";
if (!empty($search)) {
    $sql .= " AND (students_id LIKE '%$search%' 
              OR LastName LIKE '%$search%' 
              OR FirstName LIKE '%$search%' 
              OR Section LIKE '%$search%')";
}
if (!empty($filter)) {
    $sql .= " AND YearLevel = '$filter'";
}
$sql .= " ORDER BY students_id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Students | CSSO</title>
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
  padding: 40px 20px;
  color: #64748b;
  font-size: 15px;
}

.empty-state i {
  font-size: 48px;
  color: #cbd5e1;
  margin-bottom: 12px;
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
    min-width: 800px;
  }
}
</style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="page-header">
        <i class="fa-solid fa-users"></i>
        <h2>Students Management</h2>
    </div>

    <!-- Controls -->
    <div class="controls">
        <form method="get" class="filters">
            <select name="course" onchange="this.form.submit()">
                <option value="BSIT" <?= $course==='BSIT'?'selected':'' ?>>BSIT</option>
                <option value="BSCS" <?= $course==='BSCS'?'selected':'' ?>>BSCS</option>
            </select>

            <select name="filter" onchange="this.form.submit()">
                <option value="">All Year Levels</option>
                <option value="1stYear" <?= $filter==='1stYear'?'selected':'' ?>>1st Year</option>
                <option value="2ndYear" <?= $filter==='2ndYear'?'selected':'' ?>>2nd Year</option>
                <option value="3rdYear" <?= $filter==='3rdYear'?'selected':'' ?>>3rd Year</option>
                <option value="4thYear" <?= $filter==='4thYear'?'selected':'' ?>>4th Year</option>
            </select>

            <input type="text" name="search" placeholder="Search student..." value="<?= htmlspecialchars($search) ?>">
            
            <button type="submit" class="btn search-btn">
                <i class="fa fa-search"></i> Search
            </button>
            
            <button type="button" class="btn clear-btn" onclick="window.location='students.php'">
                <i class="fa fa-rotate"></i> Clear
            </button>
        </form>

        <button class="btn add-btn" onclick="window.location.href='addstudents.php'">
            <i class="fa fa-plus"></i> Add Student
        </button>
    </div>

    <!-- Course Badge -->
    <div class="course-badge">
        <h3>
            <?= $course === 'BSIT' ? 'Bachelor of Science in Information Technology' : 'Bachelor of Science in Computer Science' ?>
        </h3>
    </div>

    <!-- Table -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Last Name</th>
                    <th>First Name</th>
                    <th>MI</th>
                    <th>Course</th>
                    <th>Section</th>
                    <th>Year Level</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['students_id']) ?></strong></td>
                    <td><?= htmlspecialchars($row['LastName']) ?></td>
                    <td><?= htmlspecialchars($row['FirstName']) ?></td>
                    <td><?= htmlspecialchars($row['MI']) ?></td>
                    <td><span style="background:#dbeafe;padding:4px 10px;border-radius:6px;font-weight:600;color:#1e40af;"><?= htmlspecialchars($row['Course']) ?></span></td>
                    <td><?= htmlspecialchars($row['Section']) ?></td>
                    <td><?= htmlspecialchars($row['YearLevel']) ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn view-btn" onclick="viewStudent(<?= $row['students_id'] ?>)" title="Edit">
                                <i class="fa fa-pen"></i>
                            </button>
                            <button class="action-btn delete-btn" onclick="confirmDelete(<?= $row['students_id'] ?>)" title="Delete">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fa fa-inbox"></i>
                            <p>No students found.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function confirmDelete(id) {
    Swal.fire({
        title: 'Delete Student?',
        text: "This will permanently delete the student and all related data.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'students.php?delete_id=' + id;
        }
    });
}

function viewStudent(id) {
    window.location.href = 'viewstudents.php?students_id=' + id;
}
</script>
</body>
</html>


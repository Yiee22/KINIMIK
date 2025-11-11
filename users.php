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

// ✅ Add status column dynamically if it doesn't exist
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS status TINYINT(1) DEFAULT 0");

// ===================== ADD USER FUNCTION =====================
if (isset($_POST['add_user'])) {
    $first_name = $conn->real_escape_string(trim($_POST['first_name']));
    $last_name = $conn->real_escape_string(trim($_POST['last_name']));
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $usertype = $conn->real_escape_string($_POST['usertype']);
    
    // Set default status (Governor always ON, others OFF)
    $status = (strtolower($usertype) == 'governor') ? 1 : 0;
    
    // Check if username already exists
    $check = $conn->query("SELECT username FROM users WHERE username = '$username'");
    if ($check->num_rows > 0) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            window.onload = function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Username already exists!',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            };
        </script>";
    } else {
        $sql = "INSERT INTO users (first_name, last_name, username, password, usertype, status, date_in, time_in) 
                VALUES ('$first_name', '$last_name', '$username', '$password', '$usertype', $status, CURDATE(), CURTIME())";
        
        if ($conn->query($sql)) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                window.onload = function() {
                    Swal.fire({
                        title: 'Success!',
                        text: 'User added successfully!',
                        icon: 'success',
                        confirmButtonColor: '#0ea5e9'
                    }).then(() => {
                        window.location = 'users.php';
                    });
                };
            </script>";
        } else {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                window.onload = function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to add user!',
                        icon: 'error',
                        confirmButtonColor: '#ef4444'
                    });
                };
            </script>";
        }
    }
}

// ===================== DELETE FUNCTION =====================
if (isset($_GET['delete_id'])) {
    $delete_id = $conn->real_escape_string($_GET['delete_id']);
    $conn->query("DELETE FROM users WHERE user_id = '$delete_id'");

    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        window.onload = function() {
            Swal.fire({
                title: 'Deleted!',
                text: 'User record deleted successfully!',
                icon: 'success',
                confirmButtonColor: '#0ea5e9',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location = 'users.php';
            });
        };
    </script>";
    exit;
}

// ===================== STATUS TOGGLE FUNCTION =====================
if (isset($_GET['toggle_id'])) {
    $toggle_id = $conn->real_escape_string($_GET['toggle_id']);

    // ✅ Get user info before toggle
    $getUser = $conn->query("SELECT usertype, status FROM users WHERE user_id = '$toggle_id'");
    if ($getUser->num_rows > 0) {
        $user = $getUser->fetch_assoc();
        $usertype = strtolower($user['usertype']);

        // Governor is always ON (cannot toggle)
        if ($usertype == 'governor') {
            $conn->query("UPDATE users SET status = 1 WHERE user_id = '$toggle_id'");
        } else {
            // Toggle ON/OFF
            $conn->query("UPDATE users SET status = IF(status = 1, 0, 1) WHERE user_id = '$toggle_id'");
        }
    }

    header("Location: users.php");
    exit;
}

// ✅ Force Governor always ON every load
$conn->query("UPDATE users SET status = 1 WHERE LOWER(usertype) = 'governor'");

// ===================== FETCH DATA =====================
$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT * FROM users WHERE 
        first_name LIKE '%$search%' 
        OR last_name LIKE '%$search%' 
        OR username LIKE '%$search%' 
        ORDER BY user_id DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users Management | CSSO</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

.search-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-bar input {
  padding: 10px 14px;
  border-radius: 8px;
  border: 2px solid #e2e8f0;
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  background: white;
  color: #334155;
  font-weight: 500;
  min-width: 250px;
}

.search-bar input:focus {
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
  text-decoration: none;
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
  text-align: center;
  color: white;
  font-weight: 600;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  vertical-align: middle;
}

td {
  padding: 14px;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
  font-size: 14px;
  text-align: center;
  vertical-align: middle;
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

/* Status Toggle Button */
.toggle-btn {
  padding: 6px 16px;
  border: none;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.toggle-on {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
}

.toggle-on:hover:not(:disabled) {
  transform: scale(1.05);
  box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
}

.toggle-off {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

.toggle-off:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
}

.toggle-btn:disabled {
  opacity: 0.8;
  cursor: not-allowed;
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
}

.view-btn {
  background: #0ea5e9;
  color: white;
}

.view-btn:hover {
  background: #0284c7;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(14, 165, 233, 0.3);
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

/* User Type Badge */
.usertype-badge {
  display: inline-block;
  padding: 5px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.badge-governor {
  background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
  color: white;
}

.badge-vice {
  background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
  color: white;
}

.badge-default {
  background: #dbeafe;
  color: #1e40af;
}

/* Modal Styling */
.modal-header {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  border: none;
}

.modal-content {
  border-radius: 12px;
  border: none;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
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
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
  outline: none;
}

.modal-footer {
  border-top: 2px solid #f1f5f9;
  padding: 16px 24px;
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
  display: block;
}

/* Responsive */
@media (max-width: 768px) {
  .controls {
    flex-direction: column;
    align-items: stretch;
  }
  
  .search-bar {
    flex-direction: column;
    width: 100%;
  }
  
  .search-bar input {
    width: 100%;
  }
  
  .table-container {
    overflow-x: auto;
  }
  
  table {
    min-width: 1000px;
  }
}
</style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="page-header">
        <i class="fa-solid fa-user-gear"></i>
        <h2>Users Management</h2>
    </div>

    <!-- Controls -->
    <div class="controls">
        <form method="GET" class="search-bar">
            <input type="text" name="search" placeholder="Search user..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn search-btn">
                <i class="fa fa-search"></i> Search
            </button>
            <a href="users.php" class="btn clear-btn">
                <i class="fa fa-rotate"></i> Clear
            </a>
        </form>

        <button class="btn add-btn" onclick="showAddUserModal()">
            <i class="fa fa-plus"></i> Add User
        </button>
    </div>

    <!-- Table -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Username</th>
                    <th>User Type</th>
                    <th>Date In</th>
                    <th>Time In</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $usertype = strtolower($row['usertype']);
                    $status = isset($row['status']) ? (int)$row['status'] : 0;

                    // Determine usertype badge class
                    if ($usertype == 'governor') {
                        $badge_class = 'badge-governor';
                        $status = 1;
                        $status_label = "ON";
                        $status_class = "toggle-on";
                        $toggle_button = "<button class='toggle-btn {$status_class}' disabled>{$status_label}</button>";
                    } elseif ($usertype == 'vice governor') {
                        $badge_class = 'badge-vice';
                        $status_label = $status ? "ON" : "OFF";
                        $status_class = $status ? "toggle-on" : "toggle-off";
                        $toggle_button = "<button class='toggle-btn {$status_class}' 
                                            onclick=\"window.location.href='users.php?toggle_id={$row['user_id']}'\">
                                            {$status_label}
                                          </button>";
                    } else {
                        $badge_class = 'badge-default';
                        $status_label = $status ? "ON" : "OFF";
                        $status_class = $status ? "toggle-on" : "toggle-off";
                        $toggle_button = "<button class='toggle-btn {$status_class}' 
                                            onclick=\"window.location.href='users.php?toggle_id={$row['user_id']}'\">
                                            {$status_label}
                                          </button>";
                    }

                    echo "<tr>
                            <td>{$row['first_name']}</td>
                            <td>{$row['last_name']}</td>
                            <td>{$row['username']}</td>
                            <td><span class='usertype-badge {$badge_class}'>{$row['usertype']}</span></td>
                            <td>{$row['date_in']}</td>
                            <td>{$row['time_in']}</td>
                            <td>{$toggle_button}</td>
                            <td>
                                <div class='action-buttons'>
                                    <button class='action-btn view-btn' onclick='viewUser(" . json_encode($row) . ")' title='View Details'>
                                        <i class='fa fa-eye'></i>
                                    </button>
                                    <button class='action-btn delete-btn' onclick=\"confirmDelete('{$row['user_id']}')\" title='Delete'>
                                        <i class='fa fa-trash'></i>
                                    </button>
                                </div>
                            </td>
                          </tr>";
                }
            } else {
                echo "<tr>
                        <td colspan='8'>
                            <div class='empty-state'>
                                <i class='fa fa-users-slash'></i>
                                <p>No users found.</p>
                            </div>
                        </td>
                      </tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<!-- VIEW MODAL -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-user-circle me-2"></i>User Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modalBody"></div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ADD USER MODAL -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-user-plus me-2"></i>Add New User</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label"><i class="fa fa-user me-1"></i> First Name</label>
            <input type="text" class="form-control" name="first_name" required placeholder="Enter first name">
          </div>
          
          <div class="mb-3">
            <label class="form-label"><i class="fa fa-user me-1"></i> Last Name</label>
            <input type="text" class="form-control" name="last_name" required placeholder="Enter last name">
          </div>
          
          <div class="mb-3">
            <label class="form-label"><i class="fa fa-at me-1"></i> Username</label>
            <input type="text" class="form-control" name="username" required placeholder="Enter username">
          </div>
          
          <div class="mb-3">
            <label class="form-label"><i class="fa fa-lock me-1"></i> Password</label>
            <input type="password" class="form-control" name="password" required minlength="8" placeholder="Min. 8 characters">
            <small class="text-muted">Password must be at least 8 characters</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label"><i class="fa fa-id-badge me-1"></i> User Type</label>
            <select class="form-select" name="usertype" required>
              <option value="">Select User Type</option>
              <option value="Governor">Governor</option>
              <option value="Vice Governor">Vice Governor</option>
              <option value="Secretary">Secretary</option>
              <option value="Auditor">Auditor</option>
              <option value="Treasurer">Treasurer</option>
              <option value="Social Manager">Social Manager</option>
              <option value="Senator">Senator</option>
            </select>
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fa fa-times me-1"></i> Cancel
          </button>
          <button type="submit" name="add_user" class="btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none;">
            <i class="fa fa-save me-1"></i> Save User
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showAddUserModal() {
    new bootstrap.Modal(document.getElementById('addUserModal')).show();
}

function confirmDelete(userId) {
  Swal.fire({
      title: 'Delete User?',
      text: "This user will be permanently deleted.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Yes, delete',
      cancelButtonText: 'Cancel',
      reverseButtons: true
  }).then((result) => {
      if (result.isConfirmed) {
          window.location.href = 'users.php?delete_id=' + userId;
      }
  });
}

function viewUser(user) {
  let html = `
    <div style="padding: 10px;">
        <p style="margin-bottom: 12px;"><strong style="color: #0ea5e9;"><i class="fa fa-user me-2"></i>First Name:</strong> ${user.first_name}</p>
        <p style="margin-bottom: 12px;"><strong style="color: #0ea5e9;"><i class="fa fa-user me-2"></i>Last Name:</strong> ${user.last_name}</p>
        <p style="margin-bottom: 12px;"><strong style="color: #0ea5e9;"><i class="fa fa-at me-2"></i>Username:</strong> ${user.username}</p>
        <p style="margin-bottom: 12px;"><strong style="color: #0ea5e9;"><i class="fa fa-id-badge me-2"></i>User Type:</strong> ${user.usertype}</p>
        <p style="margin-bottom: 12px;"><strong style="color: #0ea5e9;"><i class="fa fa-calendar me-2"></i>Date In:</strong> ${user.date_in}</p>
        <p style="margin-bottom: 12px;"><strong style="color: #0ea5e9;"><i class="fa fa-clock me-2"></i>Time In:</strong> ${user.time_in}</p>
    </div>
  `;
  document.getElementById('modalBody').innerHTML = html;
  new bootstrap.Modal(document.getElementById('viewModal')).show();
}
</script>

</body>
</html>
<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['usertype'], ['Governor','Vice Governor'])) {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard | CSSO</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body { font-family: 'Segoe UI', sans-serif; margin:0; display:flex; background:#f7f9fc; }
.sidebar { width:250px; background:#fff; border-right:1px solid #e2e8f0; height:100vh; padding-top:20px; position:fixed; }
.sidebar ul { list-style:none; padding:0; }
.sidebar li { padding:15px 20px; cursor:pointer; display:flex; align-items:center; gap:10px; }
.sidebar li:hover { background:#e2e8f0; }
.main { margin-left:250px; padding:20px; flex:1; }
</style>
</head>
<body>

<div class="sidebar">
  <ul>
    <li onclick="loadPage('dashboard.php')"><i class="fa fa-gauge"></i> Dashboard</li>
    <li onclick="loadPage('students.php')"><i class="fa fa-users"></i> Students</li>
    <li onclick="loadPage('registration.php')"><i class="fa fa-file-invoice-dollar"></i> Registration</li>
    <li onclick="logout()"><i class="fa fa-sign-out-alt"></i> Logout</li>
  </ul>
</div>

<div class="main" id="content">
  <!-- Default page: Dashboard -->
  <?php include 'dashboard.php'; ?>
</div>

<script>
function loadPage(page){
    fetch(page)
    .then(response => response.text())
    .then(data => document.getElementById('content').innerHTML = data)
    .catch(err => console.error(err));
}

function logout(){
    window.location.href='../logout.php';
}
</script>
</body>
</html>

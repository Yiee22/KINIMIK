<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Check if logged in and allowed user types
if (!isset($_SESSION['username']) || !in_array($_SESSION['usertype'], ['Secretary', 'Treasurer', 'Auditor', 'Social Manager', 'Senator', 'Governor', 'Vice Governor'])) {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Dashboard | CSSO</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

body {
  background: #f0f4f8;
  color: #1e3a5f;
  height: 100vh;
  display: flex;
  overflow: hidden;
}

/* ===== SIDEBAR ===== */
.sidebar {
  width: 260px;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 3px 0 15px rgba(0, 0, 0, 0.06);
  overflow: hidden;
  position: relative;
}

.sidebar.collapsed {
  width: 75px;
}

/* Sidebar Header */
.sidebar-header {
  padding: 22px 20px;
  text-align: center;
  background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
  position: relative;
  overflow: hidden;
}

.sidebar-header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, rgba(37, 99, 235, 0.2) 0%, rgba(30, 64, 175, 0.2) 100%);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.sidebar-header:hover::before {
  opacity: 1;
}

.sidebar-header img {
  width: 65px;
  height: 65px;
  border-radius: 50%;
  border: 3px solid rgba(255, 255, 255, 0.95);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
  transition: all 0.3s ease;
  background: #fff;
  padding: 5px;
}

.sidebar.collapsed .sidebar-header img {
  width: 48px;
  height: 48px;
  border-width: 2px;
}

/* Menu */
.menu {
  list-style: none;
  padding: 12px 14px;
  margin: 0;
  flex: 1;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.menu li {
  padding: 11px 18px;
  display: flex;
  align-items: center;
  gap: 14px;
  cursor: pointer;
  border-radius: 10px;
  margin-bottom: 3px;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  color: #475569;
  font-size: 15px;
  font-weight: 500;
  position: relative;
}

.menu li:hover {
  background: #e0f2fe;
  color: #0284c7;
  transform: translateX(5px);
  box-shadow: 0 2px 8px rgba(2, 132, 199, 0.15);
}

.menu li.active {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.35);
  font-weight: 600;
}

.menu li.active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 4px;
  height: 70%;
  background: #ffffff;
  border-radius: 0 4px 4px 0;
}

.menu li i {
  font-size: 18px;
  min-width: 24px;
  text-align: center;
  transition: all 0.3s ease;
}

.menu li span {
  white-space: nowrap;
  transition: opacity 0.3s ease;
  font-weight: 500;
  font-size: 15px;
}

/* Collapsed Sidebar */
.sidebar.collapsed .menu li {
  justify-content: center;
  padding: 11px 0;
}

.sidebar.collapsed .menu li span {
  opacity: 0;
  width: 0;
  overflow: hidden;
}

.sidebar.collapsed .menu li:hover::after {
  content: attr(data-title);
  position: absolute;
  left: 82px;
  background: #1e293b;
  color: #ffffff;
  padding: 9px 15px;
  border-radius: 10px;
  font-size: 13px;
  white-space: nowrap;
  z-index: 1000;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  animation: tooltipFade 0.2s ease;
}

@keyframes tooltipFade {
  from {
    opacity: 0;
    transform: translateX(-5px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

/* ===== MAIN AREA ===== */
.main {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: #f0f4f8;
}

/* Topbar */
.topbar {
  background: #ffffff;
  padding: 16px 30px;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 10;
  border-bottom: 2px solid #e0f2fe;
  min-height: 70px;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 20px;
}

.toggle-btn {
  background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
  border: none;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  font-size: 18px;
  color: #0284c7;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(2, 132, 199, 0.15);
}

.toggle-btn:hover {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: #ffffff;
  transform: scale(1.08);
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
}

.toggle-btn:active {
  transform: scale(0.96);
}

.topbar h2 {
  font-weight: 600;
  color: #1e3a5f;
  font-size: 32px;
  letter-spacing: -0.2px;
  line-height: 1.3;
}

/* User Info */
.user-info {
  text-align: right;
  line-height: 1.6;
}

.user-info strong {
  font-size: 15px;
  color: #1e3a5f;
  font-weight: 600;
  display: block;
  margin-bottom: 3px;
}

.user-info .role {
  display: inline-block;
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: #ffffff;
  padding: 4px 14px;
  border-radius: 14px;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 4px;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  box-shadow: 0 2px 6px rgba(14, 165, 233, 0.3);
}

.user-info .datetime {
  font-size: 12.5px;
  color: #64748b;
  font-weight: 500;
}

/* Iframe Container */
.content-container {
  flex: 1;
  padding: 22px;
  overflow: auto;
  background: #f0f4f8;
}

iframe {
  width: 100%;
  height: 100%;
  border: none;
  border-radius: 14px;
  background: #ffffff;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    left: -260px;
    top: 0;
    bottom: 0;
    z-index: 1000;
  }
  
  .sidebar.active {
    left: 0;
  }
  
  .topbar h2 {
    font-size: 16px;
  }
  
  .user-info {
    display: none;
  }
}

/* Custom Scrollbar */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

::-webkit-scrollbar-track {
  background: #e2e8f0;
}

::-webkit-scrollbar-thumb {
  background: #94a3b8;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: #64748b;
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <img src="../images/cssologo.png" alt="CSSO Logo">
  </div>
  <ul class="menu">
    <li class="active" data-title="Dashboard" onclick="navigate('dashboard.php', this)">
      <i class="fa fa-gauge"></i><span>Dashboard</span>
    </li>
    <li data-title="Students" onclick="navigate('students.php', this)">
      <i class="fa fa-users"></i><span>Students</span>
    </li>
    <li data-title="Registration" onclick="navigate('registration.php', this)">
      <i class="fa fa-file-invoice-dollar"></i><span>Registration</span>
    </li>
    <li data-title="Events" onclick="navigate('events.php', this)">
      <i class="fa fa-calendar"></i><span>Events</span>
    </li>
    <li data-title="Attendance" onclick="navigate('attendance.php', this)">
      <i class="fa fa-clipboard-check"></i><span>Attendance</span>
    </li>
    <li data-title="Fines" onclick="navigate('fines.php', this)">
      <i class="fa fa-gavel"></i><span>Fines</span>
    </li>
    <li data-title="Payments" onclick="navigate('payments.php', this)">
      <i class="fa fa-wallet"></i><span>Payments</span>
    </li>
    <li data-title="Logout" onclick="logout()">
      <i class="fa fa-sign-out-alt"></i><span>Logout</span>
    </li>
  </ul>
</div>

<!-- Main Content -->
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <button id="toggleSidebar" class="toggle-btn">
        <i class="fa fa-bars"></i>
      </button>
      <h2>Computer Studies Student Organization</h2>
    </div>
    <div class="user-info">
      <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
      <span class="role"><?= htmlspecialchars($_SESSION['usertype']) ?></span>
      <div class="datetime" id="datetime"></div>
    </div>
  </div>

  <!-- Content Frame -->
  <div class="content-container">
    <iframe id="contentFrame" src="dashboard.php"></iframe>
  </div>
</div>

<script>
const sidebar = document.getElementById("sidebar");
const toggleBtn = document.getElementById("toggleSidebar");

// Toggle Sidebar
toggleBtn.addEventListener('click', () => {
  sidebar.classList.toggle("collapsed");
});

// Update DateTime
function updateDateTime() {
  const now = new Date();
  const options = { 
    weekday: 'short', 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  };
  const date = now.toLocaleDateString('en-US', options);
  const time = now.toLocaleTimeString('en-US', { 
    hour: '2-digit', 
    minute: '2-digit'
  });
  document.getElementById('datetime').textContent = `${date} • ${time}`;
}
setInterval(updateDateTime, 1000);
updateDateTime();

// Navigate
function navigate(page, element) {
  document.getElementById('contentFrame').src = page;
  document.querySelectorAll('.menu li').forEach(li => li.classList.remove('active'));
  element.classList.add('active');
}

// Logout with SweetAlert
function logout() {
  Swal.fire({
    title: 'Logout Confirmation',
    text: "Are you sure you want to log out?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#0ea5e9',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Yes, logout',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      Swal.fire({
        title: 'Logging out...',
        text: 'See you soon!',
        icon: 'success',
        showConfirmButton: false,
        timer: 1200
      });
      setTimeout(() => {
        window.location.href = '../login.php';
      }, 1200);
    }
  });
}

// Mobile Sidebar Toggle
if (window.innerWidth <= 768) {
  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
  });
}
</script>

</body>
</html>
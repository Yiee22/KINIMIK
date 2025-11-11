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

// ===== GET EVENT DATA FROM URL =====
$event_name = $_GET['event'] ?? '';
$event_date = $_GET['date'] ?? '';
$location = $_GET['location'] ?? '';

// ===== FETCH STUDENT INFO (AJAX) =====
if (isset($_GET['fetch_student'])) {
    $students_id = $_GET['fetch_student'];
    
    $query = "SELECT 
                sp.students_id,
                sp.FirstName, 
                sp.LastName, 
                sp.MI, 
                sp.Suffix,
                sp.Course,
                sp.Section,
                sp.YearLevel,
                CASE 
                    WHEN r.students_id IS NOT NULL THEN 'Member'
                    ELSE 'Not Registered'
                END as Status,
                r.registration_no,
                COALESCE((SELECT SUM(amount) FROM fines WHERE students_id = sp.students_id), 0) as current_fines
              FROM student_profile sp
              LEFT JOIN registration r ON sp.students_id = r.students_id
              WHERE sp.students_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $students_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $fullname = trim($row['FirstName'] . ' ' . ($row['MI'] ? $row['MI'] . '. ' : '') . $row['LastName'] . ' ' . ($row['Suffix'] ?? ''));
        
        $checkAtt = $conn->prepare("SELECT amLogin, amLogout, pmLogin, pmLogout FROM attendance WHERE students_id = ? AND event_name = ? AND event_date = ?");
        $event_name_param = $_GET['event_name'] ?? $event_name;
        $checkAtt->bind_param("sss", $students_id, $event_name_param, $event_date);
        $checkAtt->execute();
        $attResult = $checkAtt->get_result();
        
        $attendance = null;
        if ($attResult->num_rows > 0) {
            $attendance = $attResult->fetch_assoc();
        }
        
        echo json_encode([
            "success" => true,
            "fullname" => $fullname,
            "course" => $row['Course'],
            "section" => $row['Section'],
            "yearLevel" => $row['YearLevel'],
            "status" => $row['Status'],
            "registration_no" => $row['registration_no'] ?? 'N/A',
            "current_fines" => number_format($row['current_fines'], 2),
            "attendance" => $attendance
        ]);
    } else {
        echo json_encode(["success" => false]);
    }
    exit;
}

// ===== SAVE ATTENDANCE =====
if (isset($_POST['save_attendance'])) {
    $students_id = $_POST['students_id'];
    $event_name = $_POST['event_name'];
    $event_date = $_POST['event_date'];
    $location = $_POST['location'];
    $checkpoint = $_POST['checkpoint'];
    $registration_no = $_POST['registration_no'];
    $current_time = date("H:i:s");
    
    $conn->begin_transaction();
    
    try {
        $check = $conn->prepare("SELECT attendance_id, amLogin, amLogout, pmLogin, pmLogout FROM attendance WHERE students_id = ? AND event_name = ? AND event_date = ?");
        $check->bind_param("sss", $students_id, $event_name, $event_date);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            if (!empty($row[$checkpoint])) {
                throw new Exception("This checkpoint has already been logged!");
            }
            
            $stmt = $conn->prepare("UPDATE attendance SET $checkpoint = ? WHERE attendance_id = ?");
            $stmt->bind_param("si", $current_time, $row['attendance_id']);
            $stmt->execute();
            
        } else {
            $stmt = $conn->prepare("INSERT INTO attendance (students_id, registration_no, event_name, event_date, location, $checkpoint) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $students_id, $registration_no, $event_name, $event_date, $location, $current_time);
            $stmt->execute();
        }
        
        $getAtt = $conn->prepare("SELECT amLogin, amLogout, pmLogin, pmLogout FROM attendance WHERE students_id = ? AND event_name = ? AND event_date = ?");
        $getAtt->bind_param("sss", $students_id, $event_name, $event_date);
        $getAtt->execute();
        $attData = $getAtt->get_result()->fetch_assoc();
        
        $conn->commit();
        
        $getFines = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM fines WHERE students_id = ?");
        $getFines->bind_param("s", $students_id);
        $getFines->execute();
        $finesData = $getFines->get_result()->fetch_assoc();
        
        echo json_encode([
            "success" => true, 
            "message" => "Attendance recorded successfully!",
            "checkpoint" => $checkpoint,
            "time" => $current_time,
            "current_fines" => number_format($finesData['total'], 2),
            "attendance" => $attData
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Event Attendance | CSSO</title>
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
  padding: 24px;
  min-height: 100vh;
}

.container {
  max-width: 1400px;
  margin: 0 auto;
}

/* Header */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 3px solid #e0f2fe;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.header-left i {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: white;
  padding: 14px;
  border-radius: 12px;
  font-size: 22px;
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
}

.header-left h2 {
  font-size: 26px;
  font-weight: 600;
  color: #1e3a5f;
  letter-spacing: -0.3px;
}

.back-btn {
  background: #64748b;
  color: white;
  border: none;
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.back-btn:hover {
  background: #475569;
  transform: translateY(-2px);
}

/* Event Info Card */
.event-info-card {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  padding: 24px;
  border-radius: 12px;
  margin-bottom: 24px;
  box-shadow: 0 4px 16px rgba(14, 165, 233, 0.3);
  color: white;
}

.event-info-card h3 {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.event-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-top: 16px;
}

.event-detail-item {
  background: rgba(255, 255, 255, 0.15);
  padding: 12px 16px;
  border-radius: 8px;
  backdrop-filter: blur(10px);
}

.event-detail-item label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  opacity: 0.9;
  display: block;
  margin-bottom: 4px;
}

.event-detail-item span {
  font-size: 16px;
  font-weight: 700;
}

/* NEW LAYOUT - Main Grid */
.main-grid {
  display: grid;
  grid-template-columns: 450px 1fr;
  gap: 24px;
}

/* LEFT COLUMN - Stacked Sections */
.left-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Student ID Input Section */
.student-id-section {
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #0c4a6e;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-title i {
  color: #0ea5e9;
  font-size: 18px;
}

.input-group label {
  font-weight: 600;
  color: #334155;
  margin-bottom: 8px;
  font-size: 13px;
  display: block;
}

.input-group input {
  width: 200px;
  padding: 14px 18px;
  border: 3px solid #e2e8f0;
  border-radius: 10px;
  font-size: 16px;
  font-weight: 600;
  transition: all 0.3s ease;
  background: white;
  color: #334155;
}

.input-group input:focus {
  outline: none;
  border-color: #0ea5e9;
  box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
}

/* Attendance Checkpoints Section */
.attendance-section {
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
}

.checkpoint-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 16px;
}

.checkpoint-card {
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
  padding: 18px;
  border-radius: 10px;
  border: 2px solid #bae6fd;
  text-align: center;
  transition: all 0.3s ease;
  cursor: pointer;
  position: relative;
}

.checkpoint-card:hover:not(.logged) {
  transform: translateY(-3px);
  box-shadow: 0 6px 16px rgba(14, 165, 233, 0.2);
  border-color: #0ea5e9;
}

.checkpoint-card.logged {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  border-color: #10b981;
  cursor: not-allowed;
}

.checkpoint-card.logged::before {
  content: '\f00c';
  font-family: 'Font Awesome 6 Free';
  font-weight: 900;
  position: absolute;
  top: 8px;
  right: 8px;
  color: #065f46;
  font-size: 16px;
}

.checkpoint-card h4 {
  font-size: 14px;
  font-weight: 700;
  color: #0c4a6e;
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.checkpoint-card .time {
  font-size: 13px;
  color: #64748b;
  font-weight: 600;
}

.checkpoint-card.logged h4 {
  color: #065f46;
}

.checkpoint-card.logged .time {
  color: #047857;
  font-size: 16px;
  font-weight: 700;
}

/* PM Section */
.pm-section {
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
}

/* RIGHT COLUMN - Student Info Display */
.right-column {
  background: white;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
  min-height: 600px;
}

#studentInfoDisplay {
  margin-top: 20px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  margin-bottom: 20px;
}

.info-field {
  background: #f8fafc;
  padding: 16px 20px;
  border-radius: 10px;
  border-left: 4px solid #0ea5e9;
}

.info-field label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #64748b;
  display: block;
  margin-bottom: 8px;
}

.info-field .value {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
}

.status-badge {
  display: inline-block;
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-member {
  background: #d1fae5;
  color: #065f46;
}

.status-not-registered {
  background: #fee2e2;
  color: #991b1b;
}

.fines-display {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  padding: 24px;
  border-radius: 12px;
  margin-top: 20px;
  border: 2px solid #fbbf24;
  text-align: center;
}

.fines-display h4 {
  font-size: 14px;
  font-weight: 600;
  color: #92400e;
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.fines-amount {
  font-size: 36px;
  font-weight: 700;
  color: #b45309;
}

.hidden {
  display: none;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #94a3b8;
}

.empty-state i {
  font-size: 80px;
  margin-bottom: 20px;
  opacity: 0.3;
}

.empty-state h3 {
  font-size: 20px;
  font-weight: 600;
  margin-bottom: 8px;
}

.empty-state p {
  font-size: 14px;
}

/* Responsive */
@media (max-width: 1200px) {
  .main-grid {
    grid-template-columns: 1fr;
  }
  
  .left-column {
    order: 2;
  }
  
  .right-column {
    order: 1;
  }
}

@media (max-width: 768px) {
  body {
    padding: 12px;
  }
  
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  
  .back-btn {
    width: 100%;
    justify-content: center;
  }
  
  .event-details {
    grid-template-columns: 1fr;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .checkpoint-grid {
    grid-template-columns: 1fr;
  }
  
  .input-group input {
    width: 100%;
  }
}
</style>
</head>
<body>

<div class="container">
  <!-- Header -->
  <div class="page-header">
    <div class="header-left">
      <i class="fa-solid fa-clipboard-check"></i>
      <h2>Event Attendance</h2>
    </div>
    <button class="back-btn" onclick="window.location.href='eve_att.php'">
      <i class="fa fa-arrow-left"></i> Back to Events
    </button>
  </div>

  <!-- Event Info Card -->
  <div class="event-info-card">
    <h3>
      <i class="fa-solid fa-calendar-star"></i>
      <?= htmlspecialchars($event_name) ?>
    </h3>
    <div class="event-details">
      <div class="event-detail-item">
        <label><i class="fa fa-calendar"></i> Event Date</label>
        <span><?= date('M d, Y', strtotime($event_date)) ?></span>
      </div>
      <div class="event-detail-item">
        <label><i class="fa fa-map-marker-alt"></i> Location</label>
        <span><?= htmlspecialchars($location) ?></span>
      </div>
      <div class="event-detail-item">
        <label><i class="fa fa-clock"></i> Time Now</label>
        <span id="currentTime"></span>
      </div>
    </div>
  </div>

  <!-- Main Grid Layout -->
  <div class="main-grid">
    <!-- LEFT COLUMN -->
    <div class="left-column">
      <!-- Student ID Input -->
      <div class="student-id-section">
        <div class="section-title">
          <i class="fa-solid fa-id-card"></i>
          Student ID
        </div>
        <div class="input-group">
          <label>Enter Student ID <span style="color: #ef4444;">*</span></label>
          <input 
            type="text" 
            id="studentIdInput" 
            placeholder="Student ID" 
            autocomplete="off"
            autofocus>
        </div>
      </div>

      <!-- AM Login/Logout -->
      <div class="attendance-section">
        <div class="section-title">
          <i class="fa-solid fa-sun"></i>
          AM Attendance
        </div>
        <div class="checkpoint-grid">
          <div class="checkpoint-card" id="amLoginCard" data-checkpoint="amLogin">
            <h4>AM Login</h4>
            <div class="time" id="amLoginTime">Not Logged</div>
          </div>
          <div class="checkpoint-card" id="amLogoutCard" data-checkpoint="amLogout">
            <h4>AM Logout</h4>
            <div class="time" id="amLogoutTime">Not Logged</div>
          </div>
        </div>
      </div>

      <!-- PM Login/Logout -->
      <div class="pm-section">
        <div class="section-title">
          <i class="fa-solid fa-moon"></i>
          PM Attendance
        </div>
        <div class="checkpoint-grid">
          <div class="checkpoint-card" id="pmLoginCard" data-checkpoint="pmLogin">
            <h4>PM Login</h4>
            <div class="time" id="pmLoginTime">Not Logged</div>
          </div>
          <div class="checkpoint-card" id="pmLogoutCard" data-checkpoint="pmLogout">
            <h4>PM Logout</h4>
            <div class="time" id="pmLogoutTime">Not Logged</div>
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT COLUMN - Student Info -->
    <div class="right-column">
      <div class="section-title">
        <i class="fa-solid fa-user-circle"></i>
        Student Information
      </div>

      <div id="studentInfoEmpty" class="empty-state">
        <i class="fa-solid fa-id-badge"></i>
        <h3>No Student Selected</h3>
        <p>Enter a Student ID to view information</p>
      </div>

      <div id="studentInfoDisplay" class="hidden">
        <div class="info-grid">
          <div class="info-field" style="grid-column: 1 / -1;">
            <label><i class="fa fa-user"></i> Full Name</label>
            <div class="value" id="fullName">-</div>
          </div>
          <div class="info-field">
            <label><i class="fa fa-book"></i> Course</label>
            <div class="value" id="course">-</div>
          </div>
          <div class="info-field">
            <label><i class="fa fa-layer-group"></i> Year Level</label>
            <div class="value" id="yearLevel">-</div>
          </div>
          <div class="info-field">
            <label><i class="fa fa-users"></i> Section</label>
            <div class="value" id="section">-</div>
          </div>
          <div class="info-field">
            <label><i class="fa fa-shield-check"></i> Status</label>
            <div class="value" id="status">-</div>
          </div>
        </div>

        <div class="fines-display">
          <h4><i class="fa fa-triangle-exclamation"></i> Current Fines</h4>
          <div class="fines-amount">₱<span id="finesAmount">0.00</span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let currentStudentId = null;
let currentRegistrationNo = null;

// Update current time
function updateTime() {
  const now = new Date();
  const timeString = now.toLocaleTimeString('en-US', { 
    hour: '2-digit', 
    minute: '2-digit', 
    second: '2-digit' 
  });
  document.getElementById('currentTime').textContent = timeString;
}
setInterval(updateTime, 1000);
updateTime();

// Student ID Input Handler
const studentIdInput = document.getElementById('studentIdInput');
let typingTimer;

studentIdInput.addEventListener('input', function() {
  clearTimeout(typingTimer);
  const studentId = this.value.trim();
  
  if (studentId.length >= 4) {
    typingTimer = setTimeout(() => {
      fetchStudentInfo(studentId);
    }, 500);
  } else {
    document.getElementById('studentInfoDisplay').classList.add('hidden');
    document.getElementById('studentInfoEmpty').classList.remove('hidden');
    resetCheckpoints();
  }
});

// Fetch student info
function fetchStudentInfo(studentId) {
  fetch(`addattendance.php?fetch_student=${studentId}&event_name=<?= urlencode($event_name) ?>`)
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        currentStudentId = studentId;
        currentRegistrationNo = data.registration_no;
        
        // Display student info
        document.getElementById('fullName').textContent = data.fullname;
        document.getElementById('course').textContent = data.course;
        document.getElementById('section').textContent = data.section;
        document.getElementById('yearLevel').textContent = data.yearLevel;
        
        const statusEl = document.getElementById('status');
        statusEl.innerHTML = `<span class="status-badge ${data.status === 'Member' ? 'status-member' : 'status-not-registered'}">${data.status}</span>`;
        
        document.getElementById('finesAmount').textContent = data.current_fines;
        
        document.getElementById('studentInfoEmpty').classList.add('hidden');
        document.getElementById('studentInfoDisplay').classList.remove('hidden');
        
        // Update checkpoint states
        updateCheckpoints(data.attendance);
        
        // Enable checkpoint clicking
        enableCheckpoints();
        
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Student Not Found',
          text: 'No student record found with this ID.',
          confirmButtonColor: '#ef4444',
          timer: 2000
        });
        document.getElementById('studentInfoDisplay').classList.add('hidden');
        document.getElementById('studentInfoEmpty').classList.remove('hidden');
        resetCheckpoints();
      }
    })
    .catch(error => {
      console.error('Error:', error);
    });
}

// Update checkpoint display
function updateCheckpoints(attendance) {
  resetCheckpoints();
  
  if (attendance) {
    if (attendance.amLogin) {
      document.getElementById('amLoginCard').classList.add('logged');
      document.getElementById('amLoginTime').textContent = formatTime(attendance.amLogin);
    }
    if (attendance.amLogout) {
      document.getElementById('amLogoutCard').classList.add('logged');
      document.getElementById('amLogoutTime').textContent = formatTime(attendance.amLogout);
    }
    if (attendance.pmLogin) {
      document.getElementById('pmLoginCard').classList.add('logged');
      document.getElementById('pmLoginTime').textContent = formatTime(attendance.pmLogin);
    }
    if (attendance.pmLogout) {
      document.getElementById('pmLogoutCard').classList.add('logged');
      document.getElementById('pmLogoutTime').textContent = formatTime(attendance.pmLogout);
    }
  }
}

function resetCheckpoints() {
  const cards = document.querySelectorAll('.checkpoint-card');
  cards.forEach(card => {
    card.classList.remove('logged');
    const timeEl = card.querySelector('.time');
    timeEl.textContent = 'Not Logged';
  });
  disableCheckpoints();
}

function enableCheckpoints() {
  const cards = document.querySelectorAll('.checkpoint-card:not(.logged)');
  cards.forEach(card => {
    card.onclick = function() {
      const checkpoint = this.dataset.checkpoint;
      logCheckpoint(checkpoint);
    };
  });
}

function disableCheckpoints() {
  const cards = document.querySelectorAll('.checkpoint-card');
  cards.forEach(card => {
    card.onclick = null;
  });
}

// Log checkpoint
function logCheckpoint(checkpoint) {
  if (!currentStudentId) return;
  
  const formData = new FormData();
  formData.append('save_attendance', '1');
  formData.append('students_id', currentStudentId);
  formData.append('registration_no', currentRegistrationNo);
  formData.append('event_name', '<?= htmlspecialchars($event_name) ?>');
  formData.append('event_date', '<?= htmlspecialchars($event_date) ?>');
  formData.append('location', '<?= htmlspecialchars($location) ?>');
  formData.append('checkpoint', checkpoint);
  
  fetch('addattendance.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: `${checkpoint.replace(/([A-Z])/g, ' $1').toUpperCase()} recorded!`,
        showConfirmButton: false,
        timer: 1000
      }).then(() => {
        // Update checkpoint display
        const card = document.getElementById(checkpoint + 'Card');
        card.classList.add('logged');
        document.getElementById(checkpoint + 'Time').textContent = formatTime(data.time);
        
        // Update fines
        document.getElementById('finesAmount').textContent = data.current_fines;
        
        // Update other checkpoints
        updateCheckpoints(data.attendance);
        
        // Clear for next student after 1 second
        setTimeout(() => {
          studentIdInput.value = '';
          document.getElementById('studentInfoDisplay').classList.add('hidden');
          document.getElementById('studentInfoEmpty').classList.remove('hidden');
          resetCheckpoints();
          currentStudentId = null;
          currentRegistrationNo = null;
          studentIdInput.focus();
        }, 1000);
      });
    } else {
      Swal.fire({
        icon: 'warning',
        title: 'Notice',
        text: data.message,
        confirmButtonColor: '#f59e0b'
      });
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Failed to save attendance.',
      confirmButtonColor: '#ef4444'
    });
  });
}

// Format time display
function formatTime(timeStr) {
  if (!timeStr) return 'Not Logged';
  const [h, m, s] = timeStr.split(':');
  const hour = parseInt(h);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const hour12 = hour % 12 || 12;
  return `${hour12}:${m} ${ampm}`;
}

// Prevent form submission on Enter
studentIdInput.addEventListener('keypress', function(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
  }
});
</script>

</body>
</html>
<?php $conn->close(); ?>
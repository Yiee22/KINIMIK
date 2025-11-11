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

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Show what's being received
    error_log("POST Data: " . print_r($_POST, true));
    
    $event_name = trim($_POST['event_name']);
    $event_date = trim($_POST['event_date']);
    $location = trim($_POST['location']);
    $time_session = $_POST['time_session'];
    $year_level = $_POST['year_level'];
    
    // Debug log
    error_log("Time Session: $time_session, Year Level: $year_level");
    
    // Check if event already exists
    $check_stmt = $conn->prepare("SELECT * FROM event WHERE event_Name = ? AND event_Date = ?");
    $check_stmt->bind_param("ss", $event_name, $event_date);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $message = "An event with the same name and date already exists!";
        $message_type = 'error';
    } else {
        // Use prepared statement for security
        $insert_stmt = $conn->prepare("INSERT INTO event (event_Name, event_Date, location, Time_Session, YearLevel) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssss", $event_name, $event_date, $location, $time_session, $year_level);
        
        if ($insert_stmt->execute()) {
            $message = "Event created successfully!";
            $message_type = 'success';
            
            // Clear form fields
            $_POST = array();
        } else {
            $message = "Error creating event: " . $insert_stmt->error;
            $message_type = 'error';
            error_log("SQL Error: " . $insert_stmt->error);
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add New Event | CSSO</title>
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
  max-width: 700px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 3px solid #e0f2fe;
}

.page-header i {
  background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
  color: white;
  padding: 14px;
  border-radius: 12px;
  font-size: 22px;
  box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

.page-header h2 {
  font-size: 26px;
  font-weight: 600;
  color: #1e3a5f;
  letter-spacing: -0.3px;
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

.form-container {
  background: white;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
}

.info-banner {
  background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
  padding: 14px 18px;
  border-radius: 8px;
  margin-bottom: 25px;
  border-left: 4px solid #0ea5e9;
  display: flex;
  align-items: center;
  gap: 12px;
  color: #0c4a6e;
  font-size: 14px;
}

.info-banner i {
  font-size: 18px;
  color: #0ea5e9;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #374151;
  font-size: 14px;
}

.form-group label .required {
  color: #ef4444;
  margin-left: 2px;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.3s ease;
  background: white;
  color: #334155;
  font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #8b5cf6;
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.form-group select {
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23334155' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 36px;
}

.form-group textarea {
  resize: vertical;
  min-height: 80px;
}

.submit-btn {
  background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
  color: white;
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
}

.submit-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
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

@media (max-width: 768px) {
  .container {
    padding: 16px;
  }
  
  .form-container {
    padding: 20px;
  }
  
  .form-grid {
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

    <div class="page-header">
        <i class="fa-solid fa-calendar-plus"></i>
        <h2>Add New Event</h2>
    </div>

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <div class="info-banner">
            <i class="fa-solid fa-circle-info"></i>
            <span>Fill out all required fields marked with <strong style="color: #ef4444;">*</strong> to create a new event.</span>
        </div>

        <form method="POST" action="" id="eventForm">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label for="event_name">Event Name <span class="required">*</span></label>
                    <input type="text" id="event_name" name="event_name" 
                           value="<?= htmlspecialchars($_POST['event_name'] ?? '') ?>" 
                           required placeholder="Enter event name">
                </div>

                <div class="form-group">
                    <label for="event_date">Event Date <span class="required">*</span></label>
                    <input type="date" id="event_date" name="event_date" 
                           value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>" 
                           required min="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label for="time_session">Time Session <span class="required">*</span></label>
                    <select id="time_session" name="time_session" required>
                        <option value="">Select Time Session</option>
                        <option value="AM Session">AM Session</option>
                        <option value="PM Session">PM Session</option>
                        <option value="Full Day">Full Day</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="year_level">Year Level <span class="required">*</span></label>
                    <select id="year_level" name="year_level" required>
                        <option value="">Select Year Level</option>
                        <option value="1stYearLevel">1st Year</option>
                        <option value="2ndYearLevel">2nd Year</option>
                        <option value="3rdYearLevel">3rd Year</option>
                        <option value="4thYearLevel">4th Year</option>
                        <option value="AllLevels">All Levels</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label for="location">Location <span class="required">*</span></label>
                    <input type="text" id="location" name="location" 
                           value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" 
                           required placeholder="Enter event location">
                </div>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fa fa-plus"></i> Create Event
            </button>
        </form>
    </div>
</div>

<script>
// Set minimum date to today
document.getElementById('event_date').min = new Date().toISOString().split('T')[0];

// Debug form submission
document.getElementById('eventForm').addEventListener('submit', function(e) {
    const formData = new FormData(this);
    console.log('Form Data Being Submitted:');
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }
    
    // Validate that Time Session and Year Level are selected
    const timeSession = document.getElementById('time_session').value;
    const yearLevel = document.getElementById('year_level').value;
    
    if (!timeSession || !yearLevel) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Incomplete Form',
            text: 'Please select both Time Session and Year Level'
        });
        return false;
    }
    
    console.log('Time Session:', timeSession);
    console.log('Year Level:', yearLevel);
});

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

// Show success message with SweetAlert
<?php if ($message_type === 'success'): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?= addslashes($message) ?>',
        timer: 2000,
        showConfirmButton: false
    }).then(() => {
        goBackToEvents();
    });
<?php elseif ($message_type === 'error'): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '<?= addslashes($message) ?>'
    });
<?php endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>
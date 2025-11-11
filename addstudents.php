<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['usertype'], ['Secretary', 'Treasurer', 'Auditor', 'Social Manager', 'Senator', 'Governor', 'Vice Governor'])) {
    header("Location: ../login.php");
    exit();
}

// Database Connection
$conn = new mysqli("localhost", "root", "", "csso");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ========== SAVE STUDENT INFO ==========
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $students_id = $_POST['students_id'];

    // Start Transaction
    $conn->begin_transaction();

    try {
        // ====== 1. INSERT INTO student_profile ======
        $stmt = $conn->prepare("INSERT INTO student_profile 
            (students_id, user_id, FirstName, LastName, MI, Suffix, Course, YearLevel, Section, PhoneNumber, Gender, DOB, Age, Religion, EmailAddress, Street, Barangay, Municipality, Province, Zipcode)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "iissssssssssisssssss",
            $students_id, $user_id, $_POST['FirstName'], $_POST['LastName'], $_POST['MI'], $_POST['Suffix'],
            $_POST['Course'], $_POST['YearLevel'], $_POST['Section'], $_POST['PhoneNumber'],
            $_POST['Gender'], $_POST['DOB'], $_POST['Age'], $_POST['Religion'],
            $_POST['EmailAddress'], $_POST['Street'], $_POST['Barangay'],
            $_POST['Municipality'], $_POST['Province'], $_POST['ZipCode']
        );
        $stmt->execute();

        // ====== 2. INSERT INTO family_background ======
        $stmt2 = $conn->prepare("INSERT INTO family_background 
            (students_id, father_name, father_occupation, mother_name, mother_occupation, phone_number, siblings_count, guardian_name, guardian_occupation, contact_number, street, barangay, municipality, province, zipcode)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt2->bind_param(
            "issssssisssssss",
            $students_id,
            $_POST['father_name'], $_POST['father_occupation'], $_POST['mother_name'], $_POST['mother_occupation'],
            $_POST['phone_number'], $_POST['siblings_count'], $_POST['guardian_name'], $_POST['guardian_occupation'],
            $_POST['contact_number'], $_POST['fam_street'], $_POST['fam_barangay'],
            $_POST['fam_municipality'], $_POST['fam_province'], $_POST['fam_zipcode']
        );
        $stmt2->execute();

        // ====== 3. INSERT INTO educational_background ======
        $stmt3 = $conn->prepare("INSERT INTO educational_background 
            (students_id, elementary, elem_year_grad, elem_received, junior_high, jr_high_grad, jr_received, senior_high, sr_high_grad, sr_received)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt3->bind_param(
            "isssssssss",
            $students_id,
            $_POST['elementary'], $_POST['elem_year_grad'], $_POST['elem_received'],
            $_POST['junior_high'], $_POST['jr_high_grad'], $_POST['jr_received'],
            $_POST['senior_high'], $_POST['sr_high_grad'], $_POST['sr_received']
        );
        $stmt3->execute();

        // Commit all
        $conn->commit();

        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Success!',
                text: 'Student successfully added!',
                icon: 'success',
                confirmButtonColor: '#0ea5e9'
            }).then(() => {
                window.location = 'students.php';
            });
        });
        </script>";

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Error!',
                text: 'Error saving student data: " . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
        });
        </script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Student | CSSO</title>
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
  padding: 24px 0;
}

.container {
  width: 95%;
  max-width: 1000px;
  margin: 0 auto;
  background: white;
  border-radius: 12px;
  padding: 30px;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
}

/* Header */
.page-header {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 30px;
  padding-bottom: 20px;
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

/* Form Sections */
section {
  margin-bottom: 35px;
  padding: 25px;
  background: #f8fafc;
  border-radius: 10px;
  border-left: 4px solid #0ea5e9;
}

section h3 {
  color: #0c4a6e;
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

section h3::before {
  content: '';
  width: 4px;
  height: 20px;
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  border-radius: 2px;
}

/* Form Grid Layout */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 18px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

label {
  font-weight: 600;
  color: #334155;
  margin-bottom: 6px;
  font-size: 14px;
}

label .required {
  color: #ef4444;
  margin-left: 2px;
}

input, select, textarea {
  width: 100%;
  padding: 10px 14px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  transition: all 0.3s ease;
  background: white;
  color: #334155;
}

input:focus, select:focus, textarea:focus {
  outline: none;
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

input[readonly] {
  background: #f1f5f9;
  color: #64748b;
  cursor: not-allowed;
}

select {
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23334155' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 36px;
}

/* Buttons */
.form-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-top: 35px;
  padding-top: 25px;
  border-top: 2px solid #e2e8f0;
}

.btn {
  padding: 12px 24px;
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

.btn-back {
  background: #64748b;
  color: white;
}

.btn-back:hover {
  background: #475569;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
}

.btn-save {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
}

.btn-save:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
}

/* Info Banner */
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

/* Responsive */
@media (max-width: 768px) {
  .container {
    width: 100%;
    border-radius: 0;
    padding: 20px;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column-reverse;
  }
  
  .btn {
    width: 100%;
    justify-content: center;
  }
}

/* Loading State */
.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
<script>
function calculateAge() {
    const dob = document.querySelector('input[name="DOB"]').value;
    if(dob){
        const birthDate = new Date(dob);
        const diff = Date.now() - birthDate.getTime();
        const age = new Date(diff).getUTCFullYear() - 1970;
        document.querySelector('input[name="Age"]').value = age;
    }
}

// Form validation before submit
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const studentId = document.querySelector('input[name="students_id"]').value;
        if (!studentId || studentId.length < 3) {
            e.preventDefault();
            Swal.fire({
                title: 'Invalid Student ID',
                text: 'Please enter a valid student ID.',
                icon: 'warning',
                confirmButtonColor: '#0ea5e9'
            });
        }
    });
});
</script>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="page-header">
        <i class="fa-solid fa-user-plus"></i>
        <h2>Add New Student</h2>
    </div>

    <!-- Info Banner -->
    <div class="info-banner">
        <i class="fa-solid fa-circle-info"></i>
        <span>Fill out all required fields marked with <strong style="color: #ef4444;">*</strong> to add a new student to the system.</span>
    </div>

    <form method="POST">
        <!-- STUDENT PROFILE -->
        <section>
            <h3><i class="fa-solid fa-user" style="color: #0ea5e9;"></i> Student Profile</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Student ID <span class="required">*</span></label>
                    <input type="number" name="students_id" required placeholder="Enter student ID">
                </div>
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="FirstName" required placeholder="Enter first name">
                </div>
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="LastName" required placeholder="Enter last name">
                </div>
                <div class="form-group">
                    <label>Middle Initial</label>
                    <input type="text" name="MI" maxlength="2" placeholder="MI">
                </div>
                <div class="form-group">
                    <label>Suffix</label>
                    <select name="Suffix">
                        <option value="">None</option>
                        <option value="Jr">Jr</option>
                        <option value="Sr">Sr</option>
                        <option value="III">III</option>
                        <option value="IV">IV</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Gender <span class="required">*</span></label>
                    <select name="Gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="DOB" onchange="calculateAge()">
                </div>
                <div class="form-group">
                    <label>Age</label>
                    <input type="number" name="Age" readonly placeholder="Auto-calculated">
                </div>
                <div class="form-group">
                    <label>Course <span class="required">*</span></label>
                    <select name="Course" required>
                        <option value="">Select Course</option>
                        <option value="BSIT">BSIT</option>
                        <option value="BSCS">BSCS</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year Level <span class="required">*</span></label>
                    <select name="YearLevel" required>
                        <option value="">Select Year Level</option>
                        <option value="1stYear">1st Year</option>
                        <option value="2ndYear">2nd Year</option>
                        <option value="3rdYear">3rd Year</option>
                        <option value="4thYear">4th Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Section <span class="required">*</span></label>
                    <select name="Section" required>
                        <option value="">Select Section</option>
                        <option value="BSIT 1A">BSIT 1A</option>
                        <option value="BSIT 1B">BSIT 1B</option>
                        <option value="BSIT 2A">BSIT 2A</option>
                        <option value="BSIT 2B">BSIT 2B</option>
                        <option value="BSIT 3A">BSIT 3A</option>
                        <option value="BSIT 3B">BSIT 3B</option>
                        <option value="BSIT 4A">BSIT 4A</option>
                        <option value="BSIT 4B">BSIT 4B</option>
                        <option value="BSCS 1A">BSCS 1A</option>
                        <option value="BSCS 1B">BSCS 1B</option>
                        <option value="BSCS 2A">BSCS 2A</option>
                        <option value="BSCS 2B">BSCS 2B</option>
                        <option value="BSCS 3A">BSCS 3A</option>
                        <option value="BSCS 3B">BSCS 3B</option>
                        <option value="BSCS 4A">BSCS 4A</option>
                        <option value="BSCS 4B">BSCS 4B</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Religion</label>
                    <input type="text" name="Religion" placeholder="Enter religion">
                </div>
                <div class="form-group full-width">
                    <label>Phone Number</label>
                    <input type="text" name="PhoneNumber" placeholder="Enter phone number">
                </div>
                <div class="form-group full-width">
                    <label>Email Address</label>
                    <input type="email" name="EmailAddress" placeholder="Enter email address">
                </div>
                <div class="form-group full-width">
                    <label>Street</label>
                    <input type="text" name="Street" placeholder="Enter street address">
                </div>
                <div class="form-group">
                    <label>Barangay</label>
                    <input type="text" name="Barangay" placeholder="Enter barangay">
                </div>
                <div class="form-group">
                    <label>Municipality</label>
                    <input type="text" name="Municipality" placeholder="Enter municipality">
                </div>
                <div class="form-group">
                    <label>Province</label>
                    <input type="text" name="Province" placeholder="Enter province">
                </div>
                <div class="form-group">
                    <label>Zip Code</label>
                    <input type="text" name="ZipCode" placeholder="Enter zip code">
                </div>
            </div>
        </section>

        <!-- FAMILY BACKGROUND -->
        <section>
            <h3><i class="fa-solid fa-users" style="color: #0ea5e9;"></i> Family Background</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Father's Name <span class="required">*</span></label>
                    <input type="text" name="father_name" required placeholder="Enter father's name">
                </div>
                <div class="form-group">
                    <label>Father's Occupation</label>
                    <input type="text" name="father_occupation" placeholder="Enter occupation">
                </div>
                <div class="form-group">
                    <label>Mother's Name</label>
                    <input type="text" name="mother_name" placeholder="Enter mother's name">
                </div>
                <div class="form-group">
                    <label>Mother's Occupation</label>
                    <input type="text" name="mother_occupation" placeholder="Enter occupation">
                </div>
                <div class="form-group">
                    <label>Family Phone Number</label>
                    <input type="text" name="phone_number" placeholder="Enter phone number">
                </div>
                <div class="form-group">
                    <label>Number of Siblings</label>
                    <input type="number" name="siblings_count" placeholder="0">
                </div>
                <div class="form-group">
                    <label>Guardian Name</label>
                    <input type="text" name="guardian_name" placeholder="Enter guardian name">
                </div>
                <div class="form-group">
                    <label>Guardian Occupation</label>
                    <input type="text" name="guardian_occupation" placeholder="Enter occupation">
                </div>
                <div class="form-group full-width">
                    <label>Guardian Contact Number</label>
                    <input type="text" name="contact_number" placeholder="Enter contact number">
                </div>
                <div class="form-group full-width">
                    <label>Street</label>
                    <input type="text" name="fam_street" placeholder="Enter street address">
                </div>
                <div class="form-group">
                    <label>Barangay</label>
                    <input type="text" name="fam_barangay" placeholder="Enter barangay">
                </div>
                <div class="form-group">
                    <label>Municipality</label>
                    <input type="text" name="fam_municipality" placeholder="Enter municipality">
                </div>
                <div class="form-group">
                    <label>Province</label>
                    <input type="text" name="fam_province" placeholder="Enter province">
                </div>
                <div class="form-group">
                    <label>Zip Code</label>
                    <input type="text" name="fam_zipcode" placeholder="Enter zip code">
                </div>
            </div>
        </section>

        <!-- EDUCATIONAL BACKGROUND -->
        <section>
            <h3><i class="fa-solid fa-graduation-cap" style="color: #0ea5e9;"></i> Educational Background</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Elementary School</label>
                    <input type="text" name="elementary" placeholder="Enter school name">
                </div>
                <div class="form-group">
                    <label>Year Graduated</label>
                    <input type="date" name="elem_year_grad">
                </div>
                <div class="form-group">
                    <label>Award Received</label>
                    <input type="text" name="elem_received" placeholder="Enter award (if any)">
                </div>
                <div class="form-group">
                    <label>Junior High School</label>
                    <input type="text" name="junior_high" placeholder="Enter school name">
                </div>
                <div class="form-group">
                    <label>Year Graduated</label>
                    <input type="date" name="jr_high_grad">
                </div>
                <div class="form-group">
                    <label>Award Received</label>
                    <input type="text" name="jr_received" placeholder="Enter award (if any)">
                </div>
                <div class="form-group">
                    <label>Senior High School</label>
                    <input type="text" name="senior_high" placeholder="Enter school name">
                </div>
                <div class="form-group">
                    <label>Year Graduated</label>
                    <input type="date" name="sr_high_grad">
                </div>
                <div class="form-group">
                    <label>Award Received</label>
                    <input type="text" name="sr_received" placeholder="Enter award (if any)">
                </div>
            </div>
        </section>

        <!-- Action Buttons -->
        <div class="form-actions">
            <button type="button" class="btn btn-back" onclick="window.location.href='students.php'">
                <i class="fa fa-arrow-left"></i> Back to List
            </button>
            <button type="submit" class="btn btn-save">
                <i class="fa fa-save"></i> Add Student
            </button>
        </div>
    </form>
</div>
</body>
</html>
<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['usertype'], ['Governor', 'Vice Governor'])) {
    header("Location: ../login.php");
    exit();
}

// ✅ Database Connection
$conn = new mysqli("localhost", "root", "", "csso");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Check for ID
if (!isset($_GET['students_id'])) {
    die("<script>alert('No student ID provided.'); window.location='students.php';</script>");
}

$students_id = intval($_GET['students_id']);

// ✅ Handle Update Request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn->begin_transaction();

        // 1️⃣ Update student_profile
        $stmt = $conn->prepare("UPDATE student_profile SET 
            FirstName=?, LastName=?, MI=?, Suffix=?, Course=?, YearLevel=?, Section=?, 
            PhoneNumber=?, Gender=?, DOB=?, Age=?, Religion=?, EmailAddress=?, Street=?, 
            Barangay=?, Municipality=?, Province=?, Zipcode=? WHERE students_id=?");
        $stmt->bind_param(
            "ssssssssssssssssssi",
            $_POST['FirstName'], $_POST['LastName'], $_POST['MI'], $_POST['Suffix'],
            $_POST['Course'], $_POST['YearLevel'], $_POST['Section'], $_POST['PhoneNumber'],
            $_POST['Gender'], $_POST['DOB'], $_POST['Age'], $_POST['Religion'],
            $_POST['EmailAddress'], $_POST['Street'], $_POST['Barangay'],
            $_POST['Municipality'], $_POST['Province'], $_POST['Zipcode'], $students_id
        );
        $stmt->execute();

        // 2️⃣ Update family_background
        $stmt2 = $conn->prepare("UPDATE family_background SET 
            father_name=?, father_occupation=?, mother_name=?, mother_occupation=?, phone_number=?, siblings_count=?, 
            guardian_name=?, guardian_occupation=?, contact_number=?, street=?, barangay=?, municipality=?, province=?, zipcode=? 
            WHERE students_id=?");
        $stmt2->bind_param(
            "sssssisissssssi",
            $_POST['father_name'], $_POST['father_occupation'], $_POST['mother_name'], $_POST['mother_occupation'],
            $_POST['phone_number'], $_POST['siblings_count'], $_POST['guardian_name'], $_POST['guardian_occupation'],
            $_POST['contact_number'], $_POST['fam_street'], $_POST['fam_barangay'], $_POST['fam_municipality'],
            $_POST['fam_province'], $_POST['fam_zipcode'], $students_id
        );
        $stmt2->execute();

        // 3️⃣ Update educational_background
        $stmt3 = $conn->prepare("UPDATE educational_background SET 
            elementary=?, elem_year_grad=?, elem_received=?, 
            junior_high=?, jr_high_grad=?, jr_received=?, 
            senior_high=?, sr_high_grad=?, sr_received=? 
            WHERE students_id=?");
        $stmt3->bind_param(
            "sssssssssi",
            $_POST['elementary'], $_POST['elem_year_grad'], $_POST['elem_received'],
            $_POST['junior_high'], $_POST['jr_high_grad'], $_POST['jr_received'],
            $_POST['senior_high'], $_POST['sr_high_grad'], $_POST['sr_received'], $students_id
        );
        $stmt3->execute();

        $conn->commit();

        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Success!',
                    text: 'Student information updated successfully!',
                    icon: 'success',
                    confirmButtonColor: '#0ea5e9'
                }).then(() => {
                    window.location = 'students.php';
                });
            });
        </script>";

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Update failed: " . addslashes($e->getMessage()) . "',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            });
        </script>";
    }
    exit;
}

// ✅ Fetch data from all tables
$sql = "
SELECT sp.*, fb.father_name, fb.father_occupation, fb.mother_name, fb.mother_occupation, 
       fb.phone_number AS fam_phone, fb.siblings_count, fb.guardian_name, fb.guardian_occupation, 
       fb.contact_number, fb.street AS fam_street, fb.barangay AS fam_barangay, 
       fb.municipality AS fam_municipality, fb.province AS fam_province, fb.zipcode AS fam_zipcode,
       eb.elementary, eb.elem_year_grad, eb.elem_received, eb.junior_high, eb.jr_high_grad, eb.jr_received, 
       eb.senior_high, eb.sr_high_grad, eb.sr_received
FROM student_profile sp
LEFT JOIN family_background fb ON sp.students_id = fb.students_id
LEFT JOIN educational_background eb ON sp.students_id = eb.students_id
WHERE sp.students_id = '$students_id'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("<script>alert('Student not found.'); window.location='students.php';</script>");
}
$data = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Student | CSSO</title>
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

/* Info Banner */
.info-banner {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  padding: 14px 18px;
  border-radius: 8px;
  margin-bottom: 25px;
  border-left: 4px solid #f59e0b;
  display: flex;
  align-items: center;
  gap: 12px;
  color: #78350f;
  font-size: 14px;
}

.info-banner i {
  font-size: 18px;
  color: #f59e0b;
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

.btn-update {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
}

.btn-update:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
}

/* Student ID Badge */
.student-id-badge {
  display: inline-block;
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  color: #1e40af;
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 16px;
  margin-bottom: 20px;
  border: 2px solid #93c5fd;
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
</script>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="page-header">
        <i class="fa-solid fa-pen-to-square"></i>
        <h2>Edit Student Information</h2>
    </div>

    <!-- Info Banner -->
    <div class="info-banner">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span>You are editing student record. Make sure all information is accurate before updating.</span>
    </div>

    <form method="POST">
        <!-- STUDENT PROFILE -->
        <section>
            <h3><i class="fa-solid fa-user" style="color: #0ea5e9;"></i> Student Profile</h3>
            
            <div class="student-id-badge">
                <i class="fa-solid fa-id-card"></i> Student ID: <?= htmlspecialchars($data['students_id']) ?>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="FirstName" value="<?= htmlspecialchars($data['FirstName']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="LastName" value="<?= htmlspecialchars($data['LastName']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Middle Initial</label>
                    <input type="text" name="MI" value="<?= htmlspecialchars($data['MI']) ?>" maxlength="2">
                </div>
                <div class="form-group">
                    <label>Suffix</label>
                    <select name="Suffix">
                        <option value="">None</option>
                        <option value="Jr" <?= $data['Suffix']=='Jr'?'selected':'' ?>>Jr</option>
                        <option value="Sr" <?= $data['Suffix']=='Sr'?'selected':'' ?>>Sr</option>
                        <option value="III" <?= $data['Suffix']=='III'?'selected':'' ?>>III</option>
                        <option value="IV" <?= $data['Suffix']=='IV'?'selected':'' ?>>IV</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Gender <span class="required">*</span></label>
                    <select name="Gender" required>
                        <option value="Male" <?= $data['Gender']=='Male'?'selected':'' ?>>Male</option>
                        <option value="Female" <?= $data['Gender']=='Female'?'selected':'' ?>>Female</option>
                        <option value="Other" <?= $data['Gender']=='Other'?'selected':'' ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="DOB" value="<?= htmlspecialchars($data['DOB']) ?>" onchange="calculateAge()">
                </div>
                <div class="form-group">
                    <label>Age</label>
                    <input type="number" name="Age" value="<?= htmlspecialchars($data['Age']) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Course <span class="required">*</span></label>
                    <select name="Course" required>
                        <option value="BSIT" <?= $data['Course']=='BSIT'?'selected':'' ?>>BSIT</option>
                        <option value="BSCS" <?= $data['Course']=='BSCS'?'selected':'' ?>>BSCS</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year Level <span class="required">*</span></label>
                    <select name="YearLevel" required>
                        <option value="1stYear" <?= $data['YearLevel']=='1stYear'?'selected':'' ?>>1st Year</option>
                        <option value="2ndYear" <?= $data['YearLevel']=='2ndYear'?'selected':'' ?>>2nd Year</option>
                        <option value="3rdYear" <?= $data['YearLevel']=='3rdYear'?'selected':'' ?>>3rd Year</option>
                        <option value="4thYear" <?= $data['YearLevel']=='4thYear'?'selected':'' ?>>4th Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Section <span class="required">*</span></label>
                    <input type="text" name="Section" value="<?= htmlspecialchars($data['Section']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Religion</label>
                    <input type="text" name="Religion" value="<?= htmlspecialchars($data['Religion']) ?>">
                </div>
                <div class="form-group full-width">
                    <label>Phone Number</label>
                    <input type="text" name="PhoneNumber" value="<?= htmlspecialchars($data['PhoneNumber']) ?>">
                </div>
                <div class="form-group full-width">
                    <label>Email Address</label>
                    <input type="email" name="EmailAddress" value="<?= htmlspecialchars($data['EmailAddress']) ?>">
                </div>
                <div class="form-group full-width">
                    <label>Street</label>
                    <input type="text" name="Street" value="<?= htmlspecialchars($data['Street']) ?>">
                </div>
                <div class="form-group">
                    <label>Barangay</label>
                    <input type="text" name="Barangay" value="<?= htmlspecialchars($data['Barangay']) ?>">
                </div>
                <div class="form-group">
                    <label>Municipality</label>
                    <input type="text" name="Municipality" value="<?= htmlspecialchars($data['Municipality']) ?>">
                </div>
                <div class="form-group">
                    <label>Province</label>
                    <input type="text" name="Province" value="<?= htmlspecialchars($data['Province']) ?>">
                </div>
                <div class="form-group">
                    <label>Zip Code</label>
                    <input type="text" name="Zipcode" value="<?= htmlspecialchars($data['Zipcode']) ?>">
                </div>
            </div>
        </section>

        <!-- FAMILY BACKGROUND -->
        <section>
            <h3><i class="fa-solid fa-users" style="color: #0ea5e9;"></i> Family Background</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Father's Name</label>
                    <input type="text" name="father_name" value="<?= htmlspecialchars($data['father_name']) ?>">
                </div>
                <div class="form-group">
                    <label>Father's Occupation</label>
                    <input type="text" name="father_occupation" value="<?= htmlspecialchars($data['father_occupation']) ?>">
                </div>
                <div class="form-group">
                    <label>Mother's Name</label>
                    <input type="text" name="mother_name" value="<?= htmlspecialchars($data['mother_name']) ?>">
                </div>
                <div class="form-group">
                    <label>Mother's Occupation</label>
                    <input type="text" name="mother_occupation" value="<?= htmlspecialchars($data['mother_occupation']) ?>">
                </div>
                <div class="form-group">
                    <label>Family Phone Number</label>
                    <input type="text" name="phone_number" value="<?= htmlspecialchars($data['fam_phone']) ?>">
                </div>
                <div class="form-group">
                    <label>Number of Siblings</label>
                    <input type="number" name="siblings_count" value="<?= htmlspecialchars($data['siblings_count']) ?>">
                </div>
                <div class="form-group">
                    <label>Guardian Name</label>
                    <input type="text" name="guardian_name" value="<?= htmlspecialchars($data['guardian_name']) ?>">
                </div>
                <div class="form-group">
                    <label>Guardian Occupation</label>
                    <input type="text" name="guardian_occupation" value="<?= htmlspecialchars($data['guardian_occupation']) ?>">
                </div>
                <div class="form-group full-width">
                    <label>Guardian Contact Number</label>
                    <input type="text" name="contact_number" value="<?= htmlspecialchars($data['contact_number']) ?>">
                </div>
                <div class="form-group full-width">
                    <label>Street</label>
                    <input type="text" name="fam_street" value="<?= htmlspecialchars($data['fam_street']) ?>">
                </div>
                <div class="form-group">
                    <label>Barangay</label>
                    <input type="text" name="fam_barangay" value="<?= htmlspecialchars($data['fam_barangay']) ?>">
                </div>
                <div class="form-group">
                    <label>Municipality</label>
                    <input type="text" name="fam_municipality" value="<?= htmlspecialchars($data['fam_municipality']) ?>">
                </div>
                <div class="form-group">
                    <label>Province</label>
                    <input type="text" name="fam_province" value="<?= htmlspecialchars($data['fam_province']) ?>">
                </div>
                <div class="form-group">
                    <label>Zip Code</label>
                    <input type="text" name="fam_zipcode" value="<?= htmlspecialchars($data['fam_zipcode']) ?>">
                </div>
            </div>
        </section>

        <!-- EDUCATIONAL BACKGROUND -->
        <section>
            <h3><i class="fa-solid fa-graduation-cap" style="color: #0ea5e9;"></i> Educational Background</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Elementary School</label>
                    <input type="text" name="elementary" value="<?= htmlspecialchars($data['elementary']) ?>">
                </div>
                <div class="form-group">
                    <label>Year Graduated</label>
                    <input type="date" name="elem_year_grad" value="<?= htmlspecialchars($data['elem_year_grad']) ?>">
                </div>
                <div class="form-group">
                    <label>Award Received</label>
                    <input type="text" name="elem_received" value="<?= htmlspecialchars($data['elem_received']) ?>">
                </div>
                <div class="form-group">
                    <label>Junior High School</label>
                    <input type="text" name="junior_high" value="<?= htmlspecialchars($data['junior_high']) ?>">
                </div>
                <div class="form-group">
                    <label>Year Graduated</label>
                    <input type="date" name="jr_high_grad" value="<?= htmlspecialchars($data['jr_high_grad']) ?>">
                </div>
                <div class="form-group">
                    <label>Award Received</label>
                    <input type="text" name="jr_received" value="<?= htmlspecialchars($data['jr_received']) ?>">
                </div>
                <div class="form-group">
                    <label>Senior High School</label>
                    <input type="text" name="senior_high" value="<?= htmlspecialchars($data['senior_high']) ?>">
                </div>
                <div class="form-group">
                    <label>Year Graduated</label>
                    <input type="date" name="sr_high_grad" value="<?= htmlspecialchars($data['sr_high_grad']) ?>">
                </div>
                <div class="form-group">
                    <label>Award Received</label>
                    <input type="text" name="sr_received" value="<?= htmlspecialchars($data['sr_received']) ?>">
                </div>
            </div>
        </section>

        <!-- Action Buttons -->
        <div class="form-actions">
            <button type="button" class="btn btn-back" onclick="window.location.href='students.php'">
                <i class="fa fa-arrow-left"></i> Back to List
            </button>
            <button type="submit" class="btn btn-update">
                <i class="fa fa-save"></i> Update Student
            </button>
        </div>
    </form>
</div>
</body>
</html>
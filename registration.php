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

// ✅ AUTO FETCH STUDENT NAME
if (isset($_GET['fetch_student'])) {
    $students_id = $_GET['fetch_student'];
    $query = "SELECT FirstName, LastName, MI, Suffix FROM student_profile WHERE students_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $students_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $fullname = trim($row['FirstName'] . ' ' . ($row['MI'] ? $row['MI'] . '. ' : '') . $row['LastName'] . ' ' . $row['Suffix']);
        echo json_encode(["success" => true, "fullname" => $fullname]);
    } else {
        echo json_encode(["success" => false]);
    }
    exit;
}

// ✅ SAVE WHEN PAY BUTTON IS CLICKED
if (isset($_POST['payNow'])) {
    $students_id = $_POST['students_id'];
    $registration_no = 'R' . rand(1000, 9999);
    $registration_date = date("Y-m-d");
    $semester = $_POST['semester'];
    $membership_fee = 100.00;
    $amount = $_POST['amount'];
    $payment_type = $_POST['payment_type'];
    $payment_status = $_POST['payment_status'];
    $user_id = $_SESSION['user_id'] ?? null;

    if ($user_id) {
        $query = "INSERT INTO registration (registration_no, students_id, registration_date, semester, membership_fee, amount, payment_type, payment_status, user_id)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssddssi", $registration_no, $students_id, $registration_date, $semester, $membership_fee, $amount, $payment_type, $payment_status, $user_id);
    } else {
        $query = "INSERT INTO registration (registration_no, students_id, registration_date, semester, membership_fee, amount, payment_type, payment_status)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssddss", $registration_no, $students_id, $registration_date, $semester, $membership_fee, $amount, $payment_type, $payment_status);
    }

    if ($stmt->execute()) {
        // fetch student full name for receipt
        $queryName = "SELECT FirstName, LastName, MI, Suffix FROM student_profile WHERE students_id = ?";
        $stmtName = $conn->prepare($queryName);
        $stmtName->bind_param("s", $students_id);
        $stmtName->execute();
        $resultName = $stmtName->get_result();
        $fullname = "";
        if ($resultName && $resultName->num_rows > 0) {
            $row = $resultName->fetch_assoc();
            $fullname = trim($row['FirstName'] . ' ' . ($row['MI'] ? $row['MI'] . '. ' : '') . $row['LastName'] . ' ' . $row['Suffix']);
        }

        echo "<script>
        window.onload = () => showReceiptPopup('$registration_no', '$students_id', '$fullname', '$registration_date', '$semester', '$membership_fee', '$amount', '$payment_type');
        </script>";
    } else {
        echo "<script>alert('Failed to save record. Please try again.');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registration | CSSO</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
}

.main-container {
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

.list-btn {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: white;
  border: none;
  padding: 10px 18px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
}

.list-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
}

/* Content Layout */
.content-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 20px;
}

/* Form Section */
.form-section {
  background: white;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 18px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-weight: 600;
  color: #334155;
  margin-bottom: 6px;
  font-size: 14px;
}

.form-group label .required {
  color: #ef4444;
  margin-left: 2px;
}

.form-group input,
.form-group select {
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

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.form-group input[readonly] {
  background: #f1f5f9;
  color: #64748b;
  font-weight: 600;
  cursor: not-allowed;
}

.form-group select {
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23334155' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 36px;
}

/* Right Panel - Payment Summary */
.payment-panel {
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  border-radius: 12px;
  padding: 30px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
  color: white;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.summary-card {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 10px;
  padding: 20px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  text-align: center;
  transition: all 0.3s ease;
}

.summary-card:hover {
  background: rgba(255, 255, 255, 0.08);
  transform: translateY(-2px);
}

.summary-card h3 {
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 12px;
  color: #94a3b8;
}

.summary-card .amount {
  font-size: 36px;
  font-weight: 700;
  margin: 10px 0;
}

.fee-amount { color: #ef4444; }
.paid-amount { color: #10b981; }
.change-amount { color: #f59e0b; }

.summary-card input {
  width: 100%;
  padding: 12px;
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-radius: 8px;
  font-size: 18px;
  text-align: center;
  background: rgba(255, 255, 255, 0.1);
  color: white;
  font-weight: 600;
  transition: all 0.3s ease;
}

.summary-card input:focus {
  outline: none;
  border-color: #0ea5e9;
  background: rgba(255, 255, 255, 0.15);
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.2);
}

.summary-card input::placeholder {
  color: rgba(255, 255, 255, 0.5);
}

.pay-button {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  border: none;
  padding: 16px;
  border-radius: 10px;
  font-size: 18px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
  margin-top: auto;
}

.pay-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

.pay-button:active {
  transform: translateY(0);
}

/* Receipt Popup */
.popup-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: none;
  justify-content: center;
  align-items: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}

.popup-content {
  background: white;
  padding: 35px 40px;
  border-radius: 12px;
  max-width: 500px;
  width: 90%;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  animation: popIn 0.3s ease;
  max-height: 90vh;
  overflow-y: auto;
}

@keyframes popIn {
  from {
    transform: scale(0.8);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

.receipt-header {
  text-align: center;
  margin-bottom: 25px;
  padding-bottom: 20px;
  border-bottom: 3px solid #e0f2fe;
}

.receipt-header h2 {
  color: #0c4a6e;
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 8px;
}

.receipt-header p {
  color: #64748b;
  font-size: 13px;
  margin: 3px 0;
}

.receipt-table {
  width: 100%;
  margin: 20px 0;
}

.receipt-table tr {
  border-bottom: 1px solid #f1f5f9;
}

.receipt-table td {
  padding: 12px 8px;
  color: #334155;
  font-size: 14px;
}

.receipt-table td:first-child {
  font-weight: 600;
  color: #1e293b;
  width: 45%;
}

.receipt-table td:last-child {
  text-align: right;
}

.receipt-highlight {
  color: #0ea5e9;
  font-weight: 700;
}

.receipt-actions {
  display: flex;
  gap: 12px;
  margin-top: 25px;
}

.receipt-btn {
  flex: 1;
  padding: 12px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.print-btn {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.print-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.close-btn {
  background: #64748b;
  color: white;
}

.close-btn:hover {
  background: #475569;
  transform: translateY(-2px);
}

/* Success Message Popup */
.success-popup {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: none;
  justify-content: center;
  align-items: center;
  z-index: 1001;
}

.success-content {
  background: white;
  padding: 30px 40px;
  border-radius: 12px;
  text-align: center;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  animation: popIn 0.3s ease;
}

.success-content i {
  color: #10b981;
  font-size: 48px;
  margin-bottom: 16px;
}

.success-content h3 {
  color: #1e293b;
  font-size: 18px;
  font-weight: 600;
  margin: 0;
}

/* Responsive */
@media (max-width: 1024px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
  
  .payment-panel {
    order: -1;
  }
}

@media (max-width: 768px) {
  body {
    padding: 12px;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  
  .list-btn {
    width: 100%;
    justify-content: center;
  }
}

/* Print Styles */
@media print {
  body * {
    visibility: hidden;
  }
  
  .popup-content, .popup-content * {
    visibility: visible;
  }
  
  .popup-content {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    box-shadow: none;
  }
  
  .receipt-actions {
    display: none;
  }
}
</style>
</head>
<body>

<div class="main-container">
  <!-- Header -->
  <div class="page-header">
    <div class="header-left">
      <i class="fa-solid fa-user-plus"></i>
      <h2>Student Registration</h2>
    </div>
    <button class="list-btn" onclick="window.location.href='reglist.php'">
      <i class="fa-solid fa-list"></i> Registration List
    </button>
  </div>

  <!-- Content Grid -->
  <div class="content-grid">
    <!-- Form Section -->
    <div class="form-section">
      <form method="POST" id="regForm">
        <div class="form-grid">
          <div class="form-group">
            <label>Student ID <span class="required">*</span></label>
            <input type="text" name="students_id" id="students_id" placeholder="Enter Student ID" required>
          </div>
          
          <div class="form-group">
            <label>Student Name</label>
            <input type="text" id="student_name" placeholder="Auto-filled" readonly>
          </div>
          
          <div class="form-group">
            <label>Registration Date</label>
            <input type="text" name="registration_date" value="<?php echo date('Y-m-d'); ?>" readonly>
          </div>
          
          <div class="form-group">
            <label>Semester <span class="required">*</span></label>
            <select name="semester" required>
              <option value="">Select Semester</option>
              <option value="First Semester">First Semester</option>
              <option value="Second Semester">Second Semester</option>
            </select>
          </div>
          
          <div class="form-group">
            <label>Membership Fee</label>
            <input type="text" name="membership_fee" value="₱100.00" readonly>
          </div>
          
          <div class="form-group">
            <label>Amount <span class="required">*</span></label>
            <input type="number" name="amount" id="amount" placeholder="Enter amount" required step="0.01">
          </div>
          
          <div class="form-group">
            <label>Payment Type <span class="required">*</span></label>
            <select name="payment_type" required>
              <option value="">Select Payment Type</option>
              <option value="Cash">Cash</option>
              <option value="Gcash">Gcash</option>
              <option value="Other">Other</option>
            </select>
          </div>
          
          <div class="form-group">
            <label>Payment Status <span class="required">*</span></label>
            <select name="payment_status" required>
              <option value="">Select Status</option>
              <option value="Paid">Paid</option>
              <option value="Unpaid">Unpaid</option>
              <option value="Partial Paid">Partial Paid</option>
            </select>
          </div>
        </div>
        <input type="hidden" name="payNow" value="1">
      </form>
    </div>

    <!-- Payment Panel -->
    <div class="payment-panel">
      <div class="summary-card">
        <h3>Membership Fee</h3>
        <div class="amount fee-amount">₱100.00</div>
      </div>
      
      <div class="summary-card">
        <h3>Amount to Pay</h3>
        <div class="amount paid-amount" id="displayAmount">₱0.00</div>
      </div>
      
      <div class="summary-card">
        <h3>Cash Received</h3>
        <input type="number" id="cash" placeholder="Enter cash amount" step="0.01">
      </div>
      
      <div class="summary-card">
        <h3>Change</h3>
        <div class="amount change-amount" id="changeDisplay">₱0.00</div>
      </div>
      
      <button class="pay-button" id="payBtn" type="button">
        <i class="fa-solid fa-money-bill-wave"></i>
        PROCESS PAYMENT
      </button>
    </div>
  </div>
</div>

<!-- Receipt Popup -->
<div class="popup-overlay" id="popupBox">
  <div class="popup-content" id="popupContent"></div>
</div>

<!-- Success Message Popup -->
<div class="success-popup" id="successPopup">
  <div class="success-content">
    <i class="fa-solid fa-circle-check"></i>
    <h3>Student is now a member of the CSSO!</h3>
  </div>
</div>

<script>
// Auto-fetch student name
const studentID = document.getElementById('students_id');
const studentName = document.getElementById('student_name');

studentID.addEventListener('input', function() {
  const id = this.value.trim();
  if (id.length >= 5) {
    fetch(`registration.php?fetch_student=${id}`)
      .then(res => res.json())
      .then(data => {
        studentName.value = data.success ? data.fullname : "No record found";
        if (!data.success) {
          studentName.style.color = '#ef4444';
        } else {
          studentName.style.color = '#10b981';
        }
      })
      .catch(() => {
        studentName.value = "Error fetching";
        studentName.style.color = '#ef4444';
      });
  } else {
    studentName.value = "";
  }
});

// Amount and change calculation
const amountInput = document.getElementById('amount');
const cashInput = document.getElementById('cash');
const displayAmount = document.getElementById('displayAmount');
const changeDisplay = document.getElementById('changeDisplay');

amountInput.addEventListener('input', () => {
  const amt = parseFloat(amountInput.value || 0);
  displayAmount.textContent = '₱' + amt.toFixed(2);
  calculateChange();
});

cashInput.addEventListener('input', calculateChange);

function calculateChange() {
  const amt = parseFloat(amountInput.value || 0);
  const cash = parseFloat(cashInput.value || 0);
  const change = cash - amt;
  changeDisplay.textContent = '₱' + (change > 0 ? change.toFixed(2) : '0.00');
}

// Pay button
document.getElementById('payBtn').addEventListener('click', function() {
  const form = document.getElementById('regForm');
  if (form.checkValidity()) {
    form.submit();
  } else {
    form.reportValidity();
  }
});

// Receipt popup
function showReceiptPopup(no, id, fullname, date, sem, fee, amount, type) {
  const popup = document.getElementById('popupBox');
  const content = document.getElementById('popupContent');
  popup.style.display = 'flex';
  
  content.innerHTML = `
    <div class="receipt-header">
      <h2>COMPUTER STUDIES STUDENT ORGANIZATION</h2>
      <p>Camiguin Polytechnic State College</p>
      <p>Balbagon, Mambajao 9100, Camiguin Province</p>
    </div>
    
    <table class="receipt-table">
      <tr>
        <td>Registration No:</td>
        <td class="receipt-highlight">${no}</td>
      </tr>
      <tr>
        <td>Student ID:</td>
        <td>${id}</td>
      </tr>
      <tr>
        <td>Student Name:</td>
        <td><strong>${fullname}</strong></td>
      </tr>
      <tr>
        <td>Date:</td>
        <td>${date}</td>
      </tr>
      <tr>
        <td>Semester:</td>
        <td>${sem}</td>
      </tr>
      <tr>
        <td>Membership Fee:</td>
        <td>₱${parseFloat(fee).toFixed(2)}</td>
      </tr>
      <tr>
        <td>Amount Paid:</td>
        <td class="receipt-highlight">₱${parseFloat(amount).toFixed(2)}</td>
      </tr>
      <tr>
        <td>Payment Type:</td>
        <td>${type}</td>
      </tr>
    </table>
    
    <div class="receipt-actions">
      <button class="receipt-btn print-btn" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Print Receipt
      </button>
      <button class="receipt-btn close-btn" id="closeReceiptBtn">
        <i class="fa-solid fa-xmark"></i> Close
      </button>
    </div>
  `;
  
  document.getElementById('closeReceiptBtn').onclick = function() {
    popup.style.display = 'none';
    showSuccessMessage();
  };
}

function showSuccessMessage() {
  const successPopup = document.getElementById('successPopup');
  successPopup.style.display = 'flex';
  setTimeout(() => {
    successPopup.style.display = 'none';
    window.location.href = 'registration.php';
  }, 2500);
}
</script>

</body>
</html>
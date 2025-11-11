<?php
// Connect to database
$conn = new mysqli("localhost", "root", "", "csso");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get dashboard data
$totalStudents = $conn->query("SELECT COUNT(*) FROM student_profile")->fetch_row()[0] ?? 0;
$registrationCollected = $conn->query("SELECT IFNULL(SUM(amount),0) FROM registration WHERE payment_status='Paid'")->fetch_row()[0] ?? 0;
$finesCollected = $conn->query("SELECT IFNULL(SUM(penalty_amount),0) FROM fines_payments WHERE payment_status='Paid'")->fetch_row()[0] ?? 0;
$totalIncome = $registrationCollected + $finesCollected;

$recent = $conn->query("SELECT sp.FirstName, sp.LastName, r.registration_date 
                        FROM registration r 
                        JOIN student_profile sp ON r.students_id = sp.students_id 
                        ORDER BY r.registration_date DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | CSSO</title>
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
}

.container {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

/* Stats Cards Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
    display: flex;
    align-items: center;
    gap: 18px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    transition: width 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.stat-card:hover::before {
    width: 100%;
    opacity: 0.05;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    flex-shrink: 0;
}

.stat-content h3 {
    font-size: 13px;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.stat-content .stat-value {
    font-size: 26px;
    font-weight: 700;
    color: #1e293b;
}

/* Color Schemes for Cards */
.stat-card.blue::before { background: #0ea5e9; }
.stat-card.blue .stat-icon {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    color: #0284c7;
}

.stat-card.green::before { background: #10b981; }
.stat-card.green .stat-icon {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #059669;
}

.stat-card.orange::before { background: #f59e0b; }
.stat-card.orange .stat-icon {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #d97706;
}

.stat-card.purple::before { background: #8b5cf6; }
.stat-card.purple .stat-icon {
    background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
    color: #7c3aed;
}

/* Bottom Section Layout */
.bottom-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 20px;
}

.panel {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
}

.panel-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 2px solid #f1f5f9;
}

.panel-header i {
    color: #0ea5e9;
    font-size: 20px;
}

.panel-header h3 {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
}

/* Recent Table */
.recent-table {
    width: 100%;
    border-collapse: collapse;
}

.recent-table thead {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
}

.recent-table th {
    padding: 12px;
    text-align: left;
    color: white;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.recent-table td {
    padding: 14px 12px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    font-size: 14px;
}

.recent-table tbody tr {
    transition: all 0.2s ease;
}

.recent-table tbody tr:hover {
    background: #f0f9ff;
}

.recent-table tbody tr:last-child td {
    border-bottom: none;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
}

.empty-state i {
    font-size: 48px;
    color: #cbd5e1;
    margin-bottom: 12px;
}

/* Calendar */
.calendar-container {
    text-align: center;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.calendar-header h4 {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}

.calendar-nav {
    display: flex;
    gap: 8px;
}

.calendar-nav button {
    background: #f1f5f9;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
    color: #64748b;
    font-size: 14px;
    transition: all 0.2s ease;
}

.calendar-nav button:hover {
    background: #0ea5e9;
    color: white;
}

.calendar-table {
    width: 100%;
    border-collapse: collapse;
}

.calendar-table th {
    color: #64748b;
    font-weight: 600;
    font-size: 12px;
    padding: 10px 0;
    text-transform: uppercase;
}

.calendar-table td {
    padding: 10px;
    text-align: center;
    color: #334155;
    font-size: 14px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.calendar-table td:hover:not(.empty-cell) {
    background: #e0f2fe;
    color: #0284c7;
    transform: scale(1.1);
}

.calendar-table .today {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    color: white;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
}

.calendar-table .empty-cell {
    cursor: default;
}

.calendar-table .other-month {
    color: #cbd5e1;
}

/* Responsive */
@media (max-width: 1024px) {
    .bottom-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 22px;
    }
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-card {
    animation: fadeInUp 0.5s ease forwards;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }
</style>
</head>
<body>
<div class="container">
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="stat-content">
                <h3>Total Students</h3>
                <div class="stat-value"><?= number_format($totalStudents) ?></div>
            </div>
        </div>

        <div class="stat-card green">
            <div class="stat-icon">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <div class="stat-content">
                <h3>Registration Collected</h3>
                <div class="stat-value">₱<?= number_format($registrationCollected, 2) ?></div>
            </div>
        </div>

        <div class="stat-card orange">
            <div class="stat-icon">
                <i class="fa-solid fa-gavel"></i>
            </div>
            <div class="stat-content">
                <h3>Fines Collected</h3>
                <div class="stat-value">₱<?= number_format($finesCollected, 2) ?></div>
            </div>
        </div>

        <div class="stat-card purple">
            <div class="stat-icon">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div class="stat-content">
                <h3>Total Income</h3>
                <div class="stat-value">₱<?= number_format($totalIncome, 2) ?></div>
            </div>
        </div>
    </div>

    <!-- Bottom Grid -->
    <div class="bottom-grid">
        <!-- Recent Registrations -->
        <div class="panel">
            <div class="panel-header">
                <i class="fa-solid fa-user-plus"></i>
                <h3>Recent Registered Students</h3>
            </div>
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Date Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent && $recent->num_rows > 0): ?>
                        <?php while($r = $recent->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?></strong></td>
                                <td><?= date('M d, Y', strtotime($r['registration_date'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2">
                                <div class="empty-state">
                                    <i class="fa-solid fa-inbox"></i>
                                    <p>No recent registrations found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Calendar -->
        <div class="panel">
            <div class="panel-header">
                <i class="fa-solid fa-calendar-days"></i>
                <h3>Calendar</h3>
            </div>
            <div class="calendar-container" id="calendar"></div>
        </div>
    </div>
</div>

<script>
// Enhanced Calendar Generator
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

function generateCalendar(month = currentMonth, year = currentYear) {
    const today = new Date();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const prevLastDay = new Date(year, month, 0);

    const months = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    let html = `
        <div class="calendar-header">
            <button onclick="changeMonth(-1)"><i class="fa fa-chevron-left"></i></button>
            <h4>${months[month]} ${year}</h4>
            <button onclick="changeMonth(1)"><i class="fa fa-chevron-right"></i></button>
        </div>
        <table class="calendar-table">
            <thead>
                <tr>
                    <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th>
                    <th>Thu</th><th>Fri</th><th>Sat</th>
                </tr>
            </thead>
            <tbody>
    `;

    // Add days
    let day = 1;
    let nextMonthDay = 1;
    
    for (let week = 0; week < 6; week++) {
        html += '<tr>';
        for (let dayOfWeek = 0; dayOfWeek < 7; dayOfWeek++) {
            if (week === 0 && dayOfWeek < firstDay.getDay()) {
                // Previous month's days
                const prevDay = prevLastDay.getDate() - (firstDay.getDay() - dayOfWeek - 1);
                html += `<td class="empty-cell other-month">${prevDay}</td>`;
            } else if (day > lastDay.getDate()) {
                // Next month's days
                html += `<td class="empty-cell other-month">${nextMonthDay++}</td>`;
            } else {
                // Current month's days
                const isToday = (day === today.getDate() && month === today.getMonth() && year === today.getFullYear());
                html += `<td class="${isToday ? 'today' : ''}">${day}</td>`;
                day++;
            }
        }
        html += '</tr>';
        if (day > lastDay.getDate()) break;
    }

    html += '</tbody></table>';
    document.getElementById('calendar').innerHTML = html;
}

function changeMonth(direction) {
    currentMonth += direction;
    if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    } else if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    }
    generateCalendar(currentMonth, currentYear);
}

// Initialize calendar
generateCalendar();
</script>
</body>
</html>
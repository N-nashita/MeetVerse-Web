<?php
session_start();
require 'db.php';

if (!isset($_SESSION['emp_id'])) {
    header('Location: login.html');
    exit();
}

$countstmt = $pdo->query("SELECT COUNT(*) FROM employees");
$totalEmployees = $countstmt->fetchColumn();

$empStmt = $pdo->query("SELECT emp_id, first_name, last_name, email, department, created_at FROM employees ORDER BY created_at DESC");
$employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

$deptStmt = $pdo->query("SELECT COUNT(DISTINCT department) FROM employees");
$totalDepts = $deptStmt->fetchColumn();

$meetingStmt = $pdo->query("SELECT COUNT(*) FROM meetings");
$totalMeetings = $meetingStmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MeetVerse · Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Moon+Dance&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="dashboard-layout">

    <aside class="sidebar">
      <a href="index.html" class="sidebar-logo">
        <div class="logo-icon">M</div>
            <span class="logo-text">Meet<span>Verse</span></span>
      </a>

      <span class="nav-label">Main</span>
      <a class="nav-item active" href="admin_dashboard.php">
        <span class="icon">⊞</span> Dashboard
      </a>
      <a class="nav-item" href="create_meeting.php">
        <span class="icon">📅</span> Meetings
      </a>
      <a class="nav-item" href="employees.php">
        <span class="icon">👥</span> Employees
      </a>

      <span class="nav-label">Reports</span>
      <a class="nav-item" href="#">
        <span class="icon">📊</span> Analytics
      </a>
      <a class="nav-item" href="#">
        <span class="icon">📋</span> Reports
      </a>

      <span class="nav-label">System</span>
      <a class="nav-item" href="#">
        <span class="icon">⚙️</span> Settings
      </a>

      <div class="sidebar-footer">
        <div class="user-pill">
          <div class="user-avatar">
            <?= strtoupper(substr($_SESSION['first_name'], 0, 1)) ?>
          </div>
          <div class="user-info">
            <p><?= htmlspecialchars($_SESSION['first_name']) ?></p>
            <span>Admin</span>
          </div>
        </div>
        <a href="logout.php" class="logout-btn">Sign Out</a>
      </div>
    </aside>

    <main class="main-content">
      <div class="page-header">
        <div class="page-header-text">
        <h1>Good to see you, <?= htmlspecialchars($_SESSION['first_name']) ?> 👋</h1>
        <p>Here's what's happening in MeetVerse today.</p>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Total Employees</div>
          <div class="stat-value"><?= $totalEmployees ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Departments</div>
          <div class="stat-value"><?= $totalDepts ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Meetings</div>
          <div class="stat-value"><?= $totalMeetings ?></div>
        </div>
      </div>

      <div class="table-card">
        <div class="table-header">
          <h3>All Employees</h3>
          <span><?= $totalEmployees ?> total</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Department</th>
              <th>Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php if(count($employees) > 0): ?>
              <?php foreach($employees as $emp): ?>
                <tr>
                  <td>
                    <div class="emp-name">
                      <div class="emp-avatar">
                        <?= strtoupper(substr($emp['FIRST_NAME'], 0, 1)) ?>
                      </div>
                      <?= htmlspecialchars($emp['FIRST_NAME'] . ' ' . $emp['LAST_NAME']) ?>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($emp['EMAIL']) ?></td>
                  <td><span class="dept-badge"><?= htmlspecialchars($emp['DEPARTMENT']) ?></span></td>
                  <td><?= $emp['CREATED_AT'] ? date('M d, Y', strtotime($emp['CREATED_AT'])) : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr class="empty-row">
                <td colspan="4">No employees registered yet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>
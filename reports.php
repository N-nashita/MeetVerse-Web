<?php
session_start();
require 'db.php';

if (!isset($_SESSION['emp_id'])) {
    header('Location: login.html');
    exit();
}

// Meeting summary
$summaryStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'SCHEDULED' THEN 1 ELSE 0 END) AS scheduled,
        SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled
    FROM meetings
");
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

// Department-wise meeting count
$deptMeetingStmt = $pdo->query("
    SELECT e.department, COUNT(m.meeting_id) AS total
    FROM employees e
    LEFT JOIN meetings m ON m.organizer_id = e.emp_id
    GROUP BY e.department
    ORDER BY total DESC
");
$deptMeetings = $deptMeetingStmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly meeting count
$monthlyStmt = $pdo->query("
    SELECT TO_CHAR(start_time, 'Mon YYYY') AS month,
           COUNT(*) AS total
    FROM meetings
    GROUP BY TO_CHAR(start_time, 'Mon YYYY')
    ORDER BY MIN(start_time)
");
$monthly = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

// Department-wise employee count
$deptEmpStmt = $pdo->query("
    SELECT department, COUNT(*) AS total
    FROM employees
    GROUP BY department
    ORDER BY total DESC
");
$deptEmps = $deptEmpStmt->fetchAll(PDO::FETCH_ASSOC);

// Meetings per organizer
$organizerStmt = $pdo->query("
    SELECT e.first_name || ' ' || e.last_name AS name,
           e.department,
           COUNT(m.meeting_id) AS total
    FROM employees e
    LEFT JOIN meetings m ON m.organizer_id = e.emp_id
    GROUP BY e.emp_id, e.first_name, e.last_name, e.department
    ORDER BY total DESC
");
$organizers = $organizerStmt->fetchAll(PDO::FETCH_ASSOC);

// Upcoming meetings
$upcomingStmt = $pdo->query("
    SELECT * FROM (
        SELECT m.title, m.start_time, m.end_time,
               e.first_name || ' ' || e.last_name AS organizer
        FROM meetings m
        JOIN employees e ON e.emp_id = m.organizer_id
        WHERE m.start_time > SYSDATE AND m.status = 'SCHEDULED'
        ORDER BY m.start_time ASC
    ) WHERE ROWNUM <= 10
");
$upcoming = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

// Recently joined employees
$recentEmpStmt = $pdo->query("
    SELECT * FROM (
        SELECT first_name || ' ' || last_name AS name,
               department, email, created_at
        FROM employees
        ORDER BY created_at DESC
    ) WHERE ROWNUM <= 5
");
$recentEmps = $recentEmpStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MeetVerse · Reports</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Moon+Dance&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <canvas id="canvas"></canvas>

  <div class="dashboard-layout">
    <aside class="sidebar">
      <a href="index.html" class="sidebar-logo">
        <div class="logo-icon">M</div>
        <span class="logo-text">Meet<span>Verse</span></span>
      </a>

      <span class="nav-label">Main</span>
      <a class="nav-item" href="admin_dashboard.php">
        <span class="icon">⊞</span> Dashboard
      </a>
      <a class="nav-item" href="meeting.php">
        <span class="icon">📅</span> Meetings
      </a>
      <a class="nav-item" href="employees.php">
        <span class="icon">👥</span> Employees
      </a>
      <a class="nav-item active" href="reports.php">
        <span class="icon">📋</span> Reports
      </a>

      <span class="nav-label">System</span>
      <?php $settingsPage = ($_SESSION['role']) === 'ADMIN' ? 'admin_settings.php' : 'member_settings.php'; ?>
      <a class="nav-item" href="<?= $settingsPage ?>">
        <span class="icon">⚙️</span> Settings
      </a>

      <div class="sidebar-footer">
        <div class="user-pill">
          <div class="user-avatar"><?= strtoupper(substr($_SESSION['first_name'], 0, 1)) ?></div>
          <div class="user-info">
            <p><?= htmlspecialchars($_SESSION['first_name']) ?></p>
            <span><?= $_SESSION['role']=== 'ADMIN' ? 'Admin' : 'Member' ?></span>
          </div>
        </div>
        <a href="logout.php" class="logout-btn">Sign Out</a>
      </div>
    </aside>

    <main class="main-content">
      <div class="page-header">
        <h1>Reports</h1>
        <p>Overview of meetings, employees, and activity across MeetVerse.</p>
      </div>

      <!-- Meeting Summary -->
      <div class="summary-grid">
        <div class="stat-card">
          <div class="stat-label">Total Meetings</div>
          <div class="stat-value"><?= $summary['TOTAL'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Scheduled</div>
          <div class="stat-value yellow"><?= $summary['SCHEDULED'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Completed</div>
          <div class="stat-value green"><?= $summary['COMPLETED'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Cancelled</div>
          <div class="stat-value red"><?= $summary['CANCELLED'] ?? 0 ?></div>
        </div>
      </div>

      <div class="reports-grid">

        <!-- Department-wise meeting count -->
        <div class="report-card">
          <div class="report-card-header">
            <h3>Meetings by Department</h3>
            <span><?= count($deptMeetings) ?> departments</span>
          </div>
          <div class="report-card-body">
            <?php if(count($deptMeetings) > 0):
              $maxDept = max(array_column($deptMeetings, 'TOTAL')) ?: 1;
              foreach($deptMeetings as $d): ?>
                <div class="bar-row">
                  <div class="bar-label"><?= htmlspecialchars($d['DEPARTMENT']) ?></div>
                  <div class="bar-track">
                    <div class="bar-fill" style="width:<?= ($d['TOTAL'] / $maxDept) * 100 ?>%"></div>
                  </div>
                  <div class="bar-count"><?= $d['TOTAL'] ?></div>
                </div>
            <?php endforeach; else: ?>
              <div class="empty-note">No data yet.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Department-wise employee count -->
        <div class="report-card">
          <div class="report-card-header">
            <h3>Employees by Department</h3>
            <span><?= count($deptEmps) ?> departments</span>
          </div>
          <div class="report-card-body">
            <?php if(count($deptEmps) > 0):
              $maxEmp = max(array_column($deptEmps, 'TOTAL')) ?: 1;
              foreach($deptEmps as $d): ?>
                <div class="bar-row">
                  <div class="bar-label"><?= htmlspecialchars($d['DEPARTMENT']) ?></div>
                  <div class="bar-track">
                    <div class="bar-fill" style="width:<?= ($d['TOTAL'] / $maxEmp) * 100 ?>%; background:linear-gradient(90deg,#0f766e,#14b8a6);"></div>
                  </div>
                  <div class="bar-count"><?= $d['TOTAL'] ?></div>
                </div>
            <?php endforeach; else: ?>
              <div class="empty-note">No data yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Row 2 -->
      <div class="reports-grid">

        <!-- Monthly meeting count -->
        <div class="report-card">
          <div class="report-card-header">
            <h3>Meetings by Month</h3>
            <span><?= count($monthly) ?> months</span>
          </div>
          <div class="report-card-body">
            <?php if(count($monthly) > 0):
              $maxMonth = max(array_column($monthly, 'TOTAL')) ?: 1;
              foreach($monthly as $m): ?>
                <div class="bar-row">
                  <div class="bar-label"><?= htmlspecialchars($m['MONTH']) ?></div>
                  <div class="bar-track">
                    <div class="bar-fill" style="width:<?= ($m['TOTAL'] / $maxMonth) * 100 ?>%; background:linear-gradient(90deg,#7c3aed,#a78bfa);"></div>
                  </div>
                  <div class="bar-count"><?= $m['TOTAL'] ?></div>
                </div>
            <?php endforeach; else: ?>
              <div class="empty-note">No meetings scheduled yet.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Meetings per organizer -->
        <div class="report-card">
          <div class="report-card-header">
            <h3>Meetings per Organizer</h3>
            <span><?= count($organizers) ?> employees</span>
          </div>
          <div class="report-card-body">
            <?php if(count($organizers) > 0): ?>
              <table class="report-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Meetings</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($organizers as $o): ?>
                    <tr>
                      <td><?= htmlspecialchars($o['NAME']) ?></td>
                      <td><span class="badge"><?= htmlspecialchars($o['DEPARTMENT']) ?></span></td>
                      <td><?= $o['TOTAL'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div class="empty-note">No data yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Row 3 -->
      <div class="reports-grid">

        <!-- Upcoming meetings -->
        <div class="report-card">
          <div class="report-card-header">
            <h3>Upcoming Meetings</h3>
            <span>Next 10</span>
          </div>
          <div class="report-card-body">
            <?php if(count($upcoming) > 0): ?>
              <table class="report-table">
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Date</th>
                    <th>Organizer</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($upcoming as $u): ?>
                    <tr>
                      <td><?= htmlspecialchars($u['TITLE']) ?></td>
                      <td><?= date('M d, Y H:i', strtotime($u['START_TIME'])) ?></td>
                      <td><?= htmlspecialchars($u['ORGANIZER']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div class="empty-note">No upcoming meetings.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recently joined employees -->
        <div class="report-card">
          <div class="report-card-header">
            <h3>Recently Joined</h3>
            <span>Last 5 employees</span>
          </div>
          <div class="report-card-body">
            <?php if(count($recentEmps) > 0): ?>
              <table class="report-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Joined</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($recentEmps as $e): ?>
                    <tr>
                      <td><?= htmlspecialchars($e['NAME']) ?></td>
                      <td><span class="badge green"><?= htmlspecialchars($e['DEPARTMENT']) ?></span></td>
                      <td><?= date('M d, Y', strtotime($e['CREATED_AT'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div class="empty-note">No employees yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </main>
  </div>
</body>
</html>
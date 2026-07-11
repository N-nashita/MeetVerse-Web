<?php
session_start();
require 'db.php';

if (!isset($_SESSION['emp_id'])) {
    header('Location: login.html');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] === '') {
    header('Location: logout.php');
    exit();
}
if ($_SESSION['role'] !== 'ADMIN') {
    header('Location: member_dashboard.php');
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

function formatMeetingTime($value, $format) {
  $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $value);
  if (!$dateTime) {
    $timestamp = strtotime($value);
    return $timestamp ? date($format, $timestamp) : $value;
  }

  return $dateTime->format($format);
}

// Refresh statuses via PL/SQL, then pull anything currently Ongoing
$pdo->query("BEGIN refresh_meeting_statuses; END;");
$ongoingStmt = $pdo->query("
        SELECT meeting_id, title, description,
          TO_CHAR(start_time, 'YYYY-MM-DD HH24:MI:SS') AS start_time,
          TO_CHAR(end_time, 'YYYY-MM-DD HH24:MI:SS') AS end_time,
          organizer_name, attendee_count
    FROM meeting_overview
    WHERE status = 'Ongoing'
    ORDER BY start_time
");
$ongoingMeetings = $ongoingStmt->fetchAll(PDO::FETCH_ASSOC);

$pdo->query("BEGIN refresh_meeting_statuses; END;");
$scheduledStmt = $pdo->query("
        SELECT meeting_id, title, description,
          TO_CHAR(start_time, 'YYYY-MM-DD HH24:MI:SS') AS start_time,
          TO_CHAR(end_time, 'YYYY-MM-DD HH24:MI:SS') AS end_time,
          organizer_name, attendee_count
    FROM meeting_overview
    WHERE status = 'Scheduled'
    ORDER BY start_time
");
$scheduledMeetings = $scheduledStmt->fetchAll(PDO::FETCH_ASSOC);
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
      <a href="index.php" class="sidebar-logo">
        <div class="logo-icon">M</div>
            <span class="logo-text">Meet<span>Verse</span></span>
      </a>

      <span class="nav-label">Main</span>
      <a class="nav-item active" href="admin_dashboard.php">
        <span class="icon">⊞</span> Dashboard
      </a>
      <a class="nav-item" href="meeting.php">
        <span class="icon">📅</span> Meetings
      </a>
      <a class="nav-item" href="employees.php">
        <span class="icon">👥</span> Employees
      </a>
      <a class="nav-item" href="reports.php">
        <span class="icon">📋</span> Reports
      </a>

      <span class="nav-label">System</span>
      <?php $settingsPage = ($_SESSION['role']) === 'ADMIN' ? 'admin_settings.php' : 'member_settings.php'; ?>
      <a class="nav-item" href="<?= $settingsPage ?>">
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

      <?php if (count($ongoingMeetings) > 0): ?>
      <div class="table-card" style="margin-bottom:1.5rem;">
        <div class="table-header">
          <h3>🟢 Ongoing Meetings</h3>
          <span><?= count($ongoingMeetings) ?> live now</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Organizer</th>
              <th>Time</th>
              <th>Attendees</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ongoingMeetings as $m): ?>
              <tr>
                <td><?= htmlspecialchars($m['TITLE']) ?></td>
                <td><?= htmlspecialchars($m['ORGANIZER_NAME']) ?></td>
                <td><?= formatMeetingTime($m['START_TIME'], 'g:i A') ?> – <?= formatMeetingTime($m['END_TIME'], 'g:i A') ?></td>
                <td><?= (int)$m['ATTENDEE_COUNT'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php if (count($scheduledMeetings) > 0): ?>
      <div class="table-card" style="margin-bottom:1.5rem;">
        <div class="table-header">
          <h3>🔵 Scheduled Meetings</h3>
          <span><?= count($scheduledMeetings) ?> upcoming</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Organizer</th>
              <th>Time</th>
              <th>Attendees</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($scheduledMeetings as $m): ?>
              <tr>
                <td><?= htmlspecialchars($m['TITLE']) ?></td>
                <td><?= htmlspecialchars($m['ORGANIZER_NAME']) ?></td>
                <td><?= formatMeetingTime($m['START_TIME'], 'g:i A') ?> – <?= formatMeetingTime($m['END_TIME'], 'g:i A') ?></td>
                <td><?= (int)$m['ATTENDEE_COUNT'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    </main>
  </div>
</body>
</html>
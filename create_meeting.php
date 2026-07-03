<?php
session_start();
require 'db.php';

if (!isset($_SESSION['emp_id'])) {
    header('Location: login.html');
    exit();
}

$error = $_GET['error'] ?? '';
$empStmt = $pdo->query("SELECT emp_id, first_name, last_name, department FROM employees ORDER BY first_name");
$employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MeetVerse · Schedule Meeting</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Moon+Dance&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="dashboard-layout">
    <aside class="sidebar">
      <a href="index.html" class="sidebar-logo">
        <div class="logo-icon">M</div>
        <span class="logo-text">Meet<em>Verse</em></span>
      </a>
      <span class="nav-label">Main</span>
      <a class="nav-item" href="admin_dashboard.php">
        <span class="icon">⊞</span> Dashboard
      </a>
      <a class="nav-item active" href="create_meeting.php">
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
        <div>
          <h1>Schedule a Meeting</h1>
          <p>Fill in the details to select meeting date and invite attendees.</p>
        </div>
        <a href="admin_dashboard.php" class="btn-cancel">← Back</a>
      </div>

      <?php if($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="form-card">
        <form action="meeting_action.php" method="POST">
          <div class="form-group">
            <label>Meeting Title</label>
            <input type="text" name="title" placeholder="Meeting Title" required>
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea name="description" placeholder="What is this meeting about?"></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Start Date & Time</label>
              <input type="datetime-local" name="start_time" required>
            </div>
            <div class="form-group">
              <label>End Date & Time</label>
              <input type="datetime-local" name="end_time" required>
            </div>
          </div>
          <div class="form-group">
            <label>Invite Attendees</label>
            <div class="attendees-grid">
              <?php foreach($employees as $emp): ?>
                <?php if($emp['EMP_ID'] != $_SESSION['emp_id']): ?>
                  <label class="attendee-item">
                    <input type="checkbox" name="attendees[]" value="<?= $emp['EMP_ID'] ?>">
                    <?= htmlspecialchars($emp['FIRST_NAME'] . ' ' . $emp['LAST_NAME']) ?>
                    <span style="color:var(--muted); font-size:0.72rem;">(<?= htmlspecialchars($emp['DEPARTMENT']) ?>)</span>
                  </label>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-submit">Schedule Meeting</button>
            <a href="admin_dashboard.php" class="btn-cancel">Cancel</a>
          </div>
        </form>
      </div>
    </main>
  </div>
</body>
</html>
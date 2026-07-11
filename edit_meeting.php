<?php
session_start();
require 'db.php';

if (!isset($_SESSION['emp_id'])) {
    header('Location: login.html');
    exit();
}

$meetingId = (int)($_GET['id'] ?? 0);
$error = $_GET['error'] ?? '';
$dashboardUrl = ($_SESSION['role'] === 'ADMIN') ? 'admin_dashboard.php' : 'member_dashboard.php';

function formatMeetingInput($value) {
  $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $value);
  if (!$dateTime) {
    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
  }

  return $dateTime->format('Y-m-d\TH:i');
}

$mStmt = $pdo->prepare("SELECT meeting_id, title, description,
                 TO_CHAR(start_time, 'YYYY-MM-DD HH24:MI:SS') AS start_time,
                 TO_CHAR(end_time, 'YYYY-MM-DD HH24:MI:SS') AS end_time,
                 organizer_id, status
            FROM meetings WHERE meeting_id = :id");
$mStmt->execute(['id' => $meetingId]);
$meeting = $mStmt->fetch(PDO::FETCH_ASSOC);

if (!$meeting) {
  header('Location: meeting.php?error=' . urlencode('Meeting not found'));
    exit();
}
if ($meeting['ORGANIZER_ID'] != $_SESSION['emp_id']) {
  header('Location: meeting.php?error=' . urlencode('You can only edit meetings you organized'));
    exit();
}
if ($meeting['STATUS'] === 'Cancelled') {
  header('Location: meeting.php?error=' . urlencode("Cancelled meetings can't be edited"));
    exit();
}

$empStmt = $pdo->query("SELECT emp_id, first_name, last_name, department FROM employees ORDER BY first_name");
$employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

$attStmt = $pdo->prepare("SELECT emp_id FROM attendance WHERE meeting_id = :id");
$attStmt->execute(['id' => $meetingId]);
$currentAttendees = array_column($attStmt->fetchAll(PDO::FETCH_ASSOC), 'EMP_ID');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MeetVerse · Edit Meeting</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
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
      <a class="nav-item" href="<?= $dashboardUrl ?>"><span class="icon">⊞</span> Dashboard</a>
      <a class="nav-item active" href="meeting.php"><span class="icon">📅</span> Meetings</a>
      <a class="nav-item" href="employees.php"><span class="icon">👥</span> Employees</a>
      <div class="sidebar-footer">
        <div class="user-pill">
          <div class="user-avatar"><?= strtoupper(substr($_SESSION['first_name'], 0, 1)) ?></div>
          <div class="user-info">
            <p><?= htmlspecialchars($_SESSION['first_name']) ?></p>
            <span><?= $_SESSION['role'] === 'ADMIN' ? 'Admin' : 'Member' ?></span>
          </div>
        </div>
        <a href="logout.php" class="logout-btn">Sign Out</a>
      </div>
    </aside>

    <main class="main-content">
      <div class="page-header">
        <div><h1>Edit Meeting</h1><p>Update the details below.</p></div>
        <a href="meeting.php" class="btn-cancel">← Back</a>
      </div>

      <?php if($error): ?><div class="error-box"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div class="form-card">
        <form action="meeting_action.php" method="POST">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="meeting_id" value="<?= $meeting['MEETING_ID'] ?>">
          <div class="form-group">
            <label>Meeting Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($meeting['TITLE']) ?>" required>
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea name="description"><?= htmlspecialchars($meeting['DESCRIPTION']) ?></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Start Date & Time</label>
              <input type="datetime-local" name="start_time" value="<?= formatMeetingInput($meeting['START_TIME']) ?>" required>
            </div>
            <div class="form-group">
              <label>End Date & Time</label>
              <input type="datetime-local" name="end_time" value="<?= formatMeetingInput($meeting['END_TIME']) ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Attendees</label>
            <div class="attendees-grid">
              <?php foreach($employees as $emp): ?>
                <?php if($emp['EMP_ID'] != $_SESSION['emp_id']): ?>
                  <label class="attendee-item">
                    <input type="checkbox" name="attendees[]" value="<?= $emp['EMP_ID'] ?>"
                      <?= in_array($emp['EMP_ID'], $currentAttendees) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($emp['FIRST_NAME'] . ' ' . $emp['LAST_NAME']) ?>
                    <span style="color:var(--muted); font-size:0.72rem;">(<?= htmlspecialchars($emp['DEPARTMENT']) ?>)</span>
                  </label>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-submit">Save Changes</button>
            <a href="meeting.php" class="btn-cancel">Cancel</a>
          </div>
        </form>
      </div>
    </main>
  </div>
</body>
</html>
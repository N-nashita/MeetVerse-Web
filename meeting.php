<?php
session_start();
require 'db.php';

if (!isset($_SESSION['emp_id'])) {
    header('Location: login.html');
    exit();
}

$dashboardUrl = ($_SESSION['role'] === 'ADMIN') ? 'admin_dashboard.php' : 'member_dashboard.php';

function formatMeetingTime($value, $format) {
  $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $value);
  if (!$dateTime) {
    $timestamp = strtotime($value);
    return $timestamp ? date($format, $timestamp) : $value;
  }

  return $dateTime->format($format);
}

$status = $_GET['status'] ?? '';
$dept   = $_GET['department'] ?? '';
$mine   = isset($_GET['mine']) ? 'Y' : 'N';
$sort   = $_GET['sort'] ?? 'start_desc';

$meetings = [];
$queryError = '';

try {
  $pdo->exec("BEGIN refresh_meeting_statuses; END;");

  $sql = "SELECT meeting_id, title, description, department,
                 TO_CHAR(start_time, 'YYYY-MM-DD HH24:MI:SS') AS start_time,
                 TO_CHAR(end_time, 'YYYY-MM-DD HH24:MI:SS') AS end_time,
                 status, organizer_id, organizer_name, attendee_count
      FROM meeting_overview
      WHERE 1 = 1";
  $params = [];

  if ($status !== '') {
    $sql .= " AND status = :status";
    $params['status'] = $status;
  }

  if ($dept !== '') {
    $sql .= " AND department = :department";
    $params['department'] = $dept;
  }

  if ($mine === 'Y') {
    $sql .= " AND (organizer_id = :organizer_id OR meeting_id IN (SELECT meeting_id FROM attendance WHERE emp_id = :attendee_id))";
    $params['organizer_id'] = $_SESSION['emp_id'];
    $params['attendee_id'] = $_SESSION['emp_id'];
  }

  switch ($sort) {
    case 'start_asc':
      $sql .= " ORDER BY start_time ASC";
      break;
    case 'status':
      $sql .= " ORDER BY status, start_time DESC";
      break;
    case 'department':
      $sql .= " ORDER BY department, start_time DESC";
      break;
    default:
      $sql .= " ORDER BY start_time DESC";
      break;
  }

  $stmt = $pdo->prepare($sql);
  foreach ($params as $name => $value) {
    $stmt->bindValue(':' . $name, $value);
  }
  $stmt->execute();
  $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $queryError = 'Could not load meetings: ' . $e->getMessage();
}

$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

function statusClass($status) {
    switch ($status) {
        case 'Ongoing':   return 'status-ongoing';
        case 'Scheduled': return 'status-scheduled';
        case 'Completed': return 'status-completed';
        case 'Cancelled': return 'status-cancelled';
        default:          return '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MeetVerse · Meetings</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Moon+Dance&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .meeting-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1rem;
    }
    .meeting-card {
      background: var(--card-bg, #f1f0ff);
      border: 1px solid var(--border, #e5e7eb);
      border-radius: 12px;
      padding: 1.1rem 1.2rem;
    }
    .meeting-card-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 0.5rem;
    }
    .meeting-card h4 { color: var(--text, #1f2937); margin: 0 0 0.3rem 0; font-size: 1rem; }
    .meeting-card p.desc { color: var(--muted); font-size: 0.85rem; margin: 0.3rem 0 0.8rem 0; }
    .meeting-meta { font-size: 0.78rem; color: var(--muted); display: flex; flex-direction: column; gap: 0.2rem; }
    .status-pill {
      font-size: 0.7rem;
      font-weight: 600;
      padding: 0.2rem 0.6rem;
      border-radius: 999px;
      white-space: nowrap;
    }
    .status-ongoing   { background: #dcfce7; color: #15803d; }
    .status-scheduled { background: #dbeafe; color: #1d4ed8; }
    .status-completed { background: #f1f5f9; color: #475569; }
    .status-cancelled { background: #fee2e2; color: #b91c1c; }

    .filter-bar {
      display: flex;
      gap: 0.8rem;
      flex-wrap: wrap;
      align-items: center;
      margin-bottom: 1.2rem;
    }
    .filter-bar select {
      padding: 0.4rem 0.7rem;
      border-radius: 8px;
      border: 1px solid var(--border, #e5e7eb);
      font-size: 0.85rem;
      background: #fff;
    }
    .filter-bar label {
      display: flex;
      align-items: center;
      gap: 0.3rem;
      font-size: 0.85rem;
      color: var(--muted);
    }
    .card-actions {
      display: flex;
      gap: 0.5rem;
      margin-top: 0.8rem;
    }
    .card-actions form { margin: 0; }
    .btn-cancel-meeting {
      font-size: 0.78rem;
      padding: 0.3rem 0.7rem;
      background: #fee2e2;
      color: #b91c1c;
      border: none;
      border-radius: 6px;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="dashboard-layout">
    <aside class="sidebar">
      <a href="index.html" class="sidebar-logo">
        <div class="logo-icon">M</div>
        <span class="logo-text">Meet<span>Verse</span></span>
      </a>
      <span class="nav-label">Main</span>
      <a class="nav-item" href="<?= $dashboardUrl ?>">
        <span class="icon">⊞</span> Dashboard
      </a>
      <a class="nav-item active" href="meeting.php">
        <span class="icon">📅</span> Meetings
      </a>
      <a class="nav-item" href="employees.php">
        <span class="icon">👥</span> Employees
      </a>
      <span class="nav-label">Reports</span>
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
            <span><?= $_SESSION['role'] === 'ADMIN' ? 'Admin' : 'Member' ?></span>
          </div>
        </div>
        <a href="logout.php" class="logout-btn">Sign Out</a>
      </div>
    </aside>

    <main class="main-content">
      <div class="page-header">
        <div class="page-header-text">
          <h1>Meetings</h1>
          <p>All meetings you've organized or been invited to.</p>
        </div>
        <a href="create_meeting.php" class="btn-submit" style="text-decoration:none; align-self:flex-start;">+ Launch Meeting</a>
      </div>

      <?php if (isset($_GET['success'])): ?>
        <div class="success-box" style="background:#dcfce7; color:#15803d; padding:0.7rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:0.85rem;">
          <?= htmlspecialchars($_GET['success']) ?>
        </div>
      <?php endif; ?>
      <?php if (isset($_GET['error'])): ?>
        <div class="error-box"><?= htmlspecialchars($_GET['error']) ?></div>
      <?php endif; ?>
      <?php if ($queryError): ?>
        <div class="error-box"><?= htmlspecialchars($queryError) ?></div>
      <?php endif; ?>

      <form method="GET" class="filter-bar">
        <select name="status" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <?php foreach (['Scheduled','Ongoing','Completed','Cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>

        <select name="department" onchange="this.form.submit()">
          <option value="">All Departments</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="sort" onchange="this.form.submit()">
          <option value="start_desc" <?= $sort === 'start_desc' ? 'selected' : '' ?>>Newest First</option>
          <option value="start_asc" <?= $sort === 'start_asc' ? 'selected' : '' ?>>Oldest First</option>
          <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>By Status</option>
          <option value="department" <?= $sort === 'department' ? 'selected' : '' ?>>By Department</option>
        </select>

        <label>
          <input type="checkbox" name="mine" value="1" <?= $mine === 'Y' ? 'checked' : '' ?> onchange="this.form.submit()">
          My meetings only
        </label>
      </form>

      <?php if (count($meetings) > 0): ?>
        <div class="meeting-grid">
          <?php foreach ($meetings as $m): ?>
            <div class="meeting-card">
              <div class="meeting-card-top">
                <h4><?= htmlspecialchars($m['TITLE']) ?></h4>
                <span class="status-pill <?= statusClass($m['STATUS']) ?>"><?= htmlspecialchars($m['STATUS']) ?></span>
              </div>
              <p class="desc"><?= htmlspecialchars($m['DESCRIPTION']) ?></p>
              <div class="meeting-meta">
                <span>🗓 <?= formatMeetingTime($m['START_TIME'], 'M d, Y g:i A') ?> – <?= formatMeetingTime($m['END_TIME'], 'g:i A') ?></span>
                <span>👤 Organized by <?= htmlspecialchars($m['ORGANIZER_NAME']) ?></span>
                <span>👥 <?= (int)$m['ATTENDEE_COUNT'] ?> attendee<?= $m['ATTENDEE_COUNT'] == 1 ? '' : 's' ?></span>
                <?php if ($m['DEPARTMENT']): ?>
                  <span>🏷 <?= htmlspecialchars($m['DEPARTMENT']) ?></span>
                <?php endif; ?>
              </div>

              <?php if ($m['ORGANIZER_ID'] == $_SESSION['emp_id'] && $m['STATUS'] !== 'Cancelled'): ?>
                <div class="card-actions">
                  <a href="edit_meeting.php?id=<?= $m['MEETING_ID'] ?>" class="btn-cancel" style="text-decoration:none; font-size:0.78rem; padding:0.3rem 0.7rem;">Edit</a>
                  <form action="meeting_action.php" method="POST" onsubmit="return confirm('Cancel this meeting?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="meeting_id" value="<?= $m['MEETING_ID'] ?>">
                    <button type="submit" class="btn-cancel-meeting">Cancel</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="table-card">
          <p style="padding:1.5rem; color:var(--muted);">No meetings match these filters.</p>
        </div>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
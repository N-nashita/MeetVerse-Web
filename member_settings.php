<?php
session_start();
require 'db.php';

if (!isset($_SESSION['emp_id'])) { header('Location: login.html'); exit(); }

$meStmt = $pdo->prepare("SELECT first_name, last_name, email, department, role FROM employees WHERE emp_id = :id");
$meStmt->execute([':id' => $_SESSION['emp_id']]);
$me = $meStmt->fetch(PDO::FETCH_ASSOC);
$_SESSION['role'] = $me['ROLE'];

// Load notifications
$notifStmt = $pdo->prepare("SELECT * FROM (SELECT notif_id, message, is_read, created_at FROM notifications WHERE emp_id = :id ORDER BY created_at DESC) WHERE ROWNUM <= 20");
$notifStmt->execute([':id' => $_SESSION['emp_id']]);
$notifs = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
$unread = count(array_filter($notifs, fn($n) => $n['IS_READ'] == 0));

$error   = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MeetVerse · Settings</title>
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
      <span class="nav-label"> Main </span>
      <a class="nav-item" href="admin_dashboard.php">
        <span class="icon">⊞</span> Dashboard
      </a>
      <a class="nav-item" href="create_meeting.php">
        <span class="icon">📅</span> Meetings 
      </a>
      <a class="nav-item" href="employees.php">
        <span class="icon">👥</span> Employees
      </a>
      <a class="nav-item" href="reports.php">
        <span class="icon">📋</span> Reports
      </a>
      <span class="nav-label">System</span>
      <a class="nav-item active" href="member_settings.php">
        <span class="icon">⚙️</span> Settings
      </a>

      <div class="sidebar-footer">
        <div class="user-pill">
          <div class="user-avatar"><?= strtoupper(substr($_SESSION['first_name'], 0, 1)) ?></div>
          <div class="user-info">
            <p><?= htmlspecialchars($_SESSION['first_name']) ?></p>
            <span>Member</span>
          </div>
        </div>
        <a href="logout.php" class="logout-btn">Sign Out</a>
      </div>
    </aside>

    <main class="main-content">
      <div class="page-header">
        <div class="page-header-text">
          <h1>Settings</h1>
          <p>Manage your account and notifications.</p>
        </div>
      </div>

      <?php if($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if($success): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <div class="settings-grid">

        <!-- Notifications -->
        <div class="settings-card full">
          <div class="card-header">
            <div>
              <h3>Notifications <?php if($unread > 0): ?><span class="unread-badge"><?= $unread ?></span><?php endif; ?></h3>
              <p>Meeting invitations and updates</p>
            </div>
            <?php if($unread > 0): ?>
            <form method="POST" action="settings_action.php" style="margin:0">
              <input type="hidden" name="action" value="mark_all_read">
              <button type="submit" class="btn-sm">Mark all read</button>
            </form>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if(count($notifs) > 0): ?>
              <?php foreach($notifs as $n): $read = $n['IS_READ'] == 1; ?>
              <div class="notif-item">
                <div class="notif-dot <?= $read ? 'read' : '' ?>"></div>
                <div style="flex:1">
                  <div class="notif-msg <?= $read ? 'read' : '' ?>"><?= htmlspecialchars($n['MESSAGE']) ?></div>
                  <?php if(!$read): ?>
                  <div class="notif-actions">
                    <form method="POST" action="settings_action.php" style="margin:0">
                      <input type="hidden" name="action" value="mark_read">
                      <input type="hidden" name="notif_id" value="<?= $n['NOTIF_ID'] ?>">
                      <button type="submit" class="btn-sm">Mark as read</button>
                    </form>
                  </div>
                  <?php endif; ?>
                </div>
                <span class="notif-time"><?= date('M d, H:i', strtotime($n['CREATED_AT'])) ?></span>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-note">No notifications yet.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Profile info -->
        <div class="settings-card">
          <div class="card-header">
            <div>
              <h3>Update Name</h3>
              <p>Change your display name</p>
            </div>
          </div>
          <div class="card-body">
            <div class="profile-box">
              <div class="profile-avatar"><?= strtoupper(substr($me['FIRST_NAME'], 0, 1)) ?></div>
              <div class="profile-info">
                <p><?= htmlspecialchars($me['FIRST_NAME'] . ' ' . $me['LAST_NAME']) ?></p>
                <span><?= htmlspecialchars($me['EMAIL']) ?> · <?= htmlspecialchars($me['DEPARTMENT']) ?></span>
              </div>
            </div>
            <form method="POST" action="settings_action.php">
              <input type="hidden" name="action" value="change_name">
              <div class="form-row">
                <div class="form-group">
                  <label>First Name</label>
                  <input type="text" name="first_name" value="<?= htmlspecialchars($me['FIRST_NAME']) ?>" required>
                </div>
                <div class="form-group">
                  <label>Last Name</label>
                  <input type="text" name="last_name" value="<?= htmlspecialchars($me['LAST_NAME']) ?>" required>
                </div>
              </div>
              <button type="submit" class="btn-save">Save Name</button>
            </form>
          </div>
        </div>

        <!-- Change Password -->
        <div class="settings-card">
          <div class="card-header">
            <div>
              <h3>Change Password</h3>
              <p>Update your account password</p>
            </div>
          </div>
          <div class="card-body">
            <form method="POST" action="settings_action.php">
              <input type="hidden" name="action" value="change_password">
              <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="old_password" placeholder="••••••••" required>
              </div>
              <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="Min. 8 characters" required>
              </div>
              <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" placeholder="••••••••" required>
              </div>
              <button type="submit" class="btn-save">Change Password</button>
            </form>
          </div>
        </div>

      </div>
    </main>
  </div>
</body>
</html>
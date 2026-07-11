<?php
session_start();
require 'db.php';

if (!isset($_SESSION['emp_id'])) { header('Location: login.html'); exit(); }

// Load session role from DB to be safe
$meStmt = $pdo->prepare("SELECT first_name, last_name, email, department, role FROM employees WHERE emp_id = :id");
$meStmt->execute([':id' => $_SESSION['emp_id']]);
$me = $meStmt->fetch(PDO::FETCH_ASSOC);

if (!$me || $me['ROLE'] !== 'ADMIN') { header('Location: member_settings.php'); exit(); }
$_SESSION['role'] = 'ADMIN';

// All members except self
$membersStmt = $pdo->prepare("SELECT emp_id, first_name, last_name, email, department, role, created_at FROM employees WHERE emp_id != :id ORDER BY created_at DESC");
$membersStmt->execute([':id' => $_SESSION['emp_id']]);
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

// Activity log
$logStmt = $pdo->query("SELECT * FROM (SELECT l.log_id, l.action, l.details, l.created_at, e.first_name || ' ' || e.last_name AS emp_name FROM activity_log l LEFT JOIN employees e ON e.emp_id = l.emp_id ORDER BY l.created_at DESC) WHERE ROWNUM <= 20");
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

$error   = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MeetVerse · Admin Settings</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Moon+Dance&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <canvas id="canvas"></canvas>
  <div class="orb orb1"></div><div class="orb orb2"></div><div class="orb orb3"></div>
  <div class="grid-overlay"></div>

  <!-- Confirm Remove Modal -->
  <div class="modal-overlay" id="removeModal">
    <div class="modal">
      <h4>Remove Member</h4>
      <p>Are you sure you want to remove this member? This action cannot be undone.</p>
      <div class="modal-actions">
        <button class="btn-cancel-modal" onclick="closeModal()">Cancel</button>
        <form method="POST" action="settings_action.php" style="margin:0">
          <input type="hidden" name="action" value="remove_employee">
          <input type="hidden" name="target_emp_id" id="modal_emp_id">
          <button type="submit" class="btn-danger">Yes, Remove</button>
        </form>
      </div>
    </div>
  </div>

  <div class="dashboard-layout">
    <aside class="sidebar">
      <a href="index.html" class="sidebar-logo">
        <div class="logo-icon">M</div>
        <span class="logo-text">Meet<span>Verse</span></span>
      </a>
      <span class="nav-label">Main</span>
      <a class="nav-item" href="admin_dashboard.php"><span class="icon">⊞</span> Dashboard</a>
      <a class="nav-item" href="create_meeting.php"><span class="icon">📅</span> Meetings</a>
      <a class="nav-item" href="employees.php"><span class="icon">👥</span> Employees</a>
      <a class="nav-item" href="reports.php"><span class="icon">📋</span> Reports</a>
      <span class="nav-label">System</span>
      <a class="nav-item active" href="admin_settings.php"><span class="icon">⚙️</span> Settings</a>

      <div class="sidebar-footer">
        <div class="user-pill">
          <div class="user-avatar"><?= strtoupper(substr($_SESSION['first_name'], 0, 1)) ?></div>
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
        <h1>Admin Settings</h1>
        <p>Manage your account and all members.</p>
      </div>

      <?php if($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if($success): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <div class="settings-grid">

        <!-- Change Name -->
        <div class="settings-card">
          <div class="card-header">
            <h3>Update Name</h3>
            <p>Change your display name</p>
          </div>
          <div class="card-body">
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
            <h3>Change Password</h3>
            <p>Update your account password</p>
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

        <!-- Manage Members -->
        <div class="settings-card full">
          <div class="card-header">
            <h3>Manage Members</h3>
            <p>Update departments or remove members</p>
          </div>
          <div class="card-body" style="padding:0">
            <?php if(count($members) > 0): ?>
            <table class="member-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Department</th>
                  <th>Joined</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($members as $m): ?>
                <tr>
                  <td><?= htmlspecialchars($m['FIRST_NAME'] . ' ' . $m['LAST_NAME']) ?></td>
                  <td><?= htmlspecialchars($m['EMAIL']) ?></td>
                  <td>
                    <form method="POST" action="settings_action.php" class="inline-form">
                      <input type="hidden" name="action" value="update_department">
                      <input type="hidden" name="target_emp_id" value="<?= $m['EMP_ID'] ?>">
                      <input type="text" name="department" value="<?= htmlspecialchars($m['DEPARTMENT']) ?>">
                      <button type="submit" class="btn-save" style="padding:0.4rem 0.8rem;font-size:0.78rem;">Save</button>
                    </form>
                  </td>
                  <td><?= date('M d, Y', strtotime($m['CREATED_AT'])) ?></td>
                  <td>
                    <button class="btn-danger" onclick="confirmRemove(<?= $m['EMP_ID'] ?>)">Remove</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php else: ?>
              <div class="empty-note">No other members yet.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Activity Log -->
        <div class="settings-card full">
          <div class="card-header">
            <h3>Activity Log</h3>
            <p>System events recorded automatically by Oracle triggers</p>
          </div>
          <div class="card-body">
            <?php if(count($logs) > 0): ?>
              <?php foreach($logs as $log):
                $badgeClass = match($log['ACTION']) {
                  'NEW EMPLOYEE'     => 'new-emp',
                  'NEW MEETING'      => 'new-meet',
                  'MEETING CANCELLED'=> 'cancelled',
                  default            => 'new-meet'
                };
              ?>
              <div class="log-row">
                <span class="log-badge <?= $badgeClass ?>"><?= htmlspecialchars($log['ACTION']) ?></span>
                <span class="log-details"><?= htmlspecialchars($log['DETAILS']) ?></span>
                <span class="log-time"><?= date('M d, H:i', strtotime($log['CREATED_AT'])) ?></span>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-note">No activity recorded yet.</div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </main>
  </div>

  <script>
    function confirmRemove(empId) {
      document.getElementById('modal_emp_id').value = empId;
      document.getElementById('removeModal').classList.add('open');
    }
    function closeModal() {
      document.getElementById('removeModal').classList.remove('open');
    }
  </script>
</body>
</html>
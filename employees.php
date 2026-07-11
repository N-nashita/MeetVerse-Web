<?php
session_start();
require 'db.php';

if (!isset($_SESSION['emp_id'])) {
    header('Location: login.html');
    exit();
}

$conn = $pdo->getConnection();
$dept = $_GET['department'] ?? 'ALL';
$sort = $_GET['sort'] ?? 'start_desc';
$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

$empId = $_SESSION['emp_id'];
$deptVal = $dept === '' ? 'ALL' : $dept;
$sortVal = $sort;

$cursor = oci_new_cursor($conn);
$stmt   = oci_parse($conn, "BEGIN get_filtered_members(:emp_id, :dept, :sort, :cursor); END;");

oci_bind_by_name($stmt, ':emp_id', $empId);
oci_bind_by_name($stmt, ':dept',   $deptVal);
oci_bind_by_name($stmt, ':sort',   $sortVal);
oci_bind_by_name($stmt, ':cursor', $cursor, -1, OCI_B_CURSOR);

oci_execute($stmt);
oci_execute($cursor);

$employees = [];
while ($row = oci_fetch_assoc($cursor)) {
    $employees[] = $row;
}

oci_free_statement($cursor);
oci_free_statement($stmt);

$totalEmployees = count($employees);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Moon+Dance&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <aside class="sidebar">
      <a href="index.php" class="sidebar-logo">
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
      <a class="nav-item active" href="employees.php">
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
            <span><?= $_SESSION['role'] === 'ADMIN' ? 'Admin' : 'Member' ?></span>
          </div>
        </div>
        <a href="logout.php" class="logout-btn">Sign Out</a>
      </div>
    </aside>
    <main class="main-content">
      <header class="page-header">
        <div class="page-header-text">
        <h1>Employees</h1>
        <p>Overview of the all registered employees in MeetVerse.</p>
        </div>
      </header>

      <form method="GET" class="filter-bar">
        <select name="department" onchange="this.form.submit()">
          <option value="">All Departments</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="sort" onchange="this.form.submit()">
          <option value="start_desc" <?= $sort === 'start_desc' ? 'selected' : '' ?>>Newest First</option>
          <option value="start_asc" <?= $sort === 'start_asc' ? 'selected' : '' ?>>Oldest First</option>
          <option value="department" <?= $sort === 'department' ? 'selected' : '' ?>>By Department</option>
        </select>
      </form>

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
              <th>Role</th>
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
                  <td><span class="role-badge"><?= ucfirst(strtolower($emp['ROLE'])) ?></span></td>
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
</body>
</html>
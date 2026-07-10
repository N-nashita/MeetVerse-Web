<?php
session_start();
require 'db.php';

if (!isset($_SESSION['emp_id'])) {
    header('Location: login.html');
    exit();
}

$action  = $_POST['action'] ?? '';
$emp_id  = $_SESSION['emp_id'];
$role    = $_SESSION['role'] ?? 'MEMBER';
$redirect = ($role === 'ADMIN') ? 'admin_settings.php' : 'member_settings.php';

// Change name
if ($action === 'change_name') {
    $first = trim($_POST['first_name']);
    $last  = trim($_POST['last_name']);
    if (!$first || !$last) {
        header("Location: $redirect?error=Name cannot be empty");
        exit();
    }
    $stmt = $pdo->prepare("BEGIN :result := update_employee_name(:emp_id, :first, :last); END;");
    $result = '';
    $stmt->bindParam(':result',  $result, PDO::PARAM_STR, 20);
    $stmt->bindParam(':emp_id',  $emp_id);
    $stmt->bindParam(':first',   $first);
    $stmt->bindParam(':last',    $last);
    $stmt->execute();
    if ($result === 'SUCCESS') {
        $_SESSION['first_name'] = $first;
        header("Location: $redirect?success=Name updated successfully");
    } else {
        header("Location: $redirect?error=Failed to update name");
    }
    exit();
}

// Change password
if ($action === 'change_password') {
    $old     = $_POST['old_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!$old || !$new || !$confirm) {
        header("Location: $redirect?error=All password fields are required");
        exit();
    }
    if ($new !== $confirm) {
        header("Location: $redirect?error=New passwords do not match");
        exit();
    }
    if (strlen($new) < 8) {
        header("Location: $redirect?error=Password must be at least 8 characters");
        exit();
    }

    // Verify old password first in PHP
    $check = $pdo->prepare("SELECT password FROM employees WHERE emp_id = :emp_id");
    $check->execute([':emp_id' => $emp_id]);
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($old, $row['PASSWORD'])) {
        header("Location: $redirect?error=Current password is incorrect");
        exit();
    }

    $hashed = password_hash($new, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("BEGIN :result := change_password(:emp_id, :pass); END;");
    $result = '';
    $stmt->bindParam(':result', $result, PDO::PARAM_STR, 20);
    $stmt->bindParam(':emp_id', $emp_id);
    $stmt->bindParam(':pass',   $hashed);
    $stmt->execute();

    if ($result === 'SUCCESS') {
        header("Location: $redirect?success=Password changed successfully");
    } else {
        header("Location: $redirect?error=Failed to change password");
    }
    exit();
}

// Update department (admin only)
if ($action === 'update_department' && $role === 'ADMIN') {
    $target_id = (int)$_POST['target_emp_id'];
    $dept      = trim($_POST['department']);
    if (!$dept) {
        header("Location: $redirect?error=Department cannot be empty");
        exit();
    }
    $stmt = $pdo->prepare("BEGIN :result := update_employee_department(:emp_id, :dept); END;");
    $result = '';
    $stmt->bindParam(':result', $result, PDO::PARAM_STR, 20);
    $stmt->bindParam(':emp_id', $target_id);
    $stmt->bindParam(':dept',   $dept);
    $stmt->execute();

    if ($result === 'SUCCESS') {
        header("Location: $redirect?success=Department updated");
    } else {
        header("Location: $redirect?error=Failed to update department");
    }
    exit();
}

// Remove employee (admin only)
if ($action === 'remove_employee' && $role === 'ADMIN') {
    $target_id = (int)$_POST['target_emp_id'];
    if ($target_id === $emp_id) {
        header("Location: $redirect?error=You cannot remove yourself");
        exit();
    }
    $stmt = $pdo->prepare("BEGIN :result := remove_employee(:emp_id); END;");
    $result = '';
    $stmt->bindParam(':result', $result, PDO::PARAM_STR, 20);
    $stmt->bindParam(':emp_id', $target_id);
    $stmt->execute();

    if ($result === 'SUCCESS') {
        header("Location: $redirect?success=Member removed successfully");
    } else {
        header("Location: $redirect?error=Failed to remove member");
    }
    exit();
}

// Mark notification as read (member)
if ($action === 'mark_read') {
    $notif_id = (int)$_POST['notif_id'];
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notif_id = :id AND emp_id = :emp_id")
        ->execute([':id' => $notif_id, ':emp_id' => $emp_id]);
    header("Location: $redirect?success=Notification marked as read");
    exit();
}

// Mark all notifications as read
if ($action === 'mark_all_read') {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE emp_id = :emp_id")
        ->execute([':emp_id' => $emp_id]);
    header("Location: $redirect?success=All notifications marked as read");
    exit();
}

header("Location: $redirect");
exit();
?>
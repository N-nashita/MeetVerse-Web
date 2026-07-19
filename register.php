<?php
session_start();
require 'db.php';

if($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: register.html');
    exit();
}

$firstname  = trim($_POST['first_name']);
$lastname   = trim($_POST['last_name']);
$email      = strtolower(trim($_POST['email']));
$department = trim($_POST['department']);
$password   = $_POST['password'];
$confirm    = $_POST['confirm_password'];
$hashed     = password_hash($password, PASSWORD_DEFAULT);

if(!$firstname || !$lastname || !$email || !$password) {
    header('Location: register.html?error=Please fill in all fields');
    exit();
}

if($password !== $confirm) {
    header('Location: register.html?error=Passwords do not match');
    exit();
}

if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: register.html?error=Invalid email format');
    exit();
}

if(strlen($password) < 8) {
    header('Location: register.html?error=Password must be at least 8 characters long');
    exit();
}

// Check duplicate email
$check = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE LOWER(TRIM(email)) = :email");
$check->execute([':email' => $email]);
$count = $check->fetchColumn();

if($count > 0) {
    header('Location: register.html?error=Email already registered');
    exit();
}

$countStmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE UPPER(role) = 'ADMIN'");
$existingAdminCount = (int)$countStmt->fetchColumn();
$role = ($existingAdminCount < 2) ? 'ADMIN' : 'MEMBER';

// Insert employee
$stmt = $pdo->prepare("INSERT INTO employees (emp_id, first_name, last_name, email, department, password, role)
    VALUES (emp_seq.NEXTVAL, :first_name, :last_name, :email, :department, :password, :role)");

try {
    $stmt->execute([
        ':first_name' => $firstname,
        ':last_name'  => $lastname,
        ':email'      => $email,
        ':department' => $department,
        ':password'   => $hashed,
        ':role'       => $role
    ]);
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'ORA-00001') !== false) {
        header('Location: register.html?error=Account could not be created. Please try again.');
        exit();
    }

    throw $e;
}

// emp_seq.NEXTVAL was used in the INSERT, so CURRVAL returns the new id reliably.
$newEmpIdStmt = $pdo->query("SELECT emp_seq.CURRVAL FROM dual");
$newEmpId = $newEmpIdStmt->fetchColumn();

$_SESSION['emp_id']     = $newEmpId;
$_SESSION['first_name'] = $firstname;
$_SESSION['role']       = $role;

if($role === 'ADMIN') {
    header('Location: admin_dashboard.php');
} else {
    header('Location: member_dashboard.php');
}
exit();
?>
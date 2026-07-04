<?php
session_start();
require 'db.php';

if($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: register.html');
    exit();
}

$firstname = trim($_POST['first_name']);
$lastname = trim($_POST['last_name']);
$email = trim($_POST['email']);
$department = trim($_POST['department']);
$password = $_POST['password'];
$confirm = $_POST['confirm_password'];
$hashed = password_hash($password, PASSWORD_DEFAULT);

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

$check = $pdo->prepare("SELECT * FROM employees WHERE email = :email");
$check->execute(['email' => $email]);
if($check->rowCount() > 0) {
    header('Location: register.html?error=Email already registered');
    exit();
}

// The first two people to ever register automatically become admins,
// everyone after that is a regular member.
$countStmt = $pdo->query("SELECT COUNT(*) FROM employees");
$existingCount = $countStmt->fetchColumn();
$role = ($existingCount < 2) ? 'ADMIN' : 'MEMBER';

$stmt = $pdo->prepare("INSERT INTO employees (emp_id, first_name, last_name, email, department, password, role)
    VALUES (emp_seq.NEXTVAL, :first_name, :last_name, :email, :department, :password, :role)");

$stmt->execute([
    'first_name' => $firstname,
    'last_name' => $lastname,
    'email' => $email,
    'department' => $department,
    'password' => $hashed,
    'role' => $role
]);

$newEmpStmt = $pdo->prepare("SELECT emp_id FROM employees WHERE email = :email");
$newEmpStmt->execute(['email' => $email]);
$newEmp = $newEmpStmt->fetch(PDO::FETCH_ASSOC);

$_SESSION['emp_id'] = $newEmp['EMP_ID'];
$_SESSION['first_name'] = $firstname;
$_SESSION['role'] = $role;

if($role === 'ADMIN') {
    header('Location: admin_dashboard.php');
} else {
    header('Location: member_dashboard.php');
}
exit();
?>
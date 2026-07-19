<?php
session_start();
require 'db.php';

if($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: login.html');
    exit();
}

$email = trim($_POST['email']);
$password = $_POST['password'];

if(!$email || !$password) {
    header('Location: login.html?error=Please fill in all fields');
    exit();
}

$stmt = $pdo->prepare("SELECT EMP_ID, FIRST_NAME, ROLE, PASSWORD FROM employees WHERE LOWER(TRIM(email)) = :email");
$stmt->execute(['email' => strtolower(trim($email))]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if($user && password_verify($password, $user['PASSWORD'])) {
    $role = strtoupper(trim((string)($user['ROLE'] ?? '')));
    if ($role !== 'ADMIN' && $role !== 'MEMBER') {
        $role = 'MEMBER';
    }

    $_SESSION['emp_id'] = $user['EMP_ID'];
    $_SESSION['first_name'] = $user['FIRST_NAME'];
    $_SESSION['role'] = $role;
    if ($_SESSION['role'] === 'ADMIN') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: member_dashboard.php');
    }
    exit();
} else {
    header('Location: login.html?error=Invalid email or password');
    exit();
}
?>
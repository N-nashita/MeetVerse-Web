<?php
try {
    $pdo = new PDO("oci:dbname=//localhost:1521/XE;charset=AL32UTF8", "admin", "2022");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $roleColumnStmt = $pdo->query("SELECT COUNT(*) FROM user_tab_columns WHERE table_name = 'EMPLOYEES' AND column_name = 'ROLE'");
    if ((int)$roleColumnStmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE employees ADD role VARCHAR2(20)");
        $pdo->exec("UPDATE employees SET role = CASE WHEN emp_id IN (SELECT emp_id FROM (SELECT emp_id FROM employees ORDER BY emp_id) WHERE ROWNUM <= 2) THEN 'ADMIN' ELSE 'MEMBER' END");
        $pdo->exec("ALTER TABLE employees MODIFY role DEFAULT 'MEMBER' NOT NULL");
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
<?php
try {
    $pdo = new PDO("oci:dbname=//localhost:1521/XE;charset=AL32UTF8", "admin", "2022");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
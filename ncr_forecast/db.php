<?php
$host = "localhost";
$dbname = "ncr_forecast";
$user = "root";   // default XAMPP user
$pass = "";       // leave blank unless you set a password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

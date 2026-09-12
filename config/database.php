<?php
$host = 'localhost';

if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
    // Local environment
    $dbname = 'vdownloader';
    $username = 'root';
    $password = '';
} else {
    // Live environment
    $dbname = 'u498336619_vdownloader';
    $username = 'u498336619_vdownloader';
    $password = 'Vdownloader@123';
}

$pdo = null;
$dbError = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $pdo = null;
    $dbError = $e->getMessage();
}
?>

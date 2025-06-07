<?php
require_once 'auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$status  = isset($_GET['status']) ? $_GET['status'] : '';
if ($orderId && $status) {
    $stmt = $conn->prepare('UPDATE orders SET order_status=? WHERE id=?');
    if ($stmt) {
        $stmt->bind_param('si', $status, $orderId);
        $stmt->execute();
        $stmt->close();
    }
}
header('Location: orders.php');
exit();
?>

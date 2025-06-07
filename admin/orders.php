<?php
require_once 'auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$orders = [];
$result = $conn->query('SELECT id, restaurant_id, customer_name, customer_car_type, total_amount, order_status, created_at FROM orders ORDER BY id DESC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $result->close();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>الطلبات</title>
    <style>
        body {font-family: Arial, sans-serif;background-color:#f8f8f8;}
        .container {max-width:1000px;margin:40px auto;background-color:#fff;padding:20px;border-radius:8px;box-shadow:0 0 10px rgba(0,0,0,0.1);}
        table {width:100%;border-collapse: collapse;}
        th, td {padding:8px;border-bottom:1px solid #ddd;text-align:center;}
        th {background-color:#f1f1f1;}
    </style>
</head>
<body>
<div class="container">
    <h1>قائمة الطلبات</h1>
    <p><a href="index.php">العودة للوحة التحكم</a></p>
    <table>
        <thead>
            <tr>
                <th>رقم الطلب</th>
                <th>رقم المطعم</th>
                <th>اسم الزبون</th>
                <th>السيارة</th>
                <th>الإجمالي</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="6">لا توجد طلبات</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $ord): ?>
                    <tr>
                        <td><?php echo $ord['id']; ?></td>
                        <td><?php echo $ord['restaurant_id']; ?></td>
                        <td><?php echo htmlspecialchars($ord['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($ord['customer_car_type']); ?></td>
                        <td><?php echo number_format($ord['total_amount'], 2); ?> ر.س</td>
                        <td><?php echo htmlspecialchars($ord['order_status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>

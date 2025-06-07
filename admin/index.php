<?php
require_once 'auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة التحكم</title>
    <style>
        body {font-family: Arial, sans-serif;background-color:#f8f8f8;}
        .container {max-width:800px;margin:40px auto;background-color:#fff;padding:20px;border-radius:8px;box-shadow:0 0 10px rgba(0,0,0,0.1);}
        h1 {text-align:center;margin-bottom:20px;}
        .nav {text-align:center;margin-bottom:20px;}
        .nav a {margin:0 10px;color:#007bff;text-decoration:none;}
    </style>
</head>
<body>
    <div class="container">
        <h1>لوحة التحكم</h1>
        <div class="nav">
            <a href="orders.php">الطلبات</a> |
            <a href="logout.php">تسجيل الخروج</a>
        </div>
        <p>مرحباً بك في لوحة التحكم. اختر من القائمة أعلاه لإدارة الموقع.</p>
    </div>
</body>
</html>

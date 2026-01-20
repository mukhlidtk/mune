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
        :root {
            color-scheme: light;
            --bg: #f3f4f6;
            --surface: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --border: #e5e7eb;
            --shadow: 0 18px 40px rgba(15, 23, 42, 0.1);
            --radius: 18px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: "Tajawal", "Cairo", "Segoe UI", Arial, sans-serif;
            background: #f9fafb;
            margin: 0;
            color: var(--text);
        }
        .container {
            max-width: 900px;
            margin: 60px auto;
            background-color: var(--surface);
            padding: 32px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        h1 {
            text-align: center;
            margin-bottom: 16px;
            font-size: 1.8rem;
        }
        .nav {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .nav a {
            padding: 10px 18px;
            color: var(--primary);
            text-decoration: none;
            border-radius: 999px;
            border: 1px solid #c7d2fe;
            background: #eef2ff;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .nav a:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        .welcome-card {
            background: linear-gradient(135deg, #eff6ff, #ffffff);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.7;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>لوحة التحكم</h1>
        <div class="nav">
            <a href="orders.php">الطلبات</a>
            <a href="logout.php">تسجيل الخروج</a>
        </div>
        <p class="welcome-card">مرحباً بك في لوحة التحكم. اختر من القائمة أعلاه لإدارة الموقع.</p>
    </div>
</body>
</html>

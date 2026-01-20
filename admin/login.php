<?php
session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $adminUser = getenv('ADMIN_USER') ?: 'admin';
    $adminPass = getenv('ADMIN_PASS') ?: 'admin123';

    if ($username === $adminUser && $password === $adminPass) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit();
    } else {
        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول</title>
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
            --shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
            --radius: 18px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: "Tajawal", "Cairo", "Segoe UI", Arial, sans-serif;
            background: radial-gradient(circle at top, #eef2ff, #f9fafb 55%, #f3f4f6 100%);
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text);
        }
        .login-container {
            width: min(420px, 92vw);
            background-color: var(--surface);
            padding: 32px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .login-container h1 {
            text-align: center;
            margin-bottom: 8px;
            font-size: 1.6rem;
        }
        .login-container p {
            text-align: center;
            margin-top: 0;
            color: var(--muted);
        }
        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            margin-bottom: 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 0.98rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .login-container input[type="text"]:focus,
        .login-container input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .login-container input[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: #fff;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 700;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        .login-container input[type="submit"]:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }
        .error {
            color: #dc2626;
            text-align: center;
            margin-bottom: 12px;
            background: #fee2e2;
            border: 1px solid #fecaca;
            padding: 10px;
            border-radius: 12px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>تسجيل الدخول</h1>
        <p>الرجاء إدخال بيانات المسؤول للمتابعة.</p>
        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="text" name="username" placeholder="اسم المستخدم" required>
            <input type="password" name="password" placeholder="كلمة المرور" required>
            <input type="submit" value="دخول">
        </form>
    </div>
</body>
</html>

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

    $config = include __DIR__ . '/../config/admin.php';
    $adminUser = $config['username'] ?? null;
    $adminHash = $config['password_hash'] ?? null;

    if (!$adminUser || !$adminHash) {
        $error = 'لم يتم ضبط بيانات تسجيل الدخول.';
    } elseif ($username === $adminUser && password_verify($password, $adminHash)) {
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
        body {font-family: Arial, sans-serif; background-color:#f8f8f8;}
        .login-container {max-width:400px;margin:80px auto;background-color:#fff;padding:30px;border-radius:8px;box-shadow:0 0 10px rgba(0,0,0,0.1);}
        .login-container h1 {text-align:center;margin-bottom:20px;}
        .login-container input[type="text"], .login-container input[type="password"] {width:100%;padding:10px;margin-bottom:15px;border:1px solid #ccc;border-radius:4px;}
        .login-container input[type="submit"] {width:100%;padding:10px;background-color:#007bff;color:#fff;border:none;border-radius:4px;cursor:pointer;}
        .error {color:red;text-align:center;margin-bottom:15px;}
    </style>
</head>
<body>
    <div class="login-container">
        <h1>تسجيل الدخول</h1>
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

<?php
// الملف: public_html/m/login_admin.php
session_start();

// إذا كان المدير مسجلاً دخوله بالفعل، نوجهه إلى لوحة تحكم المدير
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard_admin.php"); // سننشئ هذا الملف لاحقًا
    exit();
}

require_once 'config/config.php'; // ملف الاتصال بقاعدة البيانات

$username = ""; // لتذكر اسم المستخدم في النموذج عند الخطأ
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username)) {
        $errors[] = "اسم المستخدم مطلوب.";
    }
    if (empty($password)) {
        $errors[] = "كلمة المرور مطلوبة.";
    }

    if (empty($errors)) {
        $sql = "SELECT id, username, password_hash FROM admins WHERE username = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                if (password_verify($password, $admin['password_hash'])) {
                    // كلمة المرور صحيحة، قم بتسجيل الدخول
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];

                    header("Location: dashboard_admin.php"); // سننشئ هذا الملف لاحقًا
                    exit();
                } else {
                    $errors[] = "اسم المستخدم أو كلمة المرور غير صحيحة.";
                }
            } else {
                $errors[] = "اسم المستخدم أو كلمة المرور غير صحيحة.";
            }
            $stmt->close();
        } else {
            $errors[] = "خطأ في النظام عند محاولة تسجيل الدخول. يرجى المحاولة لاحقًا.";
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول المدير</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { background-color: #fff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { text-align: center; color: #1c1e21; margin-bottom: 25px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #4b4f56; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px; margin-bottom: 18px; border: 1px solid #dddfe2; border-radius: 6px; box-sizing: border-box; font-size: 16px; }
        input[type="submit"] { background-color: #dc3545; color: white; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 17px; font-weight: 600; width: 100%; transition: background-color 0.2s ease-in-out; }
        input[type="submit"]:hover { background-color: #c82333; }
        .message { padding: 12px; margin-bottom: 20px; border-radius: 6px; text-align: right; font-size: 15px; }
        .message.error { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .message.error ul { list-style-type: none; padding-right: 0; margin-bottom: 0;}
        .message.error ul li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>لوحة تحكم المدير - تسجيل الدخول</h1>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <ul>
                    <?php foreach ($errors as $error_item): ?>
                        <li><?php echo htmlspecialchars($error_item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div>
                <label for="username">اسم المستخدم:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div>
                <label for="password">كلمة المرور:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <input type="submit" value="تسجيل الدخول">
            </div>
        </form>
    </div>
</body>
</html>
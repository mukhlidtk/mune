<?php
// الملف: public_html/m/login_cafe.php

// **مهم جدًا**: يجب استدعاء session_start() في بداية كل ملف يستخدم الجلسات
session_start();

// إذا كان المستخدم مسجلاً دخوله بالفعل، نوجهه إلى لوحة التحكم
if (isset($_SESSION['cafe_owner_id'])) {
    header("Location: dashboard_cafe.php");
    exit();
}

require_once 'config/config.php'; // ملف الاتصال بقاعدة البيانات

$email = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // التحقق الأساسي من المدخلات
    if (empty($email)) {
        $errors[] = "البريد الإلكتروني مطلوب.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "صيغة البريد الإلكتروني غير صحيحة.";
    }
    if (empty($password)) {
        $errors[] = "كلمة المرور مطلوبة.";
    }

    if (empty($errors)) {
        // البحث عن المستخدم في قاعدة البيانات
        $sql = "SELECT id, cafe_name, password_hash, status FROM cafe_owners WHERE email = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // التحقق من كلمة المرور ومن حالة الحساب
                if (password_verify($password, $user['password_hash'])) {
                    // كلمة المرور صحيحة
                    if ($user['status'] === 'active') {
                        // الحساب نشط، قم بتسجيل الدخول
                        $_SESSION['cafe_owner_id'] = $user['id'];
                        $_SESSION['cafe_name'] = $user['cafe_name'];
                        // (يمكنك إضافة المزيد من بيانات المستخدم للجلسة إذا احتجت)

                        // توجيه إلى لوحة التحكم
                        header("Location: dashboard_cafe.php");
                        exit();
                    } elseif ($user['status'] === 'pending_approval') {
                        $errors[] = "حسابك قيد المراجعة حاليًا. يرجى الانتظار حتى يتم تفعيله من قبل الإدارة.";
                    } elseif ($user['status'] === 'suspended') {
                        $errors[] = "حسابك معلق. يرجى التواصل مع الإدارة.";
                    } else {
                         $errors[] = "حالة حسابك غير معروفة أو غير مسموح لها بالدخول.";
                    }
                } else {
                    // كلمة المرور خاطئة
                    $errors[] = "البريد الإلكتروني أو كلمة المرور غير صحيحة.";
                }
            } else {
                // المستخدم غير موجود
                $errors[] = "البريد الإلكتروني أو كلمة المرور غير صحيحة.";
            }
            $stmt->close();
        } else {
            $errors[] = "خطأ في النظام. يرجى المحاولة لاحقًا.";
            // يمكنك تسجيل $conn->error هنا إذا أردت
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
    <title>تسجيل دخول صاحب الكافيه</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { background-color: #fff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { text-align: center; color: #1c1e21; margin-bottom: 25px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #4b4f56; }
        input[type="email"], input[type="password"] { width: 100%; padding: 12px; margin-bottom: 18px; border: 1px solid #dddfe2; border-radius: 6px; box-sizing: border-box; font-size: 16px; }
        input[type="submit"] { background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 17px; font-weight: 600; width: 100%; transition: background-color 0.2s ease-in-out; }
        input[type="submit"]:hover { background-color: #0056b3; }
        .message { padding: 12px; margin-bottom: 20px; border-radius: 6px; text-align: right; font-size: 15px; }
        .message.error { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .message.error ul { list-style-type: none; padding-right: 0; margin-bottom: 0;}
        .message.error ul li { margin-bottom: 5px; }
        .register-link { text-align: center; margin-top: 20px; }
        .register-link a { color: #007bff; text-decoration: none; font-weight: 600; }
        .register-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>تسجيل الدخول</h1>

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
                <label for="email">البريد الإلكتروني:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            <div>
                <label for="password">كلمة المرور:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <input type="submit" value="تسجيل الدخول">
            </div>
        </form>
        <div class="register-link">
            ليس لديك حساب؟ <a href="register_cafe.php">سجل الآن</a>
        </div>
    </div>
</body>
</html>
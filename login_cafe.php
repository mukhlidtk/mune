<?php
// ابدأ الجلسة في بداية الملف دائماً
session_start();

// معلومات الاتصال بقاعدة البيانات
$servername = "localhost";
$username = "root";
$db_password = "";
$dbname = "mune_db";

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $db_password, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

// التحقق من أن الطلب هو POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // استخدام الاستعلامات المعدة لجلب المستخدم بناءً على البريد الإلكتروني
    $stmt = $conn->prepare("SELECT id, cafe_name, password FROM cafes WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // التحقق من وجود مستخدم بهذا البريد الإلكتروني
    if ($result->num_rows == 1) {
        $cafe = $result->fetch_assoc();

        // **الخطوة الأهم: التحقق من كلمة المرور المشفرة**
        if (password_verify($password, $cafe['password'])) {
            // كلمة المرور صحيحة، قم بإنشاء الجلسة
            $_SESSION['cafe_id'] = $cafe['id'];
            $_SESSION['cafe_name'] = $cafe['cafe_name'];
            $_SESSION['loggedin_cafe'] = true; // علامة للتحقق من تسجيل الدخول في الصفحات الأخرى

            // توجيه المستخدم إلى لوحة التحكم الخاصة به
            header("Location: dashboard_cafe.php");
            exit();
        } else {
            // كلمة المرور غير صحيحة
            echo "خطأ في البريد الإلكتروني أو كلمة المرور.";
        }
    } else {
        // لا يوجد مستخدم بهذا البريد الإلكتروني
        echo "خطأ في البريد الإلكتروني أو كلمة المرور.";
    }
    $stmt->close();
}
$conn->close();
?>

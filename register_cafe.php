<?php
// ابدأ الجلسة
session_start();

// معلومات الاتصال بقاعدة البيانات (يفضل وضعها في ملف config.php منفصل لاحقاً)
$servername = "localhost";
$username = "root"; // اسم المستخدم الافتراضي
$db_password = ""; // كلمة المرور الافتراضية
$dbname = "mune_db"; // تأكد من أن هذا هو اسم قاعدة بياناتك

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $db_password, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

// التحقق من أن الطلب هو POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cafe_name = $_POST['cafe_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $phone = $_POST['phone'];
    $location = $_POST['location'];

    // 1. تشفير كلمة المرور (الأهم)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 2. استخدام الاستعلامات المعدة (Prepared Statements)
    // الخطوة الأولى: التحقق من عدم وجود البريد الإلكتروني مسبقاً
    $stmt = $conn->prepare("SELECT id FROM cafes WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "هذا البريد الإلكتروني مسجل بالفعل.";
    } else {
        // الخطوة الثانية: إدخال المستخدم الجديد بأمان
        $stmt_insert = $conn->prepare("INSERT INTO cafes (cafe_name, email, password, phone, location) VALUES (?, ?, ?, ?, ?)");
        // "sssss" تعني أن كل المتغيرات الخمسة هي من نوع String
        $stmt_insert->bind_param("sssss", $cafe_name, $email, $hashed_password, $phone, $location);

        if ($stmt_insert->execute()) {
            echo "تم التسجيل بنجاح! يمكنك الآن تسجيل الدخول.";
            // يمكنك توجيهه لصفحة تسجيل الدخول
            // header("Location: login_cafe.php");
            // exit();
        } else {
            echo "خطأ: " . $stmt_insert->error;
        }
        $stmt_insert->close();
    }
    $stmt->close();
}
$conn->close();
?>

<?php
// الملف: public_html/m/logout_cafe.php
session_start(); // بدء أو استئناف الجلسة

// 1. إلغاء تعيين جميع متغيرات الجلسة
$_SESSION = array(); // أو session_unset();

// 2. تدمير الجلسة
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 3. توجيه المستخدم إلى صفحة تسجيل الدخول
header("Location: login_cafe.php");
exit();
?>
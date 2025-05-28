<?php
// الملف: public_html/m/logout_admin.php
session_start(); // بدء أو استئناف الجلسة

// 1. إلغاء تعيين جميع متغيرات الجلسة الخاصة بالمدير
// من الأفضل إلغاء تعيين متغيرات معينة بدلاً من $_SESSION = array(); إذا كان هناك جلسات أخرى لا تريد المساس بها
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
// إذا كان لديك المزيد من متغيرات الجلسة الخاصة بالمدير، قم بإلغاء تعيينها هنا

// 2. تدمير الجلسة إذا لم يعد هناك أي بيانات هامة فيها
// إذا كنت تستخدم نفس الجلسة لعدة أنواع من المستخدمين، قد لا ترغب في تدميرها بالكامل هنا
// ولكن بما أننا نميز بين جلسة المدير وجلسة صاحب الكافيه، فمن الآمن تدميرها إذا أردنا.
// للتبسيط الآن، سنقوم بتدميرها.

// للتأكد من إزالة جميع بيانات الجلسة فعليًا
$_SESSION = array(); 

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 3. توجيه المدير إلى صفحة تسجيل الدخول الخاصة بالمدير
header("Location: login_admin.php");
exit();
?>
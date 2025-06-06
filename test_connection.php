<?php

// تضمين ملف إعدادات الاتصال بقاعدة البيانات
// المسار يعتمد على مكان وجود test_connection.php بالنسبة لـ config/database.php
require_once 'config/database.php';

echo "<h1>اختبار الاتصال بقاعدة البيانات</h1>";

if ($conn) {
    echo "<p style='color:green;'>نجح الاتصال بقاعدة البيانات!</p>";
    echo "<p>معلومات الخادم: " . $conn->host_info . "</p>";
    echo "<p>إصدار MySQL: " . $conn->server_info . "</p>";
    echo "<p>قاعدة البيانات المحددة: " . DB_NAME . "</p>"; // DB_NAME تم تعريفه في database.php

    // (اختياري) يمكنك محاولة إغلاق الاتصال هنا، ولكن عادة ما يتم إغلاقه تلقائيًا عند انتهاء السكربت
    // $conn->close();

} else {
    // هذه الحالة يجب ألا تحدث إذا كان die() يعمل في database.php عند فشل الاتصال
    echo "<p style='color:red;'>فشل الاتصال بقاعدة البيانات. راجع ملف الخطأ error_log على الخادم.</p>";
}

phpinfo(); // لعرض معلومات PHP والإعدادات، مفيدة للمراجعة الأولية

?>
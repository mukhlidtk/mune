<?php
// الملف: /public_html/m/index.php
// هذا مثال بسيط لصفحة رئيسية للمجلد /m/
// لاحقًا يمكن تطوير هذه الصفحة لتعرض مثلاً قائمة بالكافيهات النشطة أو شعار الموقع
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة قوائم الكافيهات</title>
    <style>
        body { font-family: 'Tajawal', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 90vh; background-color: #f0f2f5; text-align: center; }
        .container { padding: 30px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h1 { color: #4A3B31; }
        p { font-size: 1.1em; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>مرحبًا بك في نظام إدارة قوائم الكافيهات</h1>
        <p>هذه هي الصفحة الرئيسية المؤقتة. يمكنك تصفح الكافيهات من خلال الروابط المباشرة لقوائمها إذا كنت تعرف المعرّف القصير (slug) الخاص بها.</p>
        <p><a href="login_cafe.php">تسجيل دخول صاحب كافيه</a> | <a href="login_admin.php">تسجيل دخول المدير</a></p>
        </div>
</body>
</html>

<?php
// الملف: public_html/m/register_cafe.php

// تفعيل الجلسات إذا كنا سنستخدمها لاحقًا لعرض الرسائل أو تذكر المستخدم (اختياري الآن)
// session_start();

// تضمين ملف الاتصال بقاعدة البيانات
require_once 'config/config.php'; // هذا هو الملف الذي أنشأناه سابقًا

// تعريف متغيرات لتخزين مدخلات المستخدم ورسائل الخطأ/النجاح
$cafe_name = "";
$owner_name = "";
$email = "";
// (لن نعيد تعبئة حقول كلمة المرور لأسباب أمنية)
$phone_number = "";
$address = "";

$errors = []; // مصفوفة لتخزين رسائل الخطأ
$success_message = ""; // لتخزين رسالة النجاح

// التحقق مما إذا كان قد تم إرسال النموذج باستخدام طريقة POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. استقبال البيانات من النموذج وتنظيفها بشكل مبدئي
    // trim() تزيل المسافات البيضاء الزائدة من بداية ونهاية النص
    $cafe_name = trim($_POST['cafe_name']);
    $owner_name = trim($_POST['owner_name']); // اختياري، لا مشكلة إذا كان فارغًا
    $email = trim($_POST['email']);
    $password = $_POST['password']; // لا نستخدم trim() على كلمة المرور مباشرة
    $confirm_password = $_POST['confirm_password'];
    $phone_number = trim($_POST['phone_number']); // اختياري
    $address = trim($_POST['address']); // اختياري

    // 2. التحقق من صحة البيانات (Validation)
    if (empty($cafe_name)) {
        $errors[] = "حقل 'اسم الكافيه' مطلوب.";
    }
    if (empty($email)) {
        $errors[] = "حقل 'البريد الإلكتروني' مطلوب.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // filter_var مع FILTER_VALIDATE_EMAIL يتحقق من أن البريد الإلكتروني يبدو صحيحًا
        $errors[] = "صيغة البريد الإلكتروني المدخلة غير صحيحة.";
    }

    if (empty($password)) {
        $errors[] = "حقل 'كلمة المرور' مطلوب.";
    }
    // يمكن إضافة شرط هنا للتحقق من طول كلمة المرور، مثلاً:
    // elseif (strlen($password) < 8) {
    //    $errors[] = "يجب أن تكون كلمة المرور 8 أحرف على الأقل.";
    // }

    if (empty($confirm_password)) {
        $errors[] = "حقل 'تأكيد كلمة المرور' مطلوب.";
    }
    
    if (!empty($password) && !empty($confirm_password) && $password !== $confirm_password) {
        $errors[] = "كلمتا المرور غير متطابقتين.";
    }

    // 3. إذا لم تكن هناك أخطاء في التحقق، نستمر في العمليات على قاعدة البيانات
    if (empty($errors)) {
        
        // 3.1 التحقق مما إذا كان البريد الإلكتروني مسجلاً مسبقًا
        // نستخدم الاستعلامات المُعدَّة (Prepared Statements) لمزيد من الأمان
        $sql_check_email = "SELECT id FROM cafe_owners WHERE email = ?";
        $stmt_check_email = $conn->prepare($sql_check_email);
        
        if ($stmt_check_email) {
            $stmt_check_email->bind_param("s", $email); // "s" تعني أن المتغير هو نص (string)
            $stmt_check_email->execute();
            $stmt_check_email->store_result(); // ضروري لمعرفة عدد الصفوف

            if ($stmt_check_email->num_rows > 0) {
                $errors[] = "هذا البريد الإلكتروني مسجل بالفعل في النظام. يرجى استخدام بريد آخر.";
            }
            $stmt_check_email->close(); // إغلاق الاستعلام المُعد
        } else {
            // خطأ في إعداد الاستعلام نفسه (نادر، قد يكون بسبب مشكلة في الاتصال أو خطأ SQL)
            $errors[] = "خطأ في النظام (فحص البريد). يرجى المحاولة لاحقًا.";
            // يمكنك تسجيل $conn->error في ملف سجلات لمراجعته لاحقًا
        }

        // 3.2 إذا كان البريد جديدًا ولم تظهر أخطاء إضافية
        if (empty($errors)) {
            // تجزئة كلمة المرور (Hashing) باستخدام خوارزمية آمنة
            $password_hashed = password_hash($password, PASSWORD_DEFAULT);

            // 3.3 إدخال بيانات المستخدم الجديد في جدول cafe_owners
            // الحالة الافتراضية للمستخدم الجديد ستكون 'pending_approval' كما في تصميم قاعدة البيانات
            $sql_insert_user = "INSERT INTO cafe_owners (cafe_name, owner_name, email, password_hash, phone_number, address, status) 
                                VALUES (?, ?, ?, ?, ?, ?, 'pending_approval')";
            
            $stmt_insert_user = $conn->prepare($sql_insert_user);
            if ($stmt_insert_user) {
                $stmt_insert_user->bind_param("ssssss", 
                    $cafe_name, 
                    $owner_name, 
                    $email, 
                    $password_hashed, 
                    $phone_number, 
                    $address
                );

                if ($stmt_insert_user->execute()) {
                    $success_message = "تم تسجيل حساب الكافيه بنجاح! سيتم مراجعته من قبل الإدارة قريبًا.";
                    // مسح الحقول بعد التسجيل الناجح (باستثناء كلمة المرور)
                    $_POST = array(); // لمسح بيانات النموذج بعد الإرسال الناجح لمنع إعادة الإرسال
                    $cafe_name = $owner_name = $email = $phone_number = $address = ""; 
                } else {
                    $errors[] = "حدث خطأ أثناء تسجيل الحساب. يرجى المحاولة مرة أخرى.";
                    // يمكنك تسجيل $stmt_insert_user->error في ملف سجلات لمراجعته لاحقًا
                }
                $stmt_insert_user->close(); // إغلاق الاستعلام المُعد
            } else {
                 $errors[] = "خطأ في النظام (إدخال البيانات). يرجى المحاولة لاحقًا.";
                 // يمكنك تسجيل $conn->error في ملف سجلات لمراجعته لاحقًا
            }
        }
    }
    // إغلاق الاتصال بقاعدة البيانات بعد الانتهاء من كل العمليات المتعلقة بالطلب الحالي
    // من المهم إغلاقه هنا لضمان عدم تركه مفتوحًا بلا داعٍ
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل كافيه جديد</title>
    <style>
        /* تنسيقات CSS أساسية لتحسين مظهر الصفحة */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background-color: #f0f2f5; 
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container { 
            background-color: #fff; 
            padding: 25px 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            width: 100%;
            max-width: 500px;
        }
        h1 { 
            text-align: center; 
            color: #1c1e21; 
            margin-bottom: 25px;
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #4b4f56;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        textarea {
            width: 100%; /* يجعل الحقل يأخذ عرض الحاوية بالكامل */
            padding: 12px;
            margin-bottom: 18px;
            border: 1px solid #dddfe2;
            border-radius: 6px;
            box-sizing: border-box; /* يضمن أن padding و border لا يزيدان من العرض الكلي */
            font-size: 16px;
        }
        textarea { 
            resize: vertical; 
            min-height: 80px;
        }
        input[type="submit"] {
            background-color: #007bff; /* لون أزرق جذاب */
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 17px;
            font-weight: 600;
            width: 100%; /* زر بعرض كامل */
            transition: background-color 0.2s ease-in-out;
        }
        input[type="submit"]:hover { 
            background-color: #0056b3; /* لون أغمق عند المرور بالفأرة */
        }
        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 6px;
            text-align: right; /* ليتناسب مع dir="rtl" */
            font-size: 15px;
        }
        .message.success { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .message.error { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .message.error ul { list-style-type: none; padding-right: 0; margin-bottom: 0;} /* تعديل ليتناسب مع dir="rtl" */
        .message.error ul li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>تسجيل كافيه جديد</h1>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <strong>الرجاء تصحيح الأخطاء التالية:</strong>
                <ul>
                    <?php foreach ($errors as $error_item): ?>
                        <li><?php echo htmlspecialchars($error_item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div>
                <label for="cafe_name">اسم الكافيه:</label>
                <input type="text" id="cafe_name" name="cafe_name" value="<?php echo htmlspecialchars($cafe_name); ?>" required>
            </div>

            <div>
                <label for="owner_name">اسم المالك (اختياري):</label>
                <input type="text" id="owner_name" name="owner_name" value="<?php echo htmlspecialchars($owner_name); ?>">
            </div>

            <div>
                <label for="email">البريد الإلكتروني:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>

            <div>
                <label for="password">كلمة المرور:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div>
                <label for="confirm_password">تأكيد كلمة المرور:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div>
                <label for="phone_number">رقم الهاتف (اختياري):</label>
                <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>">
            </div>

            <div>
                <label for="address">العنوان (اختياري):</label>
                <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($address); ?></textarea>
            </div>

            <div>
                <input type="submit" value="تسجيل الحساب">
            </div>
        </form>
    </div>
</body>
</html>
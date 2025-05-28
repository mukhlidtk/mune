<?php
// الملف: public_html/m/dashboard_cafe.php
session_start();

if (!isset($_SESSION['cafe_owner_id'])) {
    header("Location: login_cafe.php");
    exit();
}

$cafe_owner_id = $_SESSION['cafe_owner_id'];
$cafe_name_session = isset($_SESSION['cafe_name']) ? htmlspecialchars($_SESSION['cafe_name']) : 'صاحب الكافيه';

require_once 'config/config.php';

// --- متغيرات عامة ---
$profile_cafe_name = $profile_owner_name = $profile_email = $profile_phone_number = $profile_address = $profile_slug = "";
$errors_profile_form = [];
$success_profile_form_message = "";

$category_name_input = $category_description_input = "";
$errors_category_form = [];
$success_category_form_message = "";
$delete_category_success_message = $delete_category_error_message = "";

$editing_category_data = null;
$edit_mode = false;

// --- جلب بيانات الكافيه الحالية للملف الشخصي (بما في ذلك الـ slug) ---
$sql_get_profile = "SELECT cafe_name, slug, owner_name, email, phone_number, address FROM cafe_owners WHERE id = ?";
$stmt_profile_get = $conn->prepare($sql_get_profile);
if ($stmt_profile_get) {
    $stmt_profile_get->bind_param("i", $cafe_owner_id);
    $stmt_profile_get->execute();
    $result_profile = $stmt_profile_get->get_result();
    if ($result_profile->num_rows === 1) {
        $cafe_profile_data = $result_profile->fetch_assoc();
        $profile_cafe_name    = $cafe_profile_data['cafe_name'];
        $profile_slug         = $cafe_profile_data['slug']; // جلب الـ slug الحالي
        $profile_owner_name   = $cafe_profile_data['owner_name'];
        $profile_email        = $cafe_profile_data['email'];
        $profile_phone_number = $cafe_profile_data['phone_number'];
        $profile_address      = $cafe_profile_data['address'];
    }
    $stmt_profile_get->close();
} else { $errors_profile_form[] = "خطأ في جلب بيانات ملف الكافيه: " . $conn->error; }


// --- معالجة طلبات GET (مثل تعديل قسم) ---
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['edit_category_id'])) {
    // ... (كود تعديل القسم GET يبقى كما هو) ...
    $edit_category_id = intval($_GET['edit_category_id']);
    $sql_get_edit_category = "SELECT id, name, description FROM menu_categories WHERE id = ? AND cafe_owner_id = ?";
    $stmt_edit_get = $conn->prepare($sql_get_edit_category);
    if ($stmt_edit_get) {
        $stmt_edit_get->bind_param("ii", $edit_category_id, $cafe_owner_id);
        $stmt_edit_get->execute();
        $result_edit = $stmt_edit_get->get_result();
        if ($result_edit->num_rows === 1) {
            $editing_category_data = $result_edit->fetch_assoc();
            $edit_mode = true;
            $category_name_input = $editing_category_data['name'];
            $category_description_input = $editing_category_data['description'];
        } else { $errors_category_form[] = "القسم المطلوب تعديله غير موجود أو لا تملك صلاحية تعديله."; }
        $stmt_edit_get->close();
    } else { $errors_category_form[] = "خطأ في إعداد جلب بيانات القسم للتعديل."; }
}


// --- معالجة طلبات POST ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        $profile_cafe_name_new = isset($_POST['profile_cafe_name']) ? trim($_POST['profile_cafe_name']) : '';
        
        // استقبال الـ slug الجديد وتنظيفه بشكل آمن
        $profile_slug_new_input = isset($_POST['profile_slug']) ? trim($_POST['profile_slug']) : ''; // <-- التصحيح هنا
        $profile_slug_new = strtolower($profile_slug_new_input); 
        $profile_slug_new = preg_replace('/[^a-z0-9-]+/', '-', $profile_slug_new); 
        $profile_slug_new = preg_replace('/-+/', '-', $profile_slug_new); 
        $profile_slug_new = trim($profile_slug_new, '-'); 

        $profile_owner_name_new = isset($_POST['profile_owner_name']) ? trim($_POST['profile_owner_name']) : '';
        $profile_phone_number_new = isset($_POST['profile_phone_number']) ? trim($_POST['profile_phone_number']) : '';
        $profile_address_new = isset($_POST['profile_address']) ? trim($_POST['profile_address']) : '';

        if (empty($profile_cafe_name_new)) { $errors_profile_form[] = "اسم الكافيه مطلوب."; }
        if (empty($profile_slug_new)) { 
            $errors_profile_form[] = "المعرّف القصير للرابط (Slug) مطلوب.";
        } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $profile_slug_new)) {
            // تأكد أن الـ slug يبدأ وينتهي بحرف أو رقم، ويمكن أن يحتوي على شرطات في المنتصف
            $errors_profile_form[] = "المعرّف القصير للرابط (Slug) يجب أن يحتوي فقط على حروف إنجليزية صغيرة، أرقام، وشرطات (ولا يبدأ أو ينتهي بشرطة).";
        } else {
            // التحقق من أن الـ slug فريد (إذا تغير عن القديم)
            if ($profile_slug_new !== $profile_slug) { // $profile_slug هو القديم الذي تم جلبه من القاعدة
                $sql_check_slug = "SELECT id FROM cafe_owners WHERE slug = ? AND id != ?";
                $stmt_check_slug = $conn->prepare($sql_check_slug);
                if ($stmt_check_slug) {
                    $stmt_check_slug->bind_param("si", $profile_slug_new, $cafe_owner_id);
                    $stmt_check_slug->execute();
                    $stmt_check_slug->store_result();
                    if ($stmt_check_slug->num_rows > 0) {
                        $errors_profile_form[] = "هذا المعرّف القصير للرابط (Slug) مستخدم بالفعل. الرجاء اختيار معرف آخر.";
                    }
                    $stmt_check_slug->close();
                } else {
                    $errors_profile_form[] = "خطأ في التحقق من فرادة الـ Slug.";
                }
            }
        }
        
        if (empty($errors_profile_form)) {
            $sql_update_profile = "UPDATE cafe_owners SET cafe_name = ?, slug = ?, owner_name = ?, phone_number = ?, address = ? WHERE id = ?";
            $stmt_update_profile = $conn->prepare($sql_update_profile);
            if ($stmt_update_profile) {
                $stmt_update_profile->bind_param("sssssi", 
                    $profile_cafe_name_new, 
                    $profile_slug_new, // إضافة الـ slug للتحديث
                    $profile_owner_name_new, 
                    $profile_phone_number_new, 
                    $profile_address_new, 
                    $cafe_owner_id
                );
                if ($stmt_update_profile->execute()) {
                    $success_profile_form_message = "تم تحديث معلومات الكافيه والمعرّف القصير بنجاح!";
                    if ($profile_cafe_name_new !== $_SESSION['cafe_name']) {
                        $_SESSION['cafe_name'] = $profile_cafe_name_new;
                        $cafe_name_session = htmlspecialchars($profile_cafe_name_new);
                    }
                    // تحديث المتغيرات المحلية بالقيم الجديدة لعرضها
                    $profile_cafe_name = $profile_cafe_name_new;
                    $profile_slug = $profile_slug_new; // تحديث الـ slug المحلي
                    $profile_owner_name = $profile_owner_name_new;
                    $profile_phone_number = $profile_phone_number_new;
                    $profile_address = $profile_address_new;
                } else { $errors_profile_form[] = "خطأ في تحديث معلومات الكافيه: " . $stmt_update_profile->error; }
                $stmt_update_profile->close();
            } else { $errors_profile_form[] = "خطأ في إعداد استعلام تحديث الملف الشخصي: " . $conn->error; }
        }
    } 
    // ... (باقي أكواد معالجة POST لأقسام المنيو تبقى كما هي) ...
    elseif (isset($_POST['update_category'])) { /* ... كود تحديث القسم ... */ 
        if (isset($_POST['category_id_to_update'], $_POST['category_name'])) {
            $category_id_to_update = intval($_POST['category_id_to_update']);
            $category_name_input = trim($_POST['category_name']);
            $category_description_input = trim($_POST['category_description']);
            $edit_mode = true; 

            if (empty($category_name_input)) { $errors_category_form[] = "اسم قسم المنيو مطلوب عند التحديث."; }

            if (empty($errors_category_form)) {
                $sql_check_owner_update = "SELECT id FROM menu_categories WHERE id = ? AND cafe_owner_id = ?";
                $stmt_check_update = $conn->prepare($sql_check_owner_update);
                if($stmt_check_update){
                    $stmt_check_update->bind_param("ii", $category_id_to_update, $cafe_owner_id);
                    $stmt_check_update->execute(); $stmt_check_update->store_result();
                    if($stmt_check_update->num_rows === 1){
                        $sql_update_category = "UPDATE menu_categories SET name = ?, description = ? WHERE id = ? AND cafe_owner_id = ?";
                        $stmt_update = $conn->prepare($sql_update_category);
                        if ($stmt_update) {
                            $stmt_update->bind_param("ssii", $category_name_input, $category_description_input, $category_id_to_update, $cafe_owner_id);
                            if ($stmt_update->execute()) {
                                $success_category_form_message = "تم تحديث قسم المنيو بنجاح!";
                                $edit_mode = false; $category_name_input = ""; $category_description_input = "";
                            } else { $errors_category_form[] = "خطأ في تحديث القسم: " . $stmt_update->error; }
                            $stmt_update->close();
                        } else { $errors_category_form[] = "خطأ في إعداد استعلام التحديث: " . $conn->error; }
                    } else { $errors_category_form[] = "محاولة تحديث قسم غير مصرح بها أو القسم غير موجود."; }
                    $stmt_check_update->close();
                } else { $errors_category_form[] = "خطأ في التحقق من ملكية القسم للتحديث.";}
            }
        } else { $errors_category_form[] = "بيانات غير كافية لتحديث القسم."; }
    }
    elseif (isset($_POST['delete_category'])) { /* ... كود حذف القسم ... */ 
        if (isset($_POST['category_id_to_delete'])) {
            $category_id_to_delete = intval($_POST['category_id_to_delete']);
            $sql_check_owner = "SELECT id FROM menu_categories WHERE id = ? AND cafe_owner_id = ?";
            $stmt_check = $conn->prepare($sql_check_owner);
            if ($stmt_check) {
                $stmt_check->bind_param("ii", $category_id_to_delete, $cafe_owner_id);
                $stmt_check->execute(); $stmt_check->store_result();
                if ($stmt_check->num_rows === 1) {
                    $sql_delete_category = "DELETE FROM menu_categories WHERE id = ? AND cafe_owner_id = ?";
                    $stmt_delete = $conn->prepare($sql_delete_category);
                    if ($stmt_delete) {
                        $stmt_delete->bind_param("ii", $category_id_to_delete, $cafe_owner_id);
                        if ($stmt_delete->execute()) { $delete_category_success_message = "تم حذف قسم المنيو وجميع عناصره بنجاح!"; } 
                        else { $delete_category_error_message = "خطأ في حذف القسم: " . $stmt_delete->error; }
                        $stmt_delete->close();
                    } else { $delete_category_error_message = "خطأ في إعداد استعلام الحذف: " . $conn->error; }
                } else { $delete_category_error_message = "محاولة حذف قسم غير مصرح بها أو القسم غير موجود."; }
                $stmt_check->close();
            } else { $delete_category_error_message = "خطأ في التحقق من ملكية القسم: " . $conn->error; }
        } else { $delete_category_error_message = "معرف القسم للحذف غير متوفر."; }
    }
    elseif (isset($_POST['add_category'])) { /* ... كود إضافة القسم ... */ 
        $category_name_input = trim($_POST['category_name']);
        $category_description_input = trim($_POST['category_description']);
        if (empty($category_name_input)) { $errors_category_form[] = "اسم قسم المنيو مطلوب."; }
        if (empty($errors_category_form)) {
            $sql_insert_category = "INSERT INTO menu_categories (cafe_owner_id, name, description) VALUES (?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert_category);
            if ($stmt_insert) {
                $stmt_insert->bind_param("iss", $cafe_owner_id, $category_name_input, $category_description_input);
                if ($stmt_insert->execute()) {
                    $success_category_form_message = "تمت إضافة قسم المنيو بنجاح!";
                    $category_name_input = ""; $category_description_input = "";
                } else { $errors_category_form[] = "خطأ في إضافة القسم: " . $stmt_insert->error; }
                $stmt_insert->close();
            } else { $errors_category_form[] = "خطأ في إعداد استعلام الإضافة: " . $conn->error; }
        }
    }
}

// --- جلب أقسام المنيو الحالية ---
$menu_categories = [];
$sql_get_categories = "SELECT id, name, description, display_order FROM menu_categories WHERE cafe_owner_id = ? ORDER BY display_order ASC, name ASC";
$stmt_get_cats = $conn->prepare($sql_get_categories);
if ($stmt_get_cats) {
    $stmt_get_cats->bind_param("i", $cafe_owner_id);
    $stmt_get_cats->execute();
    $result_categories = $stmt_get_cats->get_result();
    while ($row = $result_categories->fetch_assoc()) {
        $menu_categories[] = $row;
    }
    $stmt_get_cats->close();
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم: <?php echo $cafe_name_session; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* ... (نفس كود CSS المحسّن من الرد السابق) ... */
        :root {
            --primary-color: #6f4e37; /* بني متوسط - لون الكافيه الرئيسي */
            --secondary-color: #a0522d; /* بني سيينا - للتمييز */
            --accent-color: #007bff; /* أزرق - للأزرار الرئيسية أو الروابط */
            --danger-color: #dc3545; /* أحمر - للحذف والأخطاء */
            --warning-color: #ffc107; /* أصفر - للتنبيه والتعديل */
            --success-color: #28a745; /* أخضر - للنجاح */
            --light-bg: #f8f9fa;
            --dark-text: #343a40;
            --light-text: #fff;
            --border-color: #dee2e6;
            --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.075);
            --border-radius: 0.375rem;
        }
        body { font-family: 'Tajawal', sans-serif; margin: 0; padding: 0; background-color: var(--light-bg); color: var(--dark-text); line-height: 1.6; }
        .navbar { background-color: var(--primary-color); padding: 0.75rem 1.5rem; color: var(--light-text); display: flex; justify-content: space-between; align-items: center; box-shadow: var(--box-shadow); }
        .navbar .site-title { font-size: 1.5em; font-weight: 700; }
        .navbar .logout a { color: var(--light-text); text-decoration: none; padding: 0.5rem 1rem; border: 1px solid var(--light-text); border-radius: var(--border-radius); transition: background-color 0.2s, color 0.2s; }
        .navbar .logout a:hover { background-color: var(--light-text); color: var(--primary-color); }
        .container { padding: 20px; max-width: 960px; margin: 30px auto; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px; }
        .content-card { background-color: #fff; padding: 25px; border-radius: var(--border-radius); box-shadow: var(--box-shadow); border-top: 4px solid var(--secondary-color); }
        .content-card h2 { color: var(--primary-color); margin-top: 0; margin-bottom: 20px; font-size: 1.75em; padding-bottom: 10px; border-bottom: 1px solid var(--border-color); }
        .content-card h3 { color: var(--secondary-color); margin-top: 25px; margin-bottom: 15px; font-size: 1.3em; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        input[type="text"], input[type="email"], input[type="tel"], textarea, select { width: 100%; padding: 0.6rem 0.75rem; margin-bottom: 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); box-sizing: border-box; font-size: 1rem; transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out; }
        input[type="text"]:focus, input[type="email"]:focus, input[type="tel"]:focus, textarea:focus, select:focus { border-color: var(--accent-color); outline: 0; box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); }
        input[type="email"][readonly] { background-color: #e9ecef; cursor: not-allowed; }
        textarea { resize: vertical; min-height: 80px; }
        .btn { display: inline-block; font-weight: 500; color: var(--light-text); text-align: center; vertical-align: middle; cursor: pointer; user-select: none; background-color: transparent; border: 1px solid transparent; padding: 0.5rem 1rem; font-size: 1rem; line-height: 1.5; border-radius: var(--border-radius); transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out; margin-top: 0.5rem; }
        .btn-primary { background-color: var(--accent-color); border-color: var(--accent-color); }
        .btn-primary:hover { background-color: #0056b3; border-color: #0056b3; }
        .btn-success { background-color: var(--success-color); border-color: var(--success-color); }
        .btn-success:hover { background-color: #1e7e34; border-color: #1c7430; }
        .btn-danger { background-color: var(--danger-color); border-color: var(--danger-color); }
        .btn-danger:hover { background-color: #bd2130; border-color: #b21f2d; }
        .btn-warning { background-color: var(--warning-color); border-color: var(--warning-color); color: var(--dark-text); }
        .btn-warning:hover { background-color: #e0a800; border-color: #d39e00; }
        .btn-info { background-color: #17a2b8; border-color: #17a2b8; }
        .btn-info:hover { background-color: #117a8b; border-color: #10707f; }
        .btn-light { background-color: #f8f9fa; border-color: #f8f9fa; color: var(--dark-text); }
        .btn-light:hover { background-color: #e2e6ea; border-color: #dae0e5; }
        .message { padding: 1rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: var(--border-radius); }
        .message.success { background-color: #d1e7dd; color: #0f5132; border-color: #badbcc; }
        .message.error { background-color: #f8d7da; color: #842029; border-color: #f5c2c7; }
        .message.error ul { list-style-type: none; padding-right: 0; margin-bottom: 0;}
        .message.error ul li { margin-bottom: 0.25rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; font-size: 0.95em; }
        table th, table td { border: 1px solid var(--border-color); padding: 0.75rem; text-align: right; vertical-align: middle;}
        table th { background-color: #e9ecef; font-weight: 600; }
        .actions form, .actions a { display: inline-block; margin-right: 0.5rem; } 
        .actions .btn { padding: 0.3rem 0.6rem; font-size: 0.85em; }
        .form-actions { margin-top: 1rem; }
        .slug-help-text { font-size: 0.85em; color: #6c757d; margin-bottom: 1rem; display: block; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="site-title">لوحة تحكم: <?php echo $cafe_name_session; ?></div>
    <div class="logout"><a href="logout_cafe.php">تسجيل الخروج</a></div>
</div>

<div class="container">
    <h1 style="text-align:center; margin-bottom:30px; color:var(--primary-color);">أهلاً بك في لوحة التحكم الشاملة!</h1>

    <?php if (!empty($success_profile_form_message)): ?>
        <div class="message success"><p><?php echo htmlspecialchars($success_profile_form_message); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($errors_profile_form) && isset($_POST['update_profile'])): // عرض أخطاء الملف الشخصي فقط عند محاولة تحديثه ?>
        <div class="message error"><strong>أخطاء في ملف الكافيه:</strong><ul><?php foreach ($errors_profile_form as $error_item): ?><li><?php echo htmlspecialchars($error_item); ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if (!empty($success_category_form_message)): ?>
        <div class="message success"><p><?php echo htmlspecialchars($success_category_form_message); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($delete_category_success_message)): ?>
        <div class="message success"><p><?php echo htmlspecialchars($delete_category_success_message); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($delete_category_error_message)): ?>
        <div class="message error"><p><?php echo htmlspecialchars($delete_category_error_message); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($errors_category_form) && (isset($_POST['add_category']) || isset($_POST['update_category']) || isset($_GET['edit_category_id']))): // عرض أخطاء الأقسام عند محاولة الإضافة أو التعديل ?>
        <div class="message error"><strong>أخطاء في نموذج الأقسام:</strong><ul><?php foreach ($errors_category_form as $error_item): ?><li><?php echo htmlspecialchars($error_item); ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="content-card">
            <h2><span style="font-size: 1.5em; vertical-align: middle;">⚙️</span> ملف الكافيه</h2>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div>
                    <label for="profile_cafe_name">اسم الكافيه:</label>
                    <input type="text" id="profile_cafe_name" name="profile_cafe_name" value="<?php echo htmlspecialchars($profile_cafe_name); ?>" required>
                </div>
                <div>
                    <label for="profile_slug">المعرّف القصير للرابط (Slug):</label>
<label for="profile_slug">المعرّف القصير للرابط (Slug):</label>
                    <input type="text" id="profile_slug" name="profile_slug" value="<?php echo htmlspecialchars($profile_slug ?? ''); ?>" placeholder="مثال: my-unique-cafe-name" required>
                    <small class="slug-help-text">استخدم حروف إنجليزية صغيرة (a-z)، أرقام (0-9)، وشرطات (-). يجب أن يكون فريدًا.</small>
                </div>
                <div>
                    <label for="profile_owner_name">اسم المالك (اختياري):</label>
                    <input type="text" id="profile_owner_name" name="profile_owner_name" value="<?php echo htmlspecialchars($profile_owner_name); ?>">
                </div>
                <div>
                    <label for="profile_email">البريد الإلكتروني (للعرض فقط):</label>
                    <input type="email" id="profile_email" name="profile_email_display" value="<?php echo htmlspecialchars($profile_email); ?>" readonly>
                </div>
                <div>
                    <label for="profile_phone_number">رقم الهاتف (اختياري):</label>
                    <input type="tel" id="profile_phone_number" name="profile_phone_number" value="<?php echo htmlspecialchars($profile_phone_number); ?>">
                </div>
                <div>
                    <label for="profile_address">العنوان (مثال: الطائف، حي السلامة):</label>
                    <textarea id="profile_address" name="profile_address" rows="3"><?php echo htmlspecialchars($profile_address); ?></textarea>
                </div>
                <div>
                    <input type="submit" name="update_profile" value="حفظ تغييرات الملف الشخصي" class="btn btn-primary">
                </div>
            </form>
        </div>
        <div class="content-card">
            <h2><span style="font-size: 1.5em; vertical-align: middle;">🍽️</span> إدارة أقسام المنيو</h2>

            <?php if ($edit_mode && $editing_category_data): // عرض نموذج التعديل للقسم ?>
                <h3>تعديل قسم: "<?php echo htmlspecialchars($editing_category_data['name']); ?>"</h3>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="category_id_to_update" value="<?php echo htmlspecialchars($editing_category_data['id']); ?>">
                    <div>
                        <label for="category_name_edit">اسم القسم الجديد:</label>
                        <input type="text" id="category_name_edit" name="category_name" value="<?php echo htmlspecialchars($category_name_input); ?>" required>
                    </div>
                    <div>
                        <label for="category_description_edit">وصف القسم الجديد (اختياري):</label>
                        <textarea id="category_description_edit" name="category_description" rows="2"><?php echo htmlspecialchars($category_description_input); ?></textarea>
                    </div>
                    <div class="form-actions">
                        <input type="submit" name="update_category" value="تحديث القسم" class="btn btn-success">
                        <a href="dashboard_cafe.php" class="btn btn-light">إلغاء</a>
                    </div>
                </form>
            <?php else: // عرض نموذج الإضافة للقسم ?>
                <h3>إضافة قسم جديد</h3>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div>
                        <label for="category_name_add">اسم القسم:</label>
                        <input type="text" id="category_name_add" name="category_name" value="<?php echo htmlspecialchars($category_name_input); ?>" required>
                    </div>
                    <div>
                        <label for="category_description_add">وصف القسم (اختياري):</label>
                        <textarea id="category_description_add" name="category_description" rows="2"><?php echo htmlspecialchars($category_description_input); ?></textarea>
                    </div>
                    <div>
                        <input type="submit" name="add_category" value="إضافة قسم" class="btn btn-primary">
                    </div>
                </form>
            <?php endif; ?>

            <hr style="margin: 30px 0;">

            <h3>الأقسام الحالية <span style="font-size:0.8em; color:#777;">(<?php echo count($menu_categories); ?> قسم/أقسام)</span></h3>
            <?php if (empty($menu_categories)): ?>
                <p>لم تقم بإضافة أي أقسام للمنيو بعد. ابدأ بإضافة قسم جديد أعلاه!</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>اسم القسم</th>
                            <th>الوصف</th>
                            <th style="width: 280px;">إجراءات</th> </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menu_categories as $category): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($category['description'])); ?></td>
                                <td class="actions">
                                    <a href="category_items.php?category_id=<?php echo htmlspecialchars($category['id']); ?>" class="btn btn-info btn-sm">إدارة العناصر</a>
                                    <a href="dashboard_cafe.php?edit_category_id=<?php echo htmlspecialchars($category['id']); ?>" class="btn btn-warning btn-sm">تعديل</a>
                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" onsubmit="return confirm('هل أنت متأكد من حذف هذا القسم؟ سيتم حذف جميع العناصر الموجودة بداخله أيضًا.');">
                                        <input type="hidden" name="category_id_to_delete" value="<?php echo htmlspecialchars($category['id']); ?>">
                                        <input type="submit" name="delete_category" value="حذف" class="btn btn-danger btn-sm">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div> 
</div> 

</body>
</html>

<?php
// الملف: public_html/m/category_items.php
session_start();

// التحقق من تسجيل دخول صاحب الكافيه
if (!isset($_SESSION['cafe_owner_id'])) {
    header("Location: login_cafe.php");
    exit();
}

$cafe_owner_id = $_SESSION['cafe_owner_id'];
$cafe_name_session = isset($_SESSION['cafe_name']) ? htmlspecialchars($_SESSION['cafe_name']) : 'صاحب الكافيه';

require_once 'config/config.php'; // الاتصال بقاعدة البيانات

$category_id = null;
$category_name = "قسم غير محدد";
$current_items = []; // لعرض العناصر

// متغيرات لنموذج إضافة/تعديل العناصر
$item_id_to_edit_input = null; // لتخزين ID العنصر عند التعديل
$item_name_input = "";
$item_description_input = "";
$item_price_input = "";
$item_is_available_input = 1; // الافتراضي متوفر

$errors_item_form = [];
$success_item_form_message = "";

$delete_item_success_message = "";
$delete_item_error_message = "";

$edit_item_mode = false; // لتحديد ما إذا كنا في وضع تعديل عنصر

// 1. التحقق من وجود category_id في GET والتأكد من ملكيته
if (isset($_GET['category_id'])) {
    $category_id = intval($_GET['category_id']);
    $sql_get_category = "SELECT name FROM menu_categories WHERE id = ? AND cafe_owner_id = ?";
    $stmt_cat = $conn->prepare($sql_get_category);
    if ($stmt_cat) {
        $stmt_cat->bind_param("ii", $category_id, $cafe_owner_id);
        $stmt_cat->execute();
        $result_cat = $stmt_cat->get_result();
        if ($result_cat->num_rows === 1) {
            $category_data = $result_cat->fetch_assoc();
            $category_name = htmlspecialchars($category_data['name']);
        } else {
            $_SESSION['error_message_dashboard'] = "القسم المطلوب غير موجود أو لا تملك صلاحية الوصول إليه.";
            header("Location: dashboard_cafe.php"); exit();
        }
        $stmt_cat->close();
    } else {
        $_SESSION['error_message_dashboard'] = "خطأ في استرجاع بيانات القسم.";
        header("Location: dashboard_cafe.php"); exit();
    }
} else {
    $_SESSION['error_message_dashboard'] = "معرف القسم غير محدد.";
    header("Location: dashboard_cafe.php"); exit();
}

// --- معالجة طلب جلب بيانات عنصر للتعديل (GET request) ---
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['edit_item_id'])) {
    $item_id_to_edit_input = intval($_GET['edit_item_id']);
    $sql_get_edit_item = "SELECT id, name, description, price, is_available FROM menu_items WHERE id = ? AND category_id = ?";
    // نتأكد أن العنصر ينتمي للقسم الحالي (الذي تم التحقق من ملكيته)
    $stmt_item_get = $conn->prepare($sql_get_edit_item);
    if ($stmt_item_get) {
        $stmt_item_get->bind_param("ii", $item_id_to_edit_input, $category_id);
        $stmt_item_get->execute();
        $result_edit_item = $stmt_item_get->get_result();
        if ($result_edit_item->num_rows === 1) {
            $editing_item_data = $result_edit_item->fetch_assoc();
            $edit_item_mode = true;
            $item_name_input = $editing_item_data['name'];
            $item_description_input = $editing_item_data['description'];
            $item_price_input = $editing_item_data['price'];
            $item_is_available_input = $editing_item_data['is_available'];
        } else {
            $errors_item_form[] = "العنصر المطلوب تعديله غير موجود أو لا ينتمي لهذا القسم.";
        }
        $stmt_item_get->close();
    } else {
        $errors_item_form[] = "خطأ في إعداد جلب بيانات العنصر للتعديل.";
    }
}


// --- معالجة طلبات POST (إضافة, تعديل, حذف) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- معالجة تحديث عنصر ---
    if (isset($_POST['update_item'])) {
        if (isset($_POST['item_id_to_update'], $_POST['item_name'], $_POST['item_price'])) {
            $item_id_to_update_input = intval($_POST['item_id_to_update']);
            $item_name_input = trim($_POST['item_name']);
            $item_description_input = trim($_POST['item_description']);
            $item_price_input = trim($_POST['item_price']);
            $item_is_available_input = isset($_POST['item_is_available']) ? 1 : 0;
            $edit_item_mode = true; // البقاء في وضع التعديل لعرض النموذج في حال الخطأ

            if (empty($item_name_input)) { $errors_item_form[] = "اسم العنصر مطلوب عند التحديث."; }
            if (empty($item_price_input)) { $errors_item_form[] = "سعر العنصر مطلوب عند التحديث."; }
            elseif (!is_numeric($item_price_input) || floatval($item_price_input) < 0) { $errors_item_form[] = "السعر يجب أن يكون رقمًا موجبًا."; }

            if (empty($errors_item_form)) {
                // التأكد أن العنصر ينتمي للقسم الحالي قبل التحديث
                $sql_check_item_cat = "SELECT id FROM menu_items WHERE id = ? AND category_id = ?";
                $stmt_check_ic = $conn->prepare($sql_check_item_cat);
                if($stmt_check_ic){
                    $stmt_check_ic->bind_param("ii", $item_id_to_update_input, $category_id);
                    $stmt_check_ic->execute();
                    $stmt_check_ic->store_result();
                    if($stmt_check_ic->num_rows === 1){
                        $price_decimal = floatval($item_price_input);
                        $sql_update_item = "UPDATE menu_items SET name = ?, description = ?, price = ?, is_available = ? WHERE id = ? AND category_id = ?";
                        $stmt_update = $conn->prepare($sql_update_item);
                        if ($stmt_update) {
                            $stmt_update->bind_param("ssdiii", $item_name_input, $item_description_input, $price_decimal, $item_is_available_input, $item_id_to_update_input, $category_id);
                            if ($stmt_update->execute()) {
                                $success_item_form_message = "تم تحديث العنصر بنجاح!";
                                $edit_item_mode = false; // الخروج من وضع التعديل
                                $item_name_input = $item_description_input = $item_price_input = ""; $item_is_available_input = 1; // مسح الحقول
                            } else { $errors_item_form[] = "خطأ في تحديث العنصر: " . $stmt_update->error; }
                            $stmt_update->close();
                        } else { $errors_item_form[] = "خطأ في إعداد استعلام تحديث العنصر: " . $conn->error; }
                    } else { $errors_item_form[] = "العنصر لا ينتمي لهذا القسم أو غير موجود، لا يمكن التحديث."; }
                    $stmt_check_ic->close();
                } else { $errors_item_form[] = "خطأ في التحقق من العنصر قبل التحديث.";}
            }
        } else { $errors_item_form[] = "بيانات غير كافية لتحديث العنصر."; }
    }
    // --- معالجة حذف عنصر من المنيو ---
    elseif (isset($_POST['delete_item'])) {
        if (isset($_POST['item_id_to_delete'])) {
            $item_id_to_delete = intval($_POST['item_id_to_delete']);
            $sql_delete_item = "DELETE FROM menu_items WHERE id = ? AND category_id = ?";
            $stmt_delete = $conn->prepare($sql_delete_item);
            if ($stmt_delete) {
                $stmt_delete->bind_param("ii", $item_id_to_delete, $category_id);
                if ($stmt_delete->execute()) {
                    if ($stmt_delete->affected_rows > 0) { $delete_item_success_message = "تم حذف العنصر بنجاح!"; }
                    else { $delete_item_error_message = "لم يتم العثور على العنصر أو أنه لا ينتمي لهذا القسم."; }
                } else { $delete_item_error_message = "خطأ في حذف العنصر: " . $stmt_delete->error; }
                $stmt_delete->close();
            } else { $delete_item_error_message = "خطأ في إعداد استعلام حذف العنصر: " . $conn->error; }
        } else { $delete_item_error_message = "معرف العنصر للحذف غير متوفر."; }
    }
    // --- معالجة إضافة عنصر جديد ---
    elseif (isset($_POST['add_item'])) {
        $item_name_input = trim($_POST['item_name']);
        $item_description_input = trim($_POST['item_description']);
        $item_price_input = trim($_POST['item_price']);
        $item_is_available_input = isset($_POST['item_is_available']) ? 1 : 0;

        if (empty($item_name_input)) { $errors_item_form[] = "اسم العنصر مطلوب."; }
        if (empty($item_price_input)) { $errors_item_form[] = "سعر العنصر مطلوب."; }
        elseif (!is_numeric($item_price_input) || floatval($item_price_input) < 0) { $errors_item_form[] = "السعر يجب أن يكون رقمًا موجبًا."; }

        if (empty($errors_item_form)) {
            $price_decimal = floatval($item_price_input);
            $sql_insert_item = "INSERT INTO menu_items (category_id, name, description, price, is_available) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert_item);
            if ($stmt_insert) {
                $stmt_insert->bind_param("issdi", $category_id, $item_name_input, $item_description_input, $price_decimal, $item_is_available_input);
                if ($stmt_insert->execute()) {
                    $success_item_form_message = "تمت إضافة العنصر بنجاح!";
                    $item_name_input = $item_description_input = $item_price_input = ""; $item_is_available_input = 1;
                } else { $errors_item_form[] = "خطأ في إضافة العنصر: " . $stmt_insert->error; }
                $stmt_insert->close();
            } else { $errors_item_form[] = "خطأ في إعداد استعلام إضافة العنصر: " . $conn->error; }
        }
    }
}
// --- نهاية معالجة طلبات POST ---


// --- جلب العناصر الحالية لهذا القسم (يتم جلبها مجددًا بعد أي عملية) ---
$current_items = [];
$sql_get_items = "SELECT id, name, description, price, is_available FROM menu_items WHERE category_id = ? ORDER BY name ASC";
$stmt_items_display = $conn->prepare($sql_get_items); // استخدام اسم مختلف للمتغير
if ($stmt_items_display) {
    $stmt_items_display->bind_param("i", $category_id);
    $stmt_items_display->execute();
    $result_items_display = $stmt_items_display->get_result();
    while ($row = $result_items_display->fetch_assoc()) {
        $current_items[] = $row;
    }
    $stmt_items_display->close();
} else {
    $errors_item_form[] = "خطأ في جلب عناصر القسم للعرض: " . $conn->error; 
}
    
// إغلاق الاتصال
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة عناصر قسم: <?php echo $category_name; ?></title>
    <style>
        /* نفس كود CSS من الخطوة السابقة - لا تغييرات هنا */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f0f2f5; color: #333; }
        .navbar { background-color: #333; padding: 10px 20px; color: white; overflow: hidden; }
        .navbar a { float: right; display: block; color: white; text-align: center; padding: 14px 16px; text-decoration: none; }
        .navbar a:hover { background-color: #ddd; color: black; }
        .navbar .logout { float: left; }
        .navbar .site-title { float: right; padding: 14px 0; font-size: 18px; font-weight: bold; }
        
        .container { padding: 20px; max-width: 900px; margin: 20px auto; }
        .content-box { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .content-box h1, .content-box h2, .content-box h3 { color: #1c1e21; margin-top:0; border-bottom: 1px solid #eee; padding-bottom:10px; margin-bottom:15px;}
        
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #4b4f56; }
        input[type="text"], input[type="number"], textarea {
            width: calc(100% - 24px); padding: 10px; margin-bottom: 15px; border: 1px solid #dddfe2; border-radius: 6px; box-sizing: border-box; font-size: 16px;
        }
        input[type="checkbox"] { margin-left: 10px; vertical-align: middle; }
        textarea { resize: vertical; min-height: 60px; }
        input[type="submit"] {
            padding: 10px 18px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; transition: background-color 0.2s;
        }
        input[name="add_item"] { background-color: #17a2b8; color: white; }
        input[name="add_item"]:hover { background-color: #117a8b; }
        input[name="update_item"] { background-color: #28a745; color: white; /* أخضر للتحديث */ }
        input[name="update_item"]:hover { background-color: #218838; }


        .message { padding: 12px; margin-bottom: 20px; border-radius: 6px; text-align: right; font-size: 15px; }
        .message.success { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .message.error { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .message.error ul { list-style-type: none; padding-right: 0; margin-bottom: 0;}
        .message.error ul li { margin-bottom: 5px; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { border: 1px solid #ddd; padding: 10px; text-align: right; vertical-align: middle;}
        table th { background-color: #f8f9fa; font-weight: 600; }
        .actions form, .actions a { display: inline-block; margin-left: 5px; } 
        .actions button, .actions input[type="submit"], .actions a { text-decoration:none; padding: 5px 10px; border-radius:4px; font-size:14px; cursor:pointer; border: none;}
        .edit-btn { background-color: #ffc107; color: #212529; }
        .delete-btn { background-color: #dc3545; color: white; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #007bff; text-decoration: none; font-weight: 600; padding: 8px 12px; background-color: #e9ecef; border-radius: 4px;}
        .back-link:hover { text-decoration: underline; background-color: #dee2e6;}
    </style>
</head>
<body>

<div class="navbar">
    <a href="logout_cafe.php" class="logout">تسجيل الخروج</a>
    <div class="site-title">لوحة تحكم: <?php echo $cafe_name_session; ?></div>
</div>

<div class="container">
    <div class="content-box">
        <a href="dashboard_cafe.php" class="back-link">&laquo; العودة إلى إدارة الأقسام</a>
        <h1>إدارة عناصر قسم: <em><?php echo $category_name; ?></em></h1>
    </div>

    <div class="content-box">
        <?php if (!empty($success_item_form_message)): ?>
            <div class="message success"><p><?php echo htmlspecialchars($success_item_form_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($errors_item_form)): ?>
            <div class="message error">
                <strong>الرجاء تصحيح الأخطاء التالية:</strong>
                <ul><?php foreach ($errors_item_form as $error_item): ?><li><?php echo htmlspecialchars($error_item); ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($delete_item_success_message)): ?>
            <div class="message success"><p><?php echo htmlspecialchars($delete_item_success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($delete_item_error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($delete_item_error_message); ?></p></div>
        <?php endif; ?>


        <?php if ($edit_item_mode): // عرض نموذج تعديل العنصر ?>
            <h3>تعديل عنصر: <?php echo htmlspecialchars($item_name_input); // يعرض الاسم الحالي من متغيرات الإدخال ?></h3>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?category_id=" . intval($category_id); ?>">
                <input type="hidden" name="item_id_to_update" value="<?php echo intval($item_id_to_edit_input); ?>">
                <div>
                    <label for="item_name">اسم العنصر:</label>
                    <input type="text" id="item_name" name="item_name" value="<?php echo htmlspecialchars($item_name_input); ?>" required>
                </div>
                <div>
                    <label for="item_description">وصف العنصر (اختياري):</label>
                    <textarea id="item_description" name="item_description" rows="2"><?php echo htmlspecialchars($item_description_input); ?></textarea>
                </div>
                <div>
                    <label for="item_price">السعر (بالريال السعودي):</label>
                    <input type="number" id="item_price" name="item_price" step="0.01" min="0" value="<?php echo htmlspecialchars($item_price_input); ?>" required>
                </div>
                <div>
                    <label for="item_is_available" style="display:inline-block;">متوفر؟</label>
                    <input type="checkbox" id="item_is_available" name="item_is_available" value="1" <?php if($item_is_available_input) echo 'checked'; ?> style="width:auto; margin-right: 5px;">
                </div>
                <div style="margin-top:15px;">
                    <input type="submit" name="update_item" value="تحديث العنصر">
                    <a href="category_items.php?category_id=<?php echo intval($category_id); ?>" style="margin-right:10px; text-decoration:none; color:#333; padding:8px 12px; background-color:#eee; border-radius:4px;">إلغاء التعديل</a>
                </div>
            </form>
        <?php else: // عرض نموذج إضافة العنصر ?>
            <h3>إضافة عنصر جديد إلى قسم "<?php echo $category_name; ?>"</h3>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?category_id=" . intval($category_id); ?>">
                <div>
                    <label for="item_name">اسم العنصر:</label>
                    <input type="text" id="item_name" name="item_name" value="<?php echo htmlspecialchars($item_name_input); ?>" required>
                </div>
                <div>
                    <label for="item_description">وصف العنصر (اختياري):</label>
                    <textarea id="item_description" name="item_description" rows="2"><?php echo htmlspecialchars($item_description_input); ?></textarea>
                </div>
                <div>
                    <label for="item_price">السعر (بالريال السعودي):</label>
                    <input type="number" id="item_price" name="item_price" step="0.01" min="0" value="<?php echo htmlspecialchars($item_price_input); ?>" required>
                </div>
                <div>
                    <label for="item_is_available" style="display:inline-block;">متوفر؟</label>
                    <input type="checkbox" id="item_is_available" name="item_is_available" value="1" <?php if($item_is_available_input) echo 'checked'; ?> style="width:auto; margin-right: 5px;">
                </div>
                <div style="margin-top:15px;">
                    <input type="submit" name="add_item" value="إضافة العنصر">
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="content-box">
        <h3>العناصر الحالية في قسم "<?php echo $category_name; ?>"</h3>
        <?php if (empty($current_items)): ?>
            <p>لا توجد عناصر في هذا القسم حتى الآن.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>اسم العنصر</th>
                        <th>الوصف</th>
                        <th>السعر</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($current_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($item['description'])); ?></td>
                            <td><?php echo htmlspecialchars(number_format(floatval($item['price']), 2)); ?> ر.س</td>
                            <td><?php echo $item['is_available'] ? '<span style="color:green; font-weight:bold;">متوفر</span>' : '<span style="color:red; font-weight:bold;">غير متوفر</span>'; ?></td>
                            <td class="actions">
                                <a href="category_items.php?category_id=<?php echo intval($category_id); ?>&edit_item_id=<?php echo htmlspecialchars($item['id']); ?>" class="edit-btn">تعديل</a>
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?category_id=" . intval($category_id); ?>" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا العنصر؟');">
                                    <input type="hidden" name="item_id_to_delete" value="<?php echo htmlspecialchars($item['id']); ?>">
                                    <input type="submit" name="delete_item" value="حذف" class="delete-btn">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

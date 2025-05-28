<?php
// الملف: public_html/m/menu_display.php

// --- DEBUGGING: SCRIPT EXECUTION START ---
// هذه الأسطر ستظهر في "عرض مصدر الصفحة" حتى لو حدث خطأ لاحقًا
echo "\n";
echo "\n";
// --- END DEBUGGING ---

// تعريف المتغيرات التي سنستخدمها في الصفحة
$cafe_slug_from_url = null;
$cafe_data = null;          // سيحتوي على بيانات الكافيه إذا تم العثور عليه
$menu_structure = [];       // سيحتوي على الأقسام وعناصرها
$error_message_public = ""; // لرسائل الخطأ التي قد تظهر للمستخدم
$page_title = "قائمة الطعام"; // عنوان افتراضي للصفحة
$cafe_id_internal = null;   // لتخزين الـ ID الرقمي للكافيه بعد جلبه بالـ slug

// تضمين ملف الاتصال بقاعدة البيانات
require_once 'config/config.php'; 

// --- DEBUGGING: AFTER DB CONNECTION ATTEMPT ---
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    echo "\n";
} elseif (isset($conn) && $conn->connect_error) {
    echo "\n";
    // دالة die() في config.php يفترض أن توقف التنفيذ هنا، لكن هذا تأكيد إضافي
    $error_message_public = "خطأ في الاتصال بقاعدة البيانات. لا يمكن عرض المنيو.";
} else {
    echo "\n";
    $error_message_public = "خطأ فني في إعداد الاتصال. لا يمكن عرض المنيو.";
}
// --- END DEBUGGING ---


// نبدأ معالجة الطلب فقط إذا كان الاتصال بقاعدة البيانات ناجحًا وتم تمرير cafe_slug
if (empty($error_message_public) && isset($_GET['cafe_slug'])) {
    $cafe_slug_from_url = trim($_GET['cafe_slug']);
    echo "\n";

    if (empty($cafe_slug_from_url)) {
        $error_message_public = "المعرّف القصير للكافيه (slug) مطلوب لعرض المنيو.";
        echo "\n";
    } else {
        // 1. جلب بيانات الكافيه الأساسية باستخدام الـ slug والتأكد من أنه نشط
        $sql_cafe = "SELECT id, cafe_name, owner_name, email, phone_number, address FROM cafe_owners WHERE slug = ? AND status = 'active'";
        echo "\n";
        
        $stmt_cafe = $conn->prepare($sql_cafe);
        if ($stmt_cafe) {
            $stmt_cafe->bind_param("s", $cafe_slug_from_url);
            $stmt_cafe->execute();
            $result_cafe = $stmt_cafe->get_result();

            if ($result_cafe) { // التحقق من أن الاستعلام تم بنجاح
                echo "\n";
                if ($result_cafe->num_rows === 1) {
                    $cafe_data = $result_cafe->fetch_assoc();
                    $cafe_id_internal = $cafe_data['id']; // حفظ الـ ID الرقمي للكافيه
                    $page_title = "منيو " . htmlspecialchars($cafe_data['cafe_name']); // تحديث عنوان الصفحة
                    echo "\n";

                    // 2. جلب أقسام المنيو الخاصة بهذا الكافيه
                    $sql_categories = "SELECT id, name, description FROM menu_categories WHERE cafe_owner_id = ? ORDER BY display_order ASC, name ASC";
                    echo "\n";
                    $stmt_categories = $conn->prepare($sql_categories);

                    if ($stmt_categories) {
                        $stmt_categories->bind_param("i", $cafe_id_internal);
                        $stmt_categories->execute();
                        $result_categories = $stmt_categories->get_result();
                        echo "\n";

                        while ($category = $result_categories->fetch_assoc()) {
                            $category_id_current = $category['id'];
                            $category_items = [];
                            echo "\n";

                            // 3. جلب العناصر المتاحة لكل قسم
                            $sql_items = "SELECT id, name, description, price FROM menu_items WHERE category_id = ? AND is_available = TRUE ORDER BY name ASC";
                            $stmt_items = $conn->prepare($sql_items);
                            if ($stmt_items) {
                                $stmt_items->bind_param("i", $category_id_current);
                                $stmt_items->execute();
                                $result_items = $stmt_items->get_result();
                                echo "\n";
                                while ($item = $result_items->fetch_assoc()) {
                                    $category_items[] = $item;
                                }
                                $stmt_items->close();
                            } else {
                                $error_message_public .= " خطأ في تحضير استعلام العناصر للقسم '" . htmlspecialchars($category['name']) . "': " . htmlspecialchars($conn->error);
                                echo "\n";
                            }
                            $category['items'] = $category_items;
                            $menu_structure[] = $category;
                        }
                        $stmt_categories->close();
                    } else {
                        $error_message_public = "خطأ في تحضير استعلام أقسام المنيو: " . htmlspecialchars($conn->error);
                        echo "\n";
                    }
                } else {
                    $error_message_public = "الكافيه بالمعرّف القصير '" . htmlspecialchars($cafe_slug_from_url) . "' غير موجود أو ليس نشطًا.";
                    echo "\n";
                }
            } else { // $result_cafe لم يتم إنشاؤه (فشل الاستعلام)
                $error_message_public = "فشل تنفيذ استعلام البحث عن الكافيه.";
                echo "\n";
            }
            if ($stmt_cafe) $stmt_cafe->close(); // التأكد من إغلاق الاستعلام
        } else { // فشل $conn->prepare($sql_cafe)
            $error_message_public = "خطأ في إعداد استعلام البحث عن الكافيه: " . htmlspecialchars($conn->error);
            echo "\n";
        }
    }
} elseif (empty($error_message_public)) { // إذا لم تكن هناك أخطاء اتصال ولم يتم توفير cafe_slug
    $error_message_public = "لم يتم تحديد معرّف الكافيه (slug) لعرض المنيو.";
    echo "\n";
}

// إغلاق الاتصال إذا كان مفتوحًا
if (isset($conn) && $conn instanceof mysqli && empty($conn->connect_error)) {
    $conn->close();
    echo "\n";
} elseif (isset($conn) && $conn->connect_error) {
    echo "\n";
} else {
     echo "\n";
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        /* نفس كود CSS من الخطوة السابقة - لا تغييرات هنا */
        body { 
            font-family: 'Tajawal', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0; padding: 0; background-color: #f9f9f9; color: #333; line-height: 1.6;
        }
        .menu-header {
            background-color: #4A3B31; color: #fff; padding: 40px 20px; text-align: center;
            border-bottom: 5px solid #E4D5C7;
        }
        .menu-header h1 { margin: 0 0 10px 0; font-size: 2.8em; font-weight: bold; }
        .menu-header p { margin: 5px 0; font-size: 1.1em; }
        
        .menu-container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        
        .category-section {
            background-color: #fff; margin-bottom: 30px; padding: 20px 25px;
            border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .category-title {
            font-size: 2em; color: #4A3B31; margin-top: 0; margin-bottom: 20px;
            padding-bottom: 10px; border-bottom: 2px solid #E4D5C7;
        }
        .category-description { font-size: 1em; color: #666; margin-bottom: 20px; }
        
        .menu-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px 0; border-bottom: 1px dashed #eee;
        }
        .menu-item:last-child { border-bottom: none; }
        
        .item-info { display: flex; align-items: center; flex-grow: 1; }
        .item-details { flex-grow: 1; }
        .item-name { font-size: 1.4em; font-weight: 600; color: #503D3F; margin: 0 0 5px 0; }
        .item-description { font-size: 0.95em; color: #777; margin: 0 0 8px 0; }
        
        .item-price-quantity { display: flex; align-items: center; }
        .item-price {
            font-size: 1.3em; font-weight: bold; color: #8B4513;
            white-space: nowrap; margin-left: 15px;
        }
        .item-quantity input[type="number"] {
            width: 60px; padding: 5px 8px; text-align: center; border: 1px solid #ccc; border-radius: 4px;
            font-size: 1em; -moz-appearance: textfield; 
        }
        .item-quantity input[type="number"]::-webkit-outer-spin-button,
        .item-quantity input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none; margin: 0;
        }

        .order-form-section {
            background-color: #fff; padding: 25px; border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-top: 30px;
        }
        .order-form-section h2 { margin-top:0; color: #4A3B31; border-bottom: 2px solid #E4D5C7; padding-bottom: 10px;}
        .order-form-section label { display: block; margin-bottom: 8px; font-weight: 600; }
        .order-form-section input[type="text"], .order-form-section input[type="tel"], .order-form-section textarea {
            width: calc(100% - 22px); padding: 10px; margin-bottom: 15px;
            border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;
        }
        .order-form-section textarea { min-height: 70px; }
        .order-form-section input[type="submit"] {
            background-color: #8B4513; color: white; padding: 12px 25px; border: none;
            border-radius: 6px; cursor: pointer; font-size: 1.1em; font-weight: bold; display: block; width: 100%;
        }
        .order-form-section input[type="submit"]:hover { background-color: #65340d; }

        .error-message-public, .no-items {
            text-align: center; font-size: 1.2em; padding: 20px;
            background-color: #fff; border: 1px solid #eee; border-radius: 5px; margin: 30px auto; max-width: 700px;
        }
        .error-message-public { color: #dc3545; background-color: #f8d7da; border-color: #f5c2c7; }
        .no-items { font-style: italic; color: #888; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>

    <?php 
    // --- DEBUGGING: At the start of HTML body ---
    echo "\n";
    // --- END DEBUGGING ---
    ?>

    <?php if ($cafe_data && $cafe_id_internal): // الشرط الرئيسي لعرض محتوى المنيو ?>
        <header class="menu-header">
            <h1><?php echo htmlspecialchars($cafe_data['cafe_name']); ?></h1>
            <?php if (!empty($cafe_data['address'])): ?>
                <p>📍 <?php echo htmlspecialchars($cafe_data['address']); ?></p>
            <?php endif; ?>
            <?php if (!empty($cafe_data['phone_number'])): ?>
                <p>📞 <?php echo htmlspecialchars($cafe_data['phone_number']); ?></p>
            <?php endif; ?>
        </header>

        <div class="menu-container">
            <form action="place_order.php" method="POST">
                <input type="hidden" name="cafe_id" value="<?php echo intval($cafe_id_internal); ?>">

                <?php if (empty($menu_structure) && empty($error_message_public)): ?>
                    <p class="no-items">قائمة الطعام فارغة حاليًا لهذا الكافيه.</p>
                <?php elseif (!empty($error_message_public) && empty($menu_structure) && !$cafe_data): // إذا كان هناك خطأ عام ولم يتم تحميل بيانات الكافيه أصلاً ?>
                     <div class="error-message-public"><?php echo htmlspecialchars($error_message_public); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error_message_public) && $cafe_data && empty($menu_structure)): // إذا تم تحميل الكافيه ولكن هناك خطأ في جلب المنيو أو المنيو فارغ ?>
                    <p class="no-items">لا يمكن عرض قائمة الطعام حاليًا. <?php echo htmlspecialchars($error_message_public); ?></p>
                <?php endif; ?>


                <?php foreach ($menu_structure as $category): ?>
                    <section class="category-section">
                        <h2 class="category-title"><?php echo htmlspecialchars($category['name']); ?></h2>
                        <?php if (!empty($category['description'])): ?>
                            <p class="category-description"><?php echo nl2br(htmlspecialchars($category['description'])); ?></p>
                        <?php endif; ?>

                        <?php if (empty($category['items'])): ?>
                            <p class="no-items">لا توجد عناصر متاحة في هذا القسم حاليًا.</p>
                        <?php else: ?>
                            <?php foreach ($category['items'] as $item): ?>
                                <div class="menu-item">
                                    <div class="item-info">
                                        <div class="item-details">
                                            <h3 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                                            <?php if (!empty($item['description'])): ?>
                                                <p class="item-description"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="item-quantity">
                                            <input type="number" name="quantities[<?php echo intval($item['id']); ?>]" min="0" value="0" title="الكمية" style="width: 70px;">
                                        </div>
                                    </div>
                                    <div class="item-price">
                                        <?php echo htmlspecialchars(number_format(floatval($item['price']), 2)); ?> ر.س
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>

                <?php if (!empty($menu_structure) && empty($error_message_public) && $cafe_data): // لا تعرض قسم الطلب إذا كان المنيو فارغًا أو هناك خطأ رئيسي أو لم يتم العثور على الكافيه ?>
                    <section class="order-form-section">
                        <h2>معلومات الطلب</h2>
                        <div>
                            <label for="customer_name">الاسم:</label>
                            <input type="text" id="customer_name" name="customer_name" required>
                        </div>
                        <div>
                            <label for="customer_phone">رقم الهاتف (أو رقم الطاولة):</label>
                            <input type="tel" id="customer_phone" name="customer_phone" required>
                        </div>
                         <div>
                            <label for="order_notes">ملاحظات على الطلب (اختياري):</label>
                            <textarea id="order_notes" name="order_notes" rows="3"></textarea>
                        </div>
                        <input type="submit" value="إرسال الطلب">
                    </section>
                <?php endif; ?>
            </form> 
        </div>

    <?php elseif (!empty($error_message_public)): // إذا كان هناك خطأ عام ولم يتم تحميل بيانات الكافيه ?>
        <div class="menu-container">
             <div class="error-message-public"><?php echo htmlspecialchars($error_message_public); ?></div>
        </div>
    <?php else: // حالة افتراضية نهائية إذا لم يتم استيفاء أي من الشروط أعلاه (مثلاً، لم يتم توفير slug) ?>
        <div class="menu-container">
            <p class="error-message-public">عذرًا، لا يمكن عرض قائمة الطعام المطلوبة. يرجى التأكد من صحة الرابط.</p>
        </div>
    <?php endif; ?>

</body>
</html>
<?php
// الملف: public_html/m/place_order.php
session_start(); // ليس ضروريًا لمصادقة العميل هنا، ولكن قد يكون مفيدًا لرسائل الفلاش لاحقًا
require_once 'config/config.php'; // الاتصال بقاعدة البيانات

$page_title = "تأكيد الطلب";
$order_success_message = "";
$order_error_messages = [];

// التأكد أن الطلب جاء بطريقة POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. استقبال البيانات من النموذج
    $cafe_id = isset($_POST['cafe_id']) ? intval($_POST['cafe_id']) : null;
    $quantities = isset($_POST['quantities']) && is_array($_POST['quantities']) ? $_POST['quantities'] : [];
    $customer_name = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
    $customer_phone = isset($_POST['customer_phone']) ? trim($_POST['customer_phone']) : '';
    $order_notes = isset($_POST['order_notes']) ? trim($_POST['order_notes']) : '';

    // 2. التحقق من صحة البيانات الأساسية
    if (empty($cafe_id)) {
        $order_error_messages[] = "معرف الكافيه مفقود. لا يمكن معالجة الطلب.";
    }
    if (empty($customer_name)) {
        $order_error_messages[] = "اسم العميل مطلوب.";
    }
    if (empty($customer_phone)) {
        $order_error_messages[] = "رقم الهاتف أو الطاولة مطلوب.";
    }

    // تصفية الكميات: إزالة العناصر التي كميتها صفر أو غير صالحة
    $selected_items_with_quantities = [];
    foreach ($quantities as $item_id => $quantity) {
        $item_id = intval($item_id);
        $quantity = intval($quantity);
        if ($item_id > 0 && $quantity > 0) {
            $selected_items_with_quantities[$item_id] = $quantity;
        }
    }

    if (empty($selected_items_with_quantities)) {
        $order_error_messages[] = "الرجاء اختيار عنصر واحد على الأقل لطلبه.";
    }

    // 3. إذا لم تكن هناك أخطاء مبدئية، نبدأ معالجة الطلب
    if (empty($order_error_messages)) {
        $total_order_amount = 0;
        $valid_order_item_details = []; // لتخزين تفاصيل العناصر الصالحة مع أسعارها من قاعدة البيانات

        // 3.1 جلب أسعار العناصر وتوفرها من قاعدة البيانات والتحقق منها
        // (مهم لمنع التلاعب بالأسعار من جانب العميل والتأكد من توفر العنصر)
        $item_ids_to_check = array_keys($selected_items_with_quantities);
        if (!empty($item_ids_to_check)) {
            // إنشاء placeholders لـ IN (...)
            $placeholders = implode(',', array_fill(0, count($item_ids_to_check), '?'));
            $types = str_repeat('i', count($item_ids_to_check)); // أنواع البيانات للمعاملات (كلها integer)

            $sql_check_items = "SELECT id, name, price, is_available FROM menu_items WHERE id IN ($placeholders) AND category_id IN (SELECT id FROM menu_categories WHERE cafe_owner_id = ?)";
            // إضافة cafe_owner_id إلى أنواع البيانات ومعاملات الربط
            $types .= 'i';
            $params_for_bind = array_merge($item_ids_to_check, [$cafe_id]);


            $stmt_check_items = $conn->prepare($sql_check_items);
            if ($stmt_check_items) {
                // ربط المعاملات ديناميكيًا
                $stmt_check_items->bind_param($types, ...$params_for_bind);
                $stmt_check_items->execute();
                $result_db_items = $stmt_check_items->get_result();
                $db_items_data = [];
                while ($row = $result_db_items->fetch_assoc()) {
                    $db_items_data[$row['id']] = $row;
                }
                $stmt_check_items->close();

                // التحقق من كل عنصر تم اختياره
                foreach ($selected_items_with_quantities as $item_id => $quantity) {
                    if (!isset($db_items_data[$item_id])) {
                        $order_error_messages[] = "عنصر بمعرف {$item_id} غير موجود أو لا يتبع لهذا الكافيه.";
                        continue; // انتقل للعنصر التالي
                    }
                    $db_item = $db_items_data[$item_id];
                    if (!$db_item['is_available']) {
                        $order_error_messages[] = "عنصر '" . htmlspecialchars($db_item['name']) . "' غير متوفر حاليًا.";
                        continue;
                    }

                    $price_at_order_time = floatval($db_item['price']);
                    $subtotal = $quantity * $price_at_order_time;
                    $total_order_amount += $subtotal;

                    $valid_order_item_details[] = [
                        'menu_item_id' => $item_id,
                        'name' => $db_item['name'], // للاستخدام في رسالة التأكيد
                        'quantity' => $quantity,
                        'price_at_order_time' => $price_at_order_time,
                        'subtotal' => $subtotal
                    ];
                }
            } else {
                $order_error_messages[] = "خطأ في إعداد فحص العناصر: " . $conn->error;
            }
        }

        // إذا لم تكن هناك أخطاء بعد فحص العناصر، قم بحفظ الطلب
        if (empty($order_error_messages) && !empty($valid_order_item_details)) {
            // --- بدء معاملة قاعدة البيانات (مهم لضمان سلامة البيانات) ---
            $conn->begin_transaction();
            try {
                // 3.2 إدخال الطلب الرئيسي في جدول `orders`
                $sql_insert_order = "INSERT INTO orders (cafe_owner_id, customer_name, customer_phone, total_amount, order_notes, status) 
                                     VALUES (?, ?, ?, ?, ?, 'pending')";
                $stmt_order = $conn->prepare($sql_insert_order);
                if ($stmt_order) {
                    $stmt_order->bind_param("issds", $cafe_id, $customer_name, $customer_phone, $total_order_amount, $order_notes);
                    $stmt_order->execute();
                    $new_order_id = $conn->insert_id; // الحصول على ID الطلب الجديد
                    $stmt_order->close();

                    if ($new_order_id) {
                        // 3.3 إدخال عناصر الطلب في جدول `order_items`
                        $sql_insert_order_item = "INSERT INTO order_items (order_id, menu_item_id, quantity, price_at_order_time, subtotal) 
                                                  VALUES (?, ?, ?, ?, ?)";
                        $stmt_order_item = $conn->prepare($sql_insert_order_item);
                        if ($stmt_order_item) {
                            foreach ($valid_order_item_details as $detail) {
                                $stmt_order_item->bind_param("iiidd", 
                                    $new_order_id, 
                                    $detail['menu_item_id'], 
                                    $detail['quantity'], 
                                    $detail['price_at_order_time'], 
                                    $detail['subtotal']
                                );
                                if(!$stmt_order_item->execute()){
                                    // إذا فشل إدخال أي عنصر، أوقف العملية
                                    throw new Exception("خطأ في حفظ تفاصيل عنصر الطلب: " . $stmt_order_item->error);
                                }
                            }
                            $stmt_order_item->close();
                            $conn->commit(); // تأكيد جميع عمليات الإدخال
                            $order_success_message = "تم استلام طلبك بنجاح! رقم طلبك هو: <strong>{$new_order_id}</strong>. الإجمالي: " . number_format($total_order_amount, 2) . " ر.س.";
                        } else {
                            throw new Exception("خطأ في إعداد استعلام عناصر الطلب: " . $conn->error);
                        }
                    } else {
                        throw new Exception("لم يتم إنشاء معرف الطلب بشكل صحيح.");
                    }
                } else {
                    throw new Exception("خطأ في إعداد استعلام الطلب الرئيسي: " . $conn->error);
                }
            } catch (Exception $e) {
                $conn->rollback(); // التراجع عن أي عمليات إذا حدث خطأ
                $order_error_messages[] = "حدث خطأ أثناء معالجة طلبك: " . $e->getMessage();
            }
        } elseif (empty($order_error_messages) && empty($valid_order_item_details)){
            // هذا يحدث إذا كانت كل العناصر المختارة غير متوفرة أو بها خطأ
            $order_error_messages[] = "لم يتم اختيار أي عناصر صالحة للطلب.";
        }
    }
    // إغلاق الاتصال بعد الانتهاء
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
} else {
    // إذا لم يكن الطلب POST، يمكن توجيه المستخدم أو عرض رسالة
    // header("Location: index.php"); // أو صفحة الكافيهات الرئيسية
    // exit();
    $order_error_messages[] = "لم يتم إرسال أي طلب.";
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        body { font-family: 'Tajawal', 'Segoe UI', sans-serif; margin: 20px; background-color: #f9f9f9; color: #333; line-height: 1.6; text-align: center;}
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 600px; margin: 40px auto; }
        h1 { color: #4A3B31; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; text-align: right; font-size: 1.1em; }
        .message.success { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .message.error { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .message.error ul { list-style-type: disc; padding-right: 20px; margin-bottom: 0; text-align: right;}
        .message.error ul li { margin-bottom: 5px; }
        a.button-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #4A3B31;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1em;
        }
        a.button-link:hover { background-color: #332720; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>

        <?php if (!empty($order_success_message)): ?>
            <div class="message success">
                <p><?php echo $order_success_message; // HTML مسموح به هنا (strong) ?></p>
                <?php if (isset($new_order_id) && $cafe_id): // عرض تفاصيل الطلب الناجح ?>
                    <h3>تفاصيل طلبك:</h3>
                    <ul>
                    <?php 
                    // إعادة فتح الاتصال لجلب أسماء العناصر (للتأكيد فقط، يمكن تخزينها في الجلسة أو تمريرها)
                    // هذا ليس ضروريا إذا قمنا بتخزين أسماء العناصر في $valid_order_item_details
                    // $conn_temp = new mysqli($servername, $username, $password, $dbname);
                    // if (!$conn_temp->connect_error && !$conn_temp->set_charset("utf8mb4")) {
                        foreach ($valid_order_item_details as $ordered_item) {
                            echo "<li>" . htmlspecialchars($ordered_item['name']) . " - الكمية: " . intval($ordered_item['quantity']) . " - السعر للوحدة: " . number_format($ordered_item['price_at_order_time'], 2) . " ر.س - الإجمالي الفرعي: " . number_format($ordered_item['subtotal'], 2) . " ر.س</li>";
                        }
                    //     $conn_temp->close();
                    // }
                    ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($order_error_messages)): ?>
            <div class="message error">
                <strong>حدث خطأ (أو أخطاء) أثناء معالجة طلبك:</strong>
                <ul>
                    <?php foreach ($order_error_messages as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <a href="menu_display.php?cafe_id=<?php echo isset($cafe_id) ? intval($cafe_id) : ''; ?>" class="button-link">العودة إلى قائمة الطعام</a>
        <?php if ($order_success_message): ?>
            <a href="index.php" class="button-link" style="margin-right:10px;">الذهاب إلى الصفحة الرئيسية</a> <?php endif; ?>

    </div>
</body>
</html>
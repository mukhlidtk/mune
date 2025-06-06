<?php
session_start(); // قد لا تكون ضرورية مباشرة هنا، ولكنها ممارسة جيدة

// تضمين ملف الاتصال بقاعدة البيانات
require_once __DIR__ . '/config/database.php'; // يفترض أن place_order.php في نفس مستوى مجلد config

$message = "حدث خطأ غير متوقع أثناء معالجة طلبك."; // رسالة خطأ افتراضية
$message_type = "error";
$order_id_for_confirmation = null;
$restaurant_slug_for_redirect = isset($_POST['restaurant_slug']) ? $_POST['restaurant_slug'] : '';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // الحصول على البيانات من النموذج
    $restaurant_id = filter_input(INPUT_POST, 'restaurant_id', FILTER_VALIDATE_INT);
    $order_total_from_client = filter_input(INPUT_POST, 'order_total', FILTER_VALIDATE_FLOAT);
    $cart_data_json = $_POST['cart_data']; // مصفوفة السلة كـ JSON
    $customer_name = trim($_POST['customer_name']);
    $customer_car_type = trim($_POST['customer_car_type']);


// --- إرسال إشارة بوجود طلب جديد للمطعم (آلية ملفات بسيطة) ---
        $notification_dir = __DIR__ . '/../notifications'; // مجلد لتخزين ملفات الإشعارات، خارج public/admin
        if (!is_dir($notification_dir)) {
            mkdir($notification_dir, 0775, true); // أنشئ المجلد إذا لم يكن موجودًا
        }
        // اسم الملف سيكون بناءً على restaurant_id لتمييز الإشعارات لكل مطعم
        // يمكن إضافة timestamp أو order_id للملف لجعله فريدًا لكل طلب إذا أردنا تفاصيل أكثر في الإشعار
        $notification_file_content = json_encode(['order_id' => $new_order_id, 'time' => time()]);
        file_put_contents($notification_dir . '/restaurant_' . $restaurant_id . '.new_order', $notification_file_content);
        // ---------------------------------------------------------------
    // التحقق الأساسي من البيانات
    if (!$restaurant_id || $order_total_from_client === false || empty($cart_data_json) || empty($customer_name) || empty($customer_car_type)) {
        header("Location: menu.php?slug=" . urlencode($restaurant_slug_for_redirect) . "&order_status=failed&reason=missing_data");
        exit;
    }

    $cart_items = json_decode($cart_data_json, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($cart_items) || empty($cart_items)) {
        // خطأ: بيانات السلة غير صالحة
        header("Location: menu.php?slug=" . urlencode($restaurant_slug_for_redirect) . "&order_status=failed&reason=invalid_cart");
        exit;
    }

    // --- بدء معاملة قاعدة البيانات (Transaction) ---
    $conn->begin_transaction();

    try {
        // 1. إدخال الطلب الرئيسي في جدول 'orders'
        $order_status = 'جديد'; // أو 'Pending'
        // ملاحظة: $order_total_from_client هو الإجمالي من العميل. يجب التحقق منه من جانب الخادم في تطبيق حقيقي.
        
        $stmt_order = $conn->prepare("INSERT INTO orders (restaurant_id, customer_name, customer_car_type, total_amount, order_status) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt_order) {
            throw new Exception("فشل إعداد استعلام الطلب: " . $conn->error);
        }
        $stmt_order->bind_param("issds", $restaurant_id, $customer_name, $customer_car_type, $order_total_from_client, $order_status);
        
        if (!$stmt_order->execute()) {
            throw new Exception("فشل في حفظ الطلب الرئيسي: " . $stmt_order->error);
        }
        
        $new_order_id = $conn->insert_id; // الحصول على ID الطلب الجديد
        $stmt_order->close();

        // 2. إدخال أصناف الطلب في جدول 'order_items'
        $stmt_order_item = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, item_name, quantity, price_per_item, selected_options, sub_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt_order_item) {
            throw new Exception("فشل إعداد استعلام أصناف الطلب: " . $conn->error);
        }

        foreach ($cart_items as $item) {
            $menu_item_id_original = $item['id']; // معرّف الصنف الأصلي من جدول menu_items
            $item_name_ordered = $item['name'];
            $quantity_ordered = $item['quantity'];
            $price_per_unit_ordered = $item['finalPricePerUnit']; // السعر المحسوب للوحدة شامل الخيارات
            $selected_options_json = !empty($item['selectedOptions']) ? json_encode($item['selectedOptions']) : null;
            $sub_total_for_item = $price_per_unit_ordered * $quantity_ordered;

            $stmt_order_item->bind_param("iisidsd", 
                $new_order_id, 
                $menu_item_id_original, 
                $item_name_ordered, 
                $quantity_ordered, 
                $price_per_unit_ordered, 
                $selected_options_json,
                $sub_total_for_item
            );
            if (!$stmt_order_item->execute()) {
                throw new Exception("فشل في حفظ صنف الطلب: " . $stmt_order_item->error . " للصنف: " . $item_name_ordered);
            }
        }
        $stmt_order_item->close();

        // --- إذا كل شيء تمام، قم بتأكيد المعاملة (Commit) ---
        $conn->commit();
        
        $order_id_for_confirmation = $new_order_id;
        $message = "تم استلام طلبك بنجاح! رقم طلبك هو: " . $new_order_id;
        $message_type = "success";

        // placeholder لإطلاق التنبيه للمطعم
        // trigger_restaurant_notification($restaurant_id, $new_order_id);

    } catch (Exception $e) {
        // --- إذا حدث أي خطأ، قم بالتراجع عن المعاملة (Rollback) ---
        $conn->rollback();
        error_log("Order placement failed: " . $e->getMessage());
        $message = "عفواً، حدث خطأ أثناء معالجة طلبك. يرجى المحاولة مرة أخرى. (" . $e->getMessage() . ")";
        $message_type = "error";
        // إذا فشل الطلب، لا يوجد $order_id_for_confirmation
    }

    $conn->close();

    // إعادة التوجيه إلى صفحة تأكيد الطلب (سننشئها لاحقًا)
    // أو صفحة المنيو مع رسالة
    if ($message_type == "success" && $order_id_for_confirmation) {
        // في الإنتاج، يجب مسح localStorage/sessionStorage cart من جانب العميل بعد نجاح الطلب
        // يمكن القيام بذلك في صفحة التأكيد عبر JavaScript
        header("Location: order_confirmation.php?order_id=" . $order_id_for_confirmation . "&slug=" . urlencode($restaurant_slug_for_redirect) . "&status=success");
    } else {
        // إذا فشل، العودة إلى صفحة المنيو أو checkout مع رسالة خطأ
        header("Location: checkout.php?slug=" . urlencode($restaurant_slug_for_redirect) . "&order_status=failed&reason=" . urlencode(substr($message, 0, 100)));
    }
    exit();

} else {
    // إذا لم يكن الطلب POST، يتم توجيه المستخدم لصفحة المنيو الرئيسية
    header("Location: menu.php" . (!empty($restaurant_slug_for_redirect) ? "?slug=" . urlencode($restaurant_slug_for_redirect) : ""));
    exit();
}
?>
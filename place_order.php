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

    $validated_items = [];
    $computed_total = 0.0;

    foreach ($cart_items as $item) {
        $menu_item_id = isset($item['id']) ? intval($item['id']) : 0;
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
        if ($menu_item_id <= 0 || $quantity <= 0) {
            header("Location: menu.php?slug=" . urlencode($restaurant_slug_for_redirect) . "&order_status=failed&reason=invalid_item");
            exit;
        }

        // Fetch base price ensuring the item belongs to the restaurant
        $stmt = $conn->prepare(
            "SELECT mi.name, mi.price FROM menu_items mi
             JOIN menu_categories mc ON mi.menu_category_id = mc.id
             JOIN menu_sections ms ON mc.menu_section_id = ms.id
             WHERE mi.id = ? AND ms.restaurant_id = ? LIMIT 1"
        );
        if (!$stmt) {
            error_log('Prepare failed fetching menu item price: ' . $conn->error);
            header("Location: menu.php?slug=" . urlencode($restaurant_slug_for_redirect) . "&order_status=failed&reason=server_error");
            exit;
        }
        $stmt->bind_param("ii", $menu_item_id, $restaurant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows !== 1) {
            // الصنف غير موجود أو لا يتبع المطعم
            $stmt->close();
            header("Location: menu.php?slug=" . urlencode($restaurant_slug_for_redirect) . "&order_status=failed&reason=invalid_item");
            exit;
        }
        $row = $result->fetch_assoc();
        $base_price = floatval($row['price']);
        $item_name_from_db = $row['name'];
        $stmt->close();

        $options_total = 0.0;
        $validated_options = [];
        if (!empty($item['selectedOptions']) && is_array($item['selectedOptions'])) {
            foreach ($item['selectedOptions'] as $opt) {
                $opt_group = $opt['group'] ?? '';
                $opt_name = $opt['name'] ?? '';
                $stmt_opt = $conn->prepare(
                    "SELECT additional_price FROM menu_item_options
                     WHERE menu_item_id = ? AND option_group_name = ? AND option_name = ? AND is_available = 1 LIMIT 1"
                );
                if (!$stmt_opt) {
                    error_log('Prepare failed fetching option price: ' . $conn->error);
                    header("Location: menu.php?slug=" . urlencode($restaurant_slug_for_redirect) . "&order_status=failed&reason=server_error");
                    exit;
                }
                $stmt_opt->bind_param("iss", $menu_item_id, $opt_group, $opt_name);
                $stmt_opt->execute();
                $res_opt = $stmt_opt->get_result();
                if ($res_opt->num_rows !== 1) {
                    $stmt_opt->close();
                    header("Location: menu.php?slug=" . urlencode($restaurant_slug_for_redirect) . "&order_status=failed&reason=invalid_option");
                    exit;
                }
                $opt_row = $res_opt->fetch_assoc();
                $opt_price = floatval($opt_row['additional_price']);
                $options_total += $opt_price;
                $validated_options[] = [
                    'group' => $opt_group,
                    'name'  => $opt_name,
                    'price' => $opt_price
                ];
                $stmt_opt->close();
            }
        }

        $unit_price = $base_price + $options_total;
        $sub_total = $unit_price * $quantity;
        $computed_total += $sub_total;

        $validated_items[] = [
            'menu_item_id' => $menu_item_id,
            'item_name' => $item_name_from_db,
            'quantity' => $quantity,
            'price_per_item' => $unit_price,
            'selected_options_json' => !empty($validated_options) ? json_encode($validated_options) : null,
            'sub_total' => $sub_total
        ];
    }

    $computed_total = round($computed_total, 2);

    if (abs($computed_total - $order_total_from_client) > 0.01) {
        header("Location: menu.php?slug=" . urlencode($restaurant_slug_for_redirect) . "&order_status=failed&reason=total_mismatch");
        exit;
    }

    // --- بدء معاملة قاعدة البيانات (Transaction) ---
    $conn->begin_transaction();

    try {
        // 1. إدخال الطلب الرئيسي في جدول 'orders'
        $order_status = 'جديد'; // أو 'Pending'
        // تم حساب الإجمالي في الخادم للتأكد من صحة الأسعار والخيارات
        
        $stmt_order = $conn->prepare("INSERT INTO orders (restaurant_id, customer_name, customer_car_type, total_amount, order_status) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt_order) {
            throw new Exception("فشل إعداد استعلام الطلب: " . $conn->error);
        }
        $stmt_order->bind_param("issds", $restaurant_id, $customer_name, $customer_car_type, $computed_total, $order_status);
        
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

        foreach ($validated_items as $vItem) {
            $menu_item_id_original = $vItem['menu_item_id'];
            $item_name_ordered = $vItem['item_name'];
            $quantity_ordered = $vItem['quantity'];
            $price_per_unit_ordered = $vItem['price_per_item'];
            $selected_options_json = $vItem['selected_options_json'];
            $sub_total_for_item = $vItem['sub_total'];

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

        // إنشاء ملف إشعار للمطعم بالطلب الجديد
        $notification_dir = __DIR__ . '/../notifications';
        if (!is_dir($notification_dir)) {
            mkdir($notification_dir, 0775, true);
        }
        $notification_file_content = json_encode(['order_id' => $new_order_id, 'time' => time()]);
        file_put_contents($notification_dir . '/restaurant_' . $restaurant_id . '.new_order', $notification_file_content);

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
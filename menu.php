<?php
// تضمين ملف الاتصال بقاعدة البيانات
require_once __DIR__ . '/config/database.php'; // يفترض أن menu.php في نفس مستوى مجلد config

$restaurant_name_display = "المنيو"; // اسم افتراضي لعنوان الصفحة
$restaurant_logo_display = null;
$menu_data = [];
$error_page_message = '';

if (isset($_GET['slug'])) {
    $slug = trim($_GET['slug']);

    // 1. جلب معلومات المطعم بناءً على الـ slug
    $stmt_restaurant = $conn->prepare("SELECT id, name, logo_url, is_active FROM restaurants WHERE short_link_slug = ? LIMIT 1");
    if ($stmt_restaurant) {
        $stmt_restaurant->bind_param("s", $slug);
        $stmt_restaurant->execute();
        $result_restaurant = $stmt_restaurant->get_result();

        if ($result_restaurant->num_rows == 1) {
            $restaurant = $result_restaurant->fetch_assoc();
            if ($restaurant['is_active']) {
                $restaurant_id = $restaurant['id'];
                $restaurant_name_display = htmlspecialchars($restaurant['name']);
                $restaurant_logo_display = !empty($restaurant['logo_url']) ? htmlspecialchars($restaurant['logo_url']) : null;

                // 2. جلب الأقسام النشطة للمطعم
                $stmt_sections = $conn->prepare("SELECT id, name FROM menu_sections WHERE restaurant_id = ? AND is_visible = 1 ORDER BY display_order ASC, name ASC");
                if ($stmt_sections) {
                    $stmt_sections->bind_param("i", $restaurant_id);
                    $stmt_sections->execute();
                    $result_sections = $stmt_sections->get_result();

                    while ($section = $result_sections->fetch_assoc()) {
                        $section_id = $section['id'];
                        $section_item = [
                            'id' => $section_id,
                            'name' => htmlspecialchars($section['name']),
                            'categories' => []
                        ];

                        // 3. جلب التصنيفات النشطة لكل قسم
                        $stmt_categories = $conn->prepare("SELECT id, name FROM menu_categories WHERE menu_section_id = ? AND is_visible = 1 ORDER BY display_order ASC, name ASC");
                        if ($stmt_categories) {
                            $stmt_categories->bind_param("i", $section_id);
                            $stmt_categories->execute();
                            $result_categories = $stmt_categories->get_result();
                            
                            while ($category = $result_categories->fetch_assoc()) {
                                $category_id = $category['id'];
                                $category_item = [
                                    'id' => $category_id,
                                    'name' => htmlspecialchars($category['name']),
                                    'items' => []
                                ];

                                // 4. جلب الأصناف المتوفرة لكل تصنيف
                                $stmt_items = $conn->prepare("SELECT id, name, description, price, image_url FROM menu_items WHERE menu_category_id = ? AND is_available = 1 ORDER BY display_order ASC, name ASC");
                                if ($stmt_items) {
                                    $stmt_items->bind_param("i", $category_id);
                                    $stmt_items->execute();
                                    $result_items = $stmt_items->get_result();

                                    while ($item = $result_items->fetch_assoc()) {
                                        $item_id = $item['id'];
                                        $menu_item_data = [
                                            'id' => $item_id,
                                            'name' => htmlspecialchars($item['name']),
                                            'description' => !empty($item['description']) ? nl2br(htmlspecialchars($item['description'])) : null,
                                            'price' => number_format($item['price'], 2), // السعر الأساسي كـ string مهيأ للعرض
                                            'base_price_float' => floatval($item['price']), // السعر الأساسي كـ float للحسابات
                                            'image_url' => !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : null,
                                            'options' => []
                                        ];

                                        // 5. جلب الخيارات المتوفرة لكل صنف
                                        $stmt_options = $conn->prepare("SELECT option_group_name, option_name, additional_price FROM menu_item_options WHERE menu_item_id = ? AND is_available = 1 ORDER BY option_group_name, id");
                                        if ($stmt_options) {
                                            $stmt_options->bind_param("i", $item_id);
                                            $stmt_options->execute();
                                            $result_options = $stmt_options->get_result();
                                            while($option = $result_options->fetch_assoc()){
                                                $menu_item_data['options'][] = [
                                                    'group' => htmlspecialchars($option['option_group_name']),
                                                    'name' => htmlspecialchars($option['option_name']),
                                                    'additional_price' => number_format($option['additional_price'], 2), // للعرض
                                                    'additional_price_float' => floatval($option['additional_price']) // للحسابات
                                                ];
                                            }
                                            $stmt_options->close();
                                        }
                                        $category_item['items'][] = $menu_item_data;
                                    }
                                    $stmt_items->close();
                                }
                                $section_item['categories'][] = $category_item;
                            }
                            $stmt_categories->close();
                        }
                        $menu_data[] = $section_item;
                    }
                    $stmt_sections->close();
                }
            } else {
                $error_page_message = "عفواً، هذا المنيو غير متاح حالياً.";
            }
        } else {
            $error_page_message = "عفواً، رابط المنيو الذي طلبته غير صحيح أو لم يعد متوفراً.";
        }
        $stmt_restaurant->close();
    } else {
        $error_page_message = "حدث خطأ أثناء محاولة الوصول للمنيو. يرجى المحاولة لاحقاً.";
        error_log("SQL Error (prepare restaurant by slug): " . $conn->error);
    }
    $conn->close();
} else {
    $error_page_message = "لم يتم تحديد منيو لعرضه.";
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $restaurant_name_display; ?></title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f6fb;
            --surface: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #e5e7eb;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #16a34a;
            --success-dark: #15803d;
            --accent: #f97316;
            --shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            --radius-lg: 20px;
            --radius-md: 14px;
            --radius-sm: 10px;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: "Tajawal", "Cairo", "Segoe UI", Arial, sans-serif;
            margin: 0;
            padding: 0 0 170px 0;
            background-color: var(--bg);
            color: var(--text);
            direction: rtl;
        }
        .menu-header {
            background: rgba(255, 255, 255, 0.92);
            padding: 24px 20px 18px;
            text-align: center;
            border-bottom: 1px solid var(--border);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
            position: sticky;
            top: 0;
            z-index: 900;
            backdrop-filter: blur(14px);
        }
        .menu-header img.logo {
            max-height: 80px;
            margin-bottom: 10px;
            border-radius: 14px;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.1);
            background: #fff;
            padding: 6px;
        }
        .menu-header h1 {
            margin: 0;
            color: var(--text);
            font-size: 1.9rem;
        }
        .menu-container {
            max-width: 980px;
            margin: 32px auto;
            padding: 0 18px;
        }
        .error-container {
            text-align: center;
            padding: 50px 20px;
            font-size: 1.1rem;
            color: var(--muted);
            background: var(--surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }
        .section {
            margin-bottom: 32px;
        }
        .section-title {
            background: linear-gradient(120deg, #e0e7ff, #f8fafc);
            color: var(--text);
            padding: 14px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 18px;
            font-size: 1.5rem;
            border: 1px solid #e0e7ff;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
        }
        .category {
            margin-bottom: 22px;
            padding: 14px 18px;
            border-radius: var(--radius-md);
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--border);
        }
        .category-title {
            font-size: 1.2rem;
            color: var(--primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .category-title::before {
            content: "";
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
        }
        .item {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            margin-bottom: 16px;
            padding: 18px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        .item-image {
            width: 120px;
            height: 120px;
            border-radius: 16px;
            object-fit: cover;
            margin-left: 10px;
            flex-shrink: 0;
            border: 1px solid var(--border);
        }
        .item-details {
            flex: 1;
            min-width: 220px;
        }
        .item-name {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text);
            margin-top: 0;
            margin-bottom: 6px;
        }
        .item-description {
            font-size: 0.95rem;
            color: var(--muted);
            margin-bottom: 12px;
            line-height: 1.7;
        }
        .item-price-section {
            text-align: left;
            min-width: 140px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        .item-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--success);
            margin: 0;
        }
        .item-options-form {
            margin-top: 10px;
            width: 100%;
            border-top: 1px dashed #e2e8f0;
            padding-top: 10px;
        }
        .item-options-form h5 {
            margin: 0 0 8px 0;
            font-size: 0.95rem;
            color: var(--text);
        }
        .option-group {
            margin-bottom: 10px;
            background: #f8fafc;
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            border: 1px solid var(--border);
        }
        .option-group strong {
            font-size: 0.9rem;
        }
        .option-label {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-right: 10px;
            font-size: 0.88rem;
            cursor: pointer;
            color: var(--muted);
            line-height: 1.7;
        }
        .option-label input {
            margin-left: 5px;
        }
        .option-price {
            color: var(--accent);
            font-weight: 600;
        }
        .add-to-cart-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 999px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        .add-to-cart-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }
        .cart-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            max-height: 42vh;
            background-color: var(--surface);
            border-top: 2px solid var(--primary);
            box-shadow: 0 -10px 30px rgba(15, 23, 42, 0.12);
            padding: 16px 18px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }
        .cart-container h3 {
            margin-top: 0;
            text-align: center;
            color: var(--primary);
            font-size: 1.2rem;
        }
        #cart-items {
            flex-grow: 1;
            overflow-y: auto;
            margin-bottom: 10px;
        }
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px dashed #e5e7eb;
            font-size: 0.9rem;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .cart-item-details {
            flex-grow: 1;
            margin-right: 10px;
        }
        .cart-item-name {
            font-weight: 600;
        }
        .cart-item-options {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 4px;
        }
        .cart-item-actions {
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }
        .cart-item-actions button {
            background: #f8fafc;
            border: 1px solid var(--border);
            color: var(--text);
            width: 28px;
            height: 28px;
            line-height: 24px;
            text-align: center;
            cursor: pointer;
            margin: 0 2px;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .cart-item-actions .remove-btn {
            border-color: #fca5a5;
            color: #dc2626;
            padding: 0 8px;
            width: auto;
        }
        .cart-item-price {
            font-weight: 700;
            white-space: nowrap;
            margin-left: 10px;
            color: var(--success);
        }
        .cart-total {
            text-align: center;
            font-size: 1.1rem;
            padding: 10px 0;
            border-top: 1px solid var(--border);
            margin-top: 6px;
            background: #f8fafc;
            border-radius: var(--radius-sm);
        }
        #checkout-btn {
            background-color: var(--success);
            color: white;
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 999px;
            font-size: 1.05rem;
            cursor: pointer;
            font-weight: 700;
            margin-top: 6px;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        #checkout-btn:hover {
            background-color: var(--success-dark);
            transform: translateY(-1px);
        }
        .empty-cart-message {
            text-align: center;
            color: var(--muted);
            padding: 20px 0;
        }
        @media (max-width: 720px) {
            body {
                padding-bottom: 200px;
            }
            .menu-header {
                padding: 18px 14px;
            }
            .menu-header h1 {
                font-size: 1.5rem;
            }
            .item {
                flex-direction: column;
                align-items: stretch;
            }
            .item-image {
                width: 100%;
                height: auto;
                max-height: 200px;
                margin-left: 0;
            }
            .item-price-section {
                text-align: right;
                width: 100%;
                align-items: flex-start;
            }
            .cart-container {
                max-height: 48vh;
            }
        }
    </style>
</head>
<body>

    <header class="menu-header">
        <?php if ($restaurant_logo_display): ?>
            <img src="<?php echo $restaurant_logo_display; ?>" alt="شعار <?php echo $restaurant_name_display; ?>" class="logo">
        <?php endif; ?>
        <h1><?php echo $restaurant_name_display; ?></h1>
    </header>

    <div class="menu-container">
        <?php if (!empty($error_page_message)): ?>
            <div class="error-container">
                <p><?php echo htmlspecialchars($error_page_message); ?></p>
            </div>
        <?php elseif (empty($menu_data)): ?>
             <div class="error-container">
                <p>لا توجد أصناف لعرضها في هذا المنيو حاليًا.</p>
            </div>
        <?php else: ?>
            <?php foreach ($menu_data as $section): ?>
                <section class="section">
                    <h2 class="section-title"><?php echo $section['name']; ?></h2>
                    <?php if (empty($section['categories'])): ?>
                        <p style="padding-right:15px; color:#777;">لا توجد تصنيفات في هذا القسم حاليًا.</p>
                    <?php else: ?>
                        <?php foreach ($section['categories'] as $category): ?>
                            <div class="category">
                                <h3 class="category-title"><?php echo $category['name']; ?></h3>
                                <?php if (empty($category['items'])): ?>
                                    <p style="color:#777;">لا توجد أصناف في هذا التصنيف حاليًا.</p>
                                <?php else: ?>
                                    <?php foreach ($category['items'] as $item_loop): // تم تغيير اسم المتغير لتجنب التعارض ?>
                                        <article class="item" id="item-<?php echo $item_loop['id']; ?>">
                                            <?php if ($item_loop['image_url']): ?>
                                                <img src="<?php echo $item_loop['image_url']; ?>" alt="<?php echo $item_loop['name']; ?>" class="item-image">
                                            <?php endif; ?>
                                            <div class="item-details">
                                                <h4 class="item-name"><?php echo $item_loop['name']; ?></h4>
                                                <?php if ($item_loop['description']): ?>
                                                    <p class="item-description"><?php echo $item_loop['description']; ?></p>
                                                <?php endif; ?>

                                                <?php if (!empty($item_loop['options'])): ?>
                                                    <div class="item-options-form">
                                                        <h5>اختر من الخيارات:</h5>
                                                        <?php
                                                        $grouped_options_form = [];
                                                        foreach ($item_loop['options'] as $option_loop) { // تم تغيير اسم المتغير
                                                            $grouped_options_form[$option_loop['group']][] = $option_loop;
                                                        }
                                                        // استخدام معرّف فريد لكل مجموعة راديو بناءً على الصنف والمجموعة
                                                        $radio_group_counter = 0;
                                                        foreach ($grouped_options_form as $group_name_form => $options_in_group_form): 
                                                            $radio_group_name = "item_{$item_loop['id']}_group_" . (++$radio_group_counter) ;
                                                        ?>
                                                            <div class="option-group">
                                                                <strong><?php echo htmlspecialchars($group_name_form); ?>:</strong>
                                                                <?php foreach ($options_in_group_form as $opt_idx => $opt_form): ?>
                                                                    <label class="option-label">
                                                                        <input type="<?php echo (strpos(strtolower($group_name_form), 'حجم') !== false || count($options_in_group_form) > 1 && (strpos(strtolower($group_name_form), 'اختر واحد') !== false || strpos(strtolower($group_name_form), 'نوع') !== false ) ) ? 'radio' : 'checkbox'; ?>" 
                                                                               name="<?php echo $radio_group_name; ?>"
                                                                               value="<?php echo htmlspecialchars($opt_form['name']); ?>"
                                                                               data-price="<?php echo $opt_form['additional_price_float']; ?>"
                                                                               data-group="<?php echo htmlspecialchars($group_name_form); ?>"
                                                                               <?php if ( (strpos(strtolower($group_name_form), 'حجم') !== false || count($options_in_group_form) > 1 && (strpos(strtolower($group_name_form), 'اختر واحد') !== false || strpos(strtolower($group_name_form), 'نوع') !== false )) && $opt_idx === 0) echo 'checked'; ?>
                                                                               >
                                                                        <?php echo htmlspecialchars($opt_form['name']); ?>
                                                                        <?php if (floatval($opt_form['additional_price_float']) > 0): ?>
                                                                            (+<span class="option-price"><?php echo $opt_form['additional_price']; ?> ر.س</span>)
                                                                        <?php endif; ?>
                                                                    </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="item-price-section">
                                                <p class="item-price" data-base-price="<?php echo $item_loop['base_price_float']; ?>">
                                                    السعر: <span class="current-item-price"><?php echo $item_loop['price']; ?></span> ر.س
                                                </p>
                                                <button class="add-to-cart-btn" 
                                                        onclick="addToCart(
                                                            '<?php echo $item_loop['id']; ?>', 
                                                            '<?php echo htmlspecialchars(addslashes($item_loop['name'])); ?>', 
                                                            <?php echo $item_loop['base_price_float']; ?>
                                                        )">
                                                    أضف إلى السلة
                                                </button>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="cart-container">
        <h3>سلة التسوق</h3>
        <div id="cart-items">
            <p class="empty-cart-message">السلة فارغة حاليًا.</p>
        </div>
        <div class="cart-total">
            <strong>الإجمالي: <span id="cart-total-price">0.00</span> ر.س</strong>
        </div>
        <button id="checkout-btn" style="display:none;" onclick="goToCheckout()">إتمام الطلب</button>
    </div>

    <script>
        let cart = [];

        document.addEventListener('DOMContentLoaded', () => {
            loadCart();
            displayCart();
            updateAllItemPrices(); 
        });
        
        function updateItemDisplayPrice(itemId) {
            const itemElement = document.getElementById('item-' + itemId);
            if (!itemElement) return;

            const basePriceElement = itemElement.querySelector('.item-price');
            const currentPriceSpan = itemElement.querySelector('.current-item-price');
            if (!basePriceElement || !currentPriceSpan) return;

            let basePrice = parseFloat(basePriceElement.dataset.basePrice);
            let optionsPriceTotal = 0;

            const optionsForm = itemElement.querySelector('.item-options-form');
            if (optionsForm) {
                const selectedOptionsInputs = optionsForm.querySelectorAll('input:checked');
                selectedOptionsInputs.forEach(optInput => {
                    optionsPriceTotal += parseFloat(optInput.dataset.price);
                });
            }
            currentPriceSpan.textContent = (basePrice + optionsPriceTotal).toFixed(2);
        }

        function updateAllItemPrices() {
            document.querySelectorAll('.item').forEach(itemEl => {
                const itemId = itemEl.id.split('-')[1];
                updateItemDisplayPrice(itemId); 
                const optionsInputs = itemEl.querySelectorAll('.item-options-form input');
                optionsInputs.forEach(input => {
                    input.addEventListener('change', () => updateItemDisplayPrice(itemId));
                });
            });
        }

        function addToCart(id, name, basePrice) {
            const itemElement = document.getElementById('item-' + id);
            let selectedOptionsForCart = [];
            let optionsPriceTotal = 0;

            if (itemElement) {
                const optionsForm = itemElement.querySelector('.item-options-form');
                if (optionsForm) {
                    const checkedOptionsInputs = optionsForm.querySelectorAll('input:checked');
                    checkedOptionsInputs.forEach(optInput => {
                        selectedOptionsForCart.push({
                            group: optInput.dataset.group,
                            name: optInput.value,
                            price: parseFloat(optInput.dataset.price)
                        });
                        optionsPriceTotal += parseFloat(optInput.dataset.price);
                    });
                }
            }
            
            const finalItemPricePerUnit = basePrice + optionsPriceTotal;
            const cartItemKey = generateCartItemKey(id, selectedOptionsForCart);

            const existingItemInCart = cart.find(cartItm => cartItm.key === cartItemKey);

            if (existingItemInCart) {
                existingItemInCart.quantity++;
            } else {
                cart.push({
                    key: cartItemKey,
                    id: id,
                    name: name,
                    basePrice: basePrice,
                    selectedOptions: selectedOptionsForCart,
                    finalPricePerUnit: finalItemPricePerUnit,
                    quantity: 1
                });
            }
            saveCart();
            displayCart();
        }

        function generateCartItemKey(itemId, options) {
            let key = `item_${itemId}`;
            if (options && options.length > 0) {
                const sortedOptions = options.slice().sort((a, b) => {
                    const groupCompare = a.group.localeCompare(b.group);
                    if (groupCompare !== 0) return groupCompare;
                    return a.name.localeCompare(b.name);
                });
                sortedOptions.forEach(opt => {
                    key += `_opt_${opt.group.replace(/\s+/g, '_')}_${opt.name.replace(/\s+/g, '_')}`;
                });
            }
            return key;
        }

        function displayCart() {
            const cartItemsDiv = document.getElementById('cart-items');
            const cartTotalPriceSpan = document.getElementById('cart-total-price');
            const checkoutBtn = document.getElementById('checkout-btn');
            cartItemsDiv.innerHTML = ''; 

            if (cart.length === 0) {
                cartItemsDiv.innerHTML = '<p class="empty-cart-message">السلة فارغة حاليًا.</p>';
                checkoutBtn.style.display = 'none';
                cartTotalPriceSpan.textContent = '0.00';
                return;
            }

            let grandTotal = 0;
            cart.forEach(cartItm => { // Changed 'item' to 'cartItm' to avoid conflict
                const itemDiv = document.createElement('div');
                itemDiv.classList.add('cart-item');
                
                let optionsText = '';
                if (cartItm.selectedOptions && cartItm.selectedOptions.length > 0) {
                    optionsText = cartItm.selectedOptions.map(opt => `${opt.group}: ${opt.name} (+${opt.price.toFixed(2)})`).join('<br>');
                }

                itemDiv.innerHTML = `
                    <div class="cart-item-details">
                        <span class="cart-item-name">${cartItm.name}</span>
                        ${optionsText ? `<div class="cart-item-options">${optionsText}</div>` : ''}
                        <small>سعر الوحدة: ${cartItm.finalPricePerUnit.toFixed(2)} ر.س</small>
                    </div>
                    <div class="cart-item-actions">
                        <button onclick="updateQuantity('${cartItm.key}', -1)">-</button>
                        <span>${cartItm.quantity}</span>
                        <button onclick="updateQuantity('${cartItm.key}', 1)">+</button>
                        <button class="remove-btn" onclick="removeFromCart('${cartItm.key}')">إزالة</button>
                    </div>
                    <div class="cart-item-price">
                        ${(cartItm.finalPricePerUnit * cartItm.quantity).toFixed(2)} ر.س
                    </div>
                `;
                cartItemsDiv.appendChild(itemDiv);
                grandTotal += cartItm.finalPricePerUnit * cartItm.quantity;
            });

            cartTotalPriceSpan.textContent = grandTotal.toFixed(2);
            checkoutBtn.style.display = 'block';
        }

        function updateQuantity(itemKey, change) {
            const cartItm = cart.find(ci => ci.key === itemKey); // Changed 'item' to 'cartItm'
            if (cartItm) {
                cartItm.quantity += change;
                if (cartItm.quantity <= 0) {
                    removeFromCart(itemKey);
                } else {
                    saveCart();
                    displayCart();
                }
            }
        }

        function removeFromCart(itemKey) {
            cart = cart.filter(cartItm => cartItm.key !== itemKey); // Changed 'item' to 'cartItm'
            saveCart();
            displayCart();
        }

        function saveCart() {
            localStorage.setItem('restaurantMenuCart_<?php echo $slug; ?>', JSON.stringify(cart)); // Cart per slug
        }

        function loadCart() {
            const savedCart = localStorage.getItem('restaurantMenuCart_<?php echo $slug; ?>'); // Cart per slug
            if (savedCart) {
                cart = JSON.parse(savedCart);
            }
        }

        function goToCheckout() {
            if (cart.length === 0) {
                alert('سلة التسوق فارغة!');
                return;
            }
            // تخزين السلة في sessionStorage للانتقال بها لصفحة الدفع
            sessionStorage.setItem('checkoutCartDetails', JSON.stringify({
                restaurantId: <?php echo isset($restaurant_id) ? $restaurant_id : 'null'; ?>,
                restaurantName: '<?php echo addslashes($restaurant_name_display); ?>',
                cart: cart,
                total: parseFloat(document.getElementById('cart-total-price').textContent)
            }));
            // هنا يجب أن يكون لديك صفحة checkout.php
            window.location.href = 'checkout.php?slug=<?php echo $slug; ?>'; 
            // alert('سيتم نقلك لصفحة إتمام الطلب (قيد الإنشاء)!');
            // console.log('Cart for checkout:', cart);
        }

    </script>
</body>
</html>
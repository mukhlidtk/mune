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
        body { font-family: Arial, sans-serif; margin: 0; padding: 0 0 150px 0; /* ترك مسافة للسلة في الأسفل */ background-color: #f8f8f8; direction: rtl; }
        .menu-header { background-color: #fff; padding: 20px; text-align: center; border-bottom: 1px solid #eee; box-shadow: 0 2px 4px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 900; }
        .menu-header img.logo { max-height: 80px; margin-bottom: 10px; }
        .menu-header h1 { margin: 0; color: #333; }
        .menu-container { max-width: 900px; margin: 20px auto; padding: 0 15px; }
        .error-container { text-align: center; padding: 50px 20px; font-size: 1.2em; color: #777; }
        .section { margin-bottom: 30px; }
        .section-title { background-color: #e9ecef; color: #495057; padding: 12px 15px; border-radius: 5px; margin-bottom: 15px; font-size: 1.6em; }
        .category { margin-bottom: 20px; padding-right: 15px; border-right: 3px solid #007bff; }
        .category-title { font-size: 1.3em; color: #007bff; margin-bottom: 10px; }
        .item { background-color: #fff; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 15px; padding: 15px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; }
        .item-image { width: 100px; height: 100px; border-radius: 5px; object-fit: cover; margin-left: 15px; margin-bottom: 10px; flex-shrink: 0; }
        .item-details { flex: 1; min-width: 200px; }
        .item-name { font-size: 1.2em; font-weight: bold; color: #333; margin-top: 0; margin-bottom: 5px;}
        .item-description { font-size: 0.9em; color: #666; margin-bottom: 10px; }
        .item-price-section { text-align: left; min-width: 120px; /* مساحة كافية للسعر والزر */ }
        .item-price { font-size: 1.1em; font-weight: bold; color: #28a745; margin-bottom: 5px;} /* تم تصغير الخط قليلاً */
        .item-options-form { margin-top: 10px; width: 100%; border-top: 1px dashed #eee; padding-top: 10px;}
        .item-options-form h5 { margin: 0 0 8px 0; font-size: 0.95em; color: #555; }
        .option-group { margin-bottom: 8px; }
        .option-group strong { font-size: 0.9em; }
        .option-label { display: block; margin-right: 10px; font-size: 0.85em; cursor: pointer; color: #777; line-height: 1.6; }
        .option-label input { margin-left: 5px; vertical-align: middle; }
        .option-price { color: #e83e8c; }
        .add-to-cart-btn {
            background-color: #007bff; color: white; border: none; padding: 8px 12px; /* تم تصغير الحجم */
            border-radius: 4px; cursor: pointer; font-size: 0.9em; margin-top: 10px; /* تم تصغير الخط */
        }
        .add-to-cart-btn:hover { background-color: #0056b3; }

        .cart-container {
            position: fixed;
            bottom: 0;
            left: 0; 
            right: 0; /* لجعله يمتد على كامل العرض في الأسفل */
            width: 100%; /* عرض كامل */
            max-height: 40vh; /* تحديد أقصى ارتفاع */
            background-color: #fff;
            border-top: 2px solid #007bff;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.15);
            padding: 15px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            box-sizing: border-box; /* لضمان أن padding لا يزيد العرض */
        }
        .cart-container h3 { margin-top: 0; text-align: center; color: #007bff; font-size: 1.2em; }
        #cart-items { flex-grow: 1; overflow-y: auto; margin-bottom: 10px; }
        .cart-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; border-bottom: 1px dashed #eee; font-size: 0.9em;
        }
        .cart-item:last-child { border-bottom: none; }
        .cart-item-details { flex-grow: 1; margin-right: 10px; } /* مسافة لليمين */
        .cart-item-name { font-weight: bold; }
        .cart-item-options { font-size: 0.8em; color: #555; margin-top: 3px; }
        .cart-item-actions { display: flex; align-items: center; flex-shrink: 0; }
        .cart-item-actions button {
            background: none; border: 1px solid #ccc; color: #333;
            width: 24px; height: 24px; line-height: 20px; text-align:center;
            cursor: pointer; margin: 0 2px; border-radius:3px; font-size: 0.9em;
        }
        .cart-item-actions .remove-btn { border-color: #dc3545; color: #dc3545; width: auto; padding: 0 5px;}
        .cart-item-price { font-weight: bold; white-space: nowrap; margin-left: 10px; flex-shrink: 0; } /* مسافة لليسار */
        .cart-total { text-align: center; font-size: 1.2em; padding: 10px 0; border-top: 1px solid #ccc; margin-top: 5px;}
        #checkout-btn {
            background-color: #28a745; color: white; width: 100%; padding: 12px;
            border: none; border-radius: 5px; font-size: 1.1em; cursor: pointer; margin-top: 5px;
        }
        #checkout-btn:hover { background-color: #218838; }
        .empty-cart-message { text-align:center; color: #777; padding: 20px 0; }

        @media (max-width: 600px) {
            .item-image { width: 100%; height: auto; max-height: 180px; margin-left: 0; }
            .item-details { width: 100%; }
            .item-price-section { text-align: right; width: 100%; margin-top:10px; }
            .cart-container { max-height: 45vh; } /* زيادة ارتفاع السلة قليلاً في الجوال */
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
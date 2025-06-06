<?php
// لا نحتاج session_start() هنا لأننا سنعتمد على sessionStorage من JavaScript
// ولكن سنحتاج slug المطعم من GET parameter
$restaurant_slug = isset($_GET['slug']) ? htmlspecialchars($_GET['slug']) : '';
if (empty($restaurant_slug)) {
    // إذا لم يتم توفير slug، يمكن توجيه المستخدم أو عرض خطأ
    // للتسهيل الآن، سنسمح للصفحة بالتحميل ولكنها قد لا تعمل بشكل كامل بدون slug
    // في التطبيق الحقيقي، يجب معالجة هذا بشكل أفضل
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إتمام الطلب</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f8f8f8; direction: rtl; }
        .container { max-width: 700px; margin: 20px auto; background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; color: #333; }
        h1 { margin-bottom: 30px; }
        h2 { margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; font-size: 1.4em; }
        
        .order-summary table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .order-summary th, .order-summary td { text-align: right; padding: 10px; border-bottom: 1px solid #eee; }
        .order-summary th { background-color: #f9f9f9; font-size: 0.95em; }
        .order-summary td.price, .order-summary th.price { text-align: left; }
        .order-summary .item-name { font-weight: bold; }
        .order-summary .item-options { font-size: 0.85em; color: #666; padding-right: 15px; }
        .order-summary .total-row td { font-weight: bold; font-size: 1.2em; border-top: 2px solid #333; }
        
        .customer-details-form label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .customer-details-form input[type="text"] {
            width: calc(100% - 22px); padding: 12px; margin-bottom: 20px;
            border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box;
            font-size: 1em;
        }
        .customer-details-form input[type="submit"] {
            background-color: #28a745; color: white; width: 100%; padding: 15px;
            border: none; border-radius: 5px; font-size: 1.2em; cursor: pointer;
            transition: background-color 0.2s;
        }
        .customer-details-form input[type="submit"]:hover { background-color: #218838; }
        .empty-cart-message-checkout { text-align: center; color: #777; padding: 30px 0; font-size: 1.1em;}
        .back-to-menu-link { display: block; text-align: center; margin-top: 20px; color: #007bff; }

        #loading-spinner {
            display: none; /* Hidden by default */
            position: fixed;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            border: 6px solid #f3f3f3; /* Light grey */
            border-top: 6px solid #007bff; /* Blue */
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            z-index: 2000;
        }
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        .overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent black */
            z-index: 1999; /* Below spinner, above content */
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>
    <div id="loading-spinner"></div>

    <div class="container">
        <h1>مراجعة الطلب وإتمامه</h1>

        <div class="order-summary">
            <h2>ملخص طلبك</h2>
            <div id="order-summary-items">
                </div>
            <p class="empty-cart-message-checkout" style="display:none;">سلة التسوق فارغة. لا يمكنك إتمام الطلب.</p>
        </div>

        <div class="customer-details-form" id="customer-form-section" style="display:none;">
            <h2>تفاصيل الاستلام</h2>
            <form id="checkout-form" action="place_order.php" method="POST">
                <input type="hidden" name="restaurant_slug" value="<?php echo $restaurant_slug; ?>">
                <input type="hidden" name="restaurant_id" id="form_restaurant_id">
                <input type="hidden" name="order_total" id="form_order_total">
                <input type="hidden" name="cart_data" id="form_cart_data">

                <label for="customer_name">الاسم:</label>
                <input type="text" id="customer_name" name="customer_name" required placeholder="الاسم الكامل">

                <label for="customer_car_type">نوع وموديل السيارة (مثال: تويوتا كامري):</label>
                <input type="text" id="customer_car_type" name="customer_car_type" required placeholder="مثال: هونداي اكسنت">
                
                <input type="submit" value="تأكيد الطلب والدفع عند الاستلام">
            </form>
        </div>
        <a href="menu.php?slug=<?php echo $restaurant_slug; ?>" class="back-to-menu-link" id="back-link-empty" style="display:none;">العودة إلى المنيو</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const orderSummaryItemsDiv = document.getElementById('order-summary-items');
            const customerFormSection = document.getElementById('customer-form-section');
            const emptyCartMessageCheckout = document.querySelector('.empty-cart-message-checkout');
            const backLinkEmpty = document.getElementById('back-link-empty');

            const checkoutDetailsString = sessionStorage.getItem('checkoutCartDetails');

            if (checkoutDetailsString) {
                const checkoutDetails = JSON.parse(checkoutDetailsString);
                const cart = checkoutDetails.cart;
                const total = checkoutDetails.total;
                const restaurantId = checkoutDetails.restaurantId;
                const restaurantName = checkoutDetails.restaurantName; // يمكن استخدامه في العنوان إذا أردت

                if (cart && cart.length > 0) {
                    customerFormSection.style.display = 'block';
                    let tableHTML = '<table><thead><tr><th>الصنف</th><th class="price">الكمية</th><th class="price">السعر الإجمالي</th></tr></thead><tbody>';
                    cart.forEach(item => {
                        tableHTML += `
                            <tr>
                                <td>
                                    <div class="item-name">${item.name}</div>
                                    ${item.selectedOptions && item.selectedOptions.length > 0 ? 
                                        `<div class="item-options">${item.selectedOptions.map(opt => `${opt.group}: ${opt.name} (+${opt.price.toFixed(2)})`).join(', ')}</div>` 
                                        : ''
                                    }
                                </td>
                                <td class="price">${item.quantity}</td>
                                <td class="price">${(item.finalPricePerUnit * item.quantity).toFixed(2)} ر.س</td>
                            </tr>
                        `;
                    });
                    tableHTML += `
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="2">الإجمالي النهائي:</td>
                                <td class="price">${total.toFixed(2)} ر.س</td>
                            </tr>
                        </tfoot></table>`;
                    orderSummaryItemsDiv.innerHTML = tableHTML;

                    // ملء الحقول المخفية في النموذج
                    document.getElementById('form_restaurant_id').value = restaurantId;
                    document.getElementById('form_order_total').value = total.toFixed(2);
                    document.getElementById('form_cart_data').value = JSON.stringify(cart); // إرسال السلة كـ JSON

                } else {
                    displayEmptyCartMessage();
                }
            } else {
                displayEmptyCartMessage();
            }

            function displayEmptyCartMessage() {
                orderSummaryItemsDiv.innerHTML = ''; // مسح أي بقايا
                emptyCartMessageCheckout.style.display = 'block';
                customerFormSection.style.display = 'none';
                backLinkEmpty.style.display = 'block';
            }

            const checkoutForm = document.getElementById('checkout-form');
            if(checkoutForm) {
                checkoutForm.addEventListener('submit', function() {
                    document.getElementById('loading-spinner').style.display = 'block';
                    document.getElementById('overlay').style.display = 'block';
                    // لا تحتاج لـ event.preventDefault() هنا لأننا نريد الإرسال الفعلي
                    // يمكنك إضافة المزيد من التحقق من جانب العميل هنا إذا أردت قبل الإرسال
                });
            }
        });
    </script>
</body>
</html>
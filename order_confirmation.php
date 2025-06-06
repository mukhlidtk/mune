<?php
// لا نحتاج session_start() هنا إلا إذا كنا سنستخدم متغيرات جلسة من الخادم
// لكننا سنعتمد على GET parameters و JavaScript لمسح sessionStorage/localStorage

$order_id = isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : null;
$restaurant_slug = isset($_GET['slug']) ? htmlspecialchars($_GET['slug']) : '';
$status = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : '';

$page_title = "تأكيد الطلب";
$confirmation_message = '';
$error_message_confirmation = '';

if ($status === 'success' && !empty($order_id)) {
    $page_title = "تم استلام طلبك بنجاح!";
    $confirmation_message = "شكراً لك! تم استلام طلبك بنجاح. رقم طلبك هو: <strong>" . $order_id . "</strong>.";
    $confirmation_message .= "<br>يقوم المطعم الآن بتجهيز طلبك. يمكنك العودة إلى المنيو أو إغلاق هذه الصفحة.";
} else {
    // إذا لم يكن هناك نجاح أو رقم طلب، اعرض رسالة خطأ عامة أو وجه للصفحة الرئيسية
    $page_title = "خطأ في تأكيد الطلب";
    $error_message_confirmation = "عفواً، حدث خطأ ما أو أن رقم الطلب غير متوفر. إذا كنت قد أتممت طلبًا، يرجى مراجعة المطعم مباشرة.";
    if (empty($restaurant_slug)){
         // لا يوجد slug للعودة إليه، ربما نوجه للصفحة الرئيسية للموقع إذا كانت موجودة
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f8f8f8; direction: rtl; text-align: center; }
        .container { max-width: 600px; margin: 40px auto; background-color: #fff; padding: 30px 40px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h1 { color: #28a745; margin-bottom: 20px; font-size: 2em; }
        h1.error { color: #dc3545; }
        p { font-size: 1.1em; line-height: 1.7; color: #333; margin-bottom: 25px; }
        p strong { color: #007bff; font-size: 1.2em; }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 25px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1em;
            transition: background-color 0.2s;
        }
        .back-link:hover { background-color: #0056b3; }
        .icon { font-size: 4em; margin-bottom: 20px; }
        .icon.success { color: #28a745; }
        .icon.error { color: #dc3545; }

    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($confirmation_message)): ?>
            <div class="icon success">&#10004;</div> <h1><?php echo $page_title; ?></h1>
            <p><?php echo $confirmation_message; ?></p>
            <?php if (!empty($restaurant_slug)): ?>
                <a href="menu.php?slug=<?php echo $restaurant_slug; ?>" class="back-link">العودة إلى المنيو</a>
            <?php endif; ?>
        <?php else: ?>
            <div class="icon error">&#10008;</div> <h1 class="error"><?php echo $page_title; ?></h1>
            <p><?php echo htmlspecialchars($error_message_confirmation); ?></p>
            <?php if (!empty($restaurant_slug)): ?>
                 <a href="menu.php?slug=<?php echo $restaurant_slug; ?>" class="back-link">العودة إلى المنيو</a>
            <?php else: ?>
                 <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($status === 'success' && !empty($restaurant_slug)): ?>
    <script>
        // مسح سلة التسوق وبيانات إتمام الطلب بعد تأكيد الطلب بنجاح
        document.addEventListener('DOMContentLoaded', () => {
            const slug = '<?php echo $restaurant_slug; ?>';
            if (slug) {
                localStorage.removeItem('restaurantMenuCart_' + slug);
            }
            sessionStorage.removeItem('checkoutCartDetails');
            
            // يمكنك إضافة رسالة في الكونسول للتأكيد
            console.log('Cart data for slug "' + slug + '" cleared from localStorage.');
            console.log('Checkout details cleared from sessionStorage.');
        });
    </script>
    <?php endif; ?>
</body>
</html>
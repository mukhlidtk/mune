<?php
// الملف: public_html/m/dashboard_admin.php
session_start();

// التحقق مما إذا كان المدير مسجلاً دخوله
if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit();
}

$admin_username = isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'المدير';

// تضمين ملف الاتصال بقاعدة البيانات
require_once 'config/config.php';

$update_success_message = "";
$update_error_message = "";

// --- معالجة طلب تغيير الحالة ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    if (isset($_POST['user_id_to_update'], $_POST['new_status'])) {
        $user_id_to_update = intval($_POST['user_id_to_update']);
        $new_status = $_POST['new_status'];

        // التحقق من أن الحالة الجديدة هي واحدة من القيم المسموح بها في ENUM
        $allowed_statuses = ['pending_approval', 'active', 'suspended', 'rejected'];
        if (in_array($new_status, $allowed_statuses)) {
            
            $sql_update_status = "UPDATE cafe_owners SET status = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update_status);

            if ($stmt_update) {
                $stmt_update->bind_param("si", $new_status, $user_id_to_update);
                if ($stmt_update->execute()) {
                    $update_success_message = "تم تحديث حالة المستخدم بنجاح!";
                } else {
                    $update_error_message = "خطأ في تحديث الحالة: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $update_error_message = "خطأ في إعداد استعلام التحديث: " . $conn->error;
            }
        } else {
            $update_error_message = "الحالة المحددة غير صالحة.";
        }
    } else {
        $update_error_message = "بيانات غير كافية لتحديث الحالة.";
    }
}
// --- نهاية معالجة طلب تغيير الحالة ---


// --- جلب بيانات أصحاب الكافيهات (يتم جلبها مجددًا بعد أي تحديث محتمل) ---
$cafe_owners = []; // مصفوفة لتخزين المستخدمين
$sql_get_owners = "SELECT id, cafe_name, owner_name, email, phone_number, status, created_at FROM cafe_owners ORDER BY created_at DESC";
$result_owners = $conn->query($sql_get_owners);

if ($result_owners && $result_owners->num_rows > 0) {
    while ($row = $result_owners->fetch_assoc()) {
        $cafe_owners[] = $row;
    }
}
// لا نغلق الاتصال هنا $conn->close(); إذا كنا سنحتاجه لاحقًا في الصفحة.
// في هذه الحالة، تم إغلاقه في نهاية الكود.
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المدير - إدارة الكافيهات</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f0f2f5; color: #333; }
        .navbar { background-color: #c82333; padding: 10px 20px; color: white; overflow: hidden; }
        .navbar a { float: right; display: block; color: white; text-align: center; padding: 14px 16px; text-decoration: none; font-size: 17px; }
        .navbar a:hover { background-color: #a71d2a; }
        .navbar .logout { float: left; }
        .navbar .site-title { float: right; padding: 14px 0; font-size: 18px; font-weight: bold; }

        .container { padding: 20px; }
        .content-header { margin-bottom: 20px; padding-bottom:10px; border-bottom: 1px solid #ddd; }
        .content-header h1 { color: #1c1e21; margin-top:0; }
        .main-content { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { border: 1px solid #ddd; padding: 10px; text-align: right; vertical-align: middle;}
        table th { background-color: #f8f9fa; font-weight: 600; }
        table tr:nth-child(even) { background-color: #f2f2f2; }
        .status-pending { color: #b08f00; font-weight: bold; background-color: #fff3cd; padding: 3px 7px; border-radius: 4px; } /* أصفر */
        .status-active { color: #155724; font-weight: bold; background-color: #d4edda; padding: 3px 7px; border-radius: 4px;} /* أخضر */
        .status-suspended { color: #721c24; font-weight: bold; background-color: #f8d7da; padding: 3px 7px; border-radius: 4px;} /* أحمر */
        .status-rejected { color: #383d41; font-weight: bold; background-color: #e2e3e5; padding: 3px 7px; border-radius: 4px;} /* رمادي */
        
        .actions-form select { padding: 6px; border-radius: 4px; border: 1px solid #ccc; margin-left: 10px; }
        .actions-form input[type="submit"] {
            background-color: #007bff; color: white; border: none; padding: 7px 12px; border-radius: 4px; cursor: pointer; font-size: 14px;
        }
        .actions-form input[type="submit"]:hover { background-color: #0056b3; }

        .message { padding: 12px; margin-bottom: 20px; border-radius: 6px; text-align: right; font-size: 15px; }
        .message.success { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .message.error { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="logout_admin.php" class="logout">تسجيل الخروج</a>
        <div class="site-title">لوحة تحكم المدير</div>
    </div>

    <div class="container">
        <div class="content-header">
            <h1>أهلاً بك، <?php echo $admin_username; ?>!</h1>
        </div>

        <div class="main-content">
            <h2>إدارة حسابات أصحاب الكافيهات</h2>

            <?php if (!empty($update_success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($update_success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($update_error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($update_error_message); ?></p></div>
            <?php endif; ?>
            
            <?php if (empty($cafe_owners)): ?>
                <p>لا يوجد حاليًا أي أصحاب كافيهات مسجلين.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>معرف</th>
                            <th>اسم الكافيه</th>
                            <th>المالك</th>
                            <th>البريد</th>
                            <th>الهاتف</th>
                            <th>الحالة الحالية</th>
                            <th>تاريخ التسجيل</th>
                            <th>تغيير الحالة إلى</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $statuses = ['pending_approval' => 'قيد المراجعة', 'active' => 'نشط', 'suspended' => 'معلق', 'rejected' => 'مرفوض'];
                        foreach ($cafe_owners as $owner): 
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($owner['id']); ?></td>
                                <td><?php echo htmlspecialchars($owner['cafe_name']); ?></td>
                                <td><?php echo htmlspecialchars($owner['owner_name']); ?></td>
                                <td><?php echo htmlspecialchars($owner['email']); ?></td>
                                <td><?php echo htmlspecialchars($owner['phone_number']); ?></td>
                                <td>
                                    <?php 
                                    $current_status_key = htmlspecialchars($owner['status']);
                                    $status_display_text = isset($statuses[$current_status_key]) ? $statuses[$current_status_key] : $current_status_key;
                                    $status_class = 'status-' . str_replace('_', '-', $current_status_key); // e.g., status-pending-approval
                                    echo "<span class='{$status_class}'>{$status_display_text}</span>";
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($owner['created_at']))); ?></td>
                                <td class="actions-form">
                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" style="display:inline;">
                                        <input type="hidden" name="user_id_to_update" value="<?php echo htmlspecialchars($owner['id']); ?>">
                                        <select name="new_status">
                                            <?php foreach ($statuses as $status_key => $status_value): ?>
                                                <option value="<?php echo $status_key; ?>" <?php if ($owner['status'] === $status_key) echo 'selected'; ?>>
                                                    <?php echo $status_value; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="submit" name="update_status" value="تحديث">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <?php
            // إغلاق الاتصال إذا تم فتحه ولم يتم إغلاقه بعد
            if (isset($conn) && $conn instanceof mysqli) {
                $conn->close();
            }
            ?>
        </div>
    </div>

</body>
</html>

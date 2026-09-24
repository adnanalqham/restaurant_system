<?php
require_once __DIR__ . '/config/db.php';

echo '<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تحديث قاعدة البيانات</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; text-align: center; padding-top: 50px; }
        .card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: inline-block; max-width: 600px; }
        h2 { margin-bottom: 20px; }
        .success { color: #28a745; font-weight: bold; }
        .info { color: #17a2b8; }
        .error { color: #dc3545; }
        .btn { display: inline-block; background: #007bff; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 15px; }
        ul { text-align: right; list-style: none; padding: 0; }
        li { padding: 6px 0; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
<div class="card">';

try {
    $db = getDB();

    // 1. Create departments table
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name_ar VARCHAR(100) NOT NULL,
            name_en VARCHAR(100) NOT NULL,
            printer_id INT DEFAULT NULL,
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (printer_id) REFERENCES printers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo '<p class="success">✅ تم إنشاء جدول الأقسام (departments) بنجاح.</p>';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo '<p class="info">ℹ️ جدول الأقسام موجود مسبقاً.</p>';
        } else {
            throw $e;
        }
    }

    // 2. Add department_id to categories
    try {
        $db->exec("ALTER TABLE categories ADD COLUMN department_id INT DEFAULT NULL AFTER print_group_en");
        echo '<p class="success">✅ تم إضافة عمود department_id لجدول الفئات بنجاح.</p>';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo '<p class="info">ℹ️ عمود department_id موجود مسبقاً في جدول الفئات.</p>';
        } else {
            throw $e;
        }
    }

    // 3. Add is_active to printers
    try {
        $db->exec("ALTER TABLE printers ADD COLUMN is_active TINYINT DEFAULT 1 AFTER type");
        echo '<p class="success">✅ تم إضافة عمود is_active لجدول الطابعات بنجاح.</p>';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo '<p class="info">ℹ️ عمود is_active موجود مسبقاً في جدول الطابعات.</p>';
        } else {
            throw $e;
        }
    }

    // 4. Add status/attempts/error columns to print_queue for retry support
    try {
        $db->exec("ALTER TABLE print_queue 
                    ADD COLUMN department_id INT DEFAULT NULL AFTER station_user_id,
                    ADD COLUMN status ENUM('pending','printing','success','failed') DEFAULT 'pending' AFTER department_id,
                    ADD COLUMN attempts INT DEFAULT 0 AFTER status,
                    ADD COLUMN error_message TEXT DEFAULT NULL AFTER attempts,
                    ADD COLUMN printer_ip VARCHAR(45) DEFAULT NULL AFTER error_message");
        echo '<p class="success">✅ تم تحديث جدول print_queue لدعم إعادة المحاولة والتسجيل.</p>';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo '<p class="info">ℹ️ أعمدة التحديث موجودة مسبقاً في print_queue.</p>';
        } else {
            throw $e;
        }
    }

    // 5. Add enable_department_printing setting
    try {
        $db->exec("INSERT INTO settings (`key`, `value`) VALUES ('enable_department_printing', '0') ON DUPLICATE KEY UPDATE `key`=`key`");
        echo '<p class="success">✅ تم إضافة إعداد enable_department_printing.</p>';
    } catch (Exception $e) {
        echo '<p class="info">ℹ️ ' . $e->getMessage() . '</p>';
    }

    // 6. Add request_id column for idempotency
    try {
        $db->exec("ALTER TABLE print_queue ADD COLUMN request_id VARCHAR(36) DEFAULT NULL AFTER printer_ip");
        echo '<p class="success">✅ تم إضافة عمود request_id لجدول print_queue.</p>';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo '<p class="info">ℹ️ عمود request_id موجود مسبقاً.</p>';
        } else {
            throw $e;
        }
    }

    // 7. Add UNIQUE index on request_id
    try {
        $db->exec("ALTER TABLE print_queue ADD UNIQUE INDEX idx_request_id (request_id)");
        echo '<p class="success">✅ تم إضافة قيد UNIQUE على request_id.</p>';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo '<p class="info">ℹ️ قيد UNIQUE موجود مسبقاً.</p>';
        } else {
            throw $e;
        }
    }

    // 8. Add printer_type column to print_queue
    try {
        $db->exec("ALTER TABLE print_queue ADD COLUMN printer_type VARCHAR(50) DEFAULT NULL AFTER printer_ip");
        echo '<p class="success">✅ تم إضافة عمود printer_type لجدول print_queue.</p>';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo '<p class="info">ℹ️ عمود printer_type موجود مسبقاً.</p>';
        } else {
            throw $e;
        }
    }

    // 9. Add esc_data column (base64 pre-built ESC/POS bytes)
    try {
        $db->exec("ALTER TABLE print_queue ADD COLUMN esc_data MEDIUMTEXT DEFAULT NULL AFTER printer_type");
        echo '<p class="success">✅ تم إضافة عمود esc_data لجدول print_queue.</p>';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo '<p class="info">ℹ️ عمود esc_data موجود مسبقاً.</p>';
        } else {
            throw $e;
        }
    }

    // 10. Add started_at column for claim tracking
    try {
        $db->exec("ALTER TABLE print_queue ADD COLUMN started_at DATETIME DEFAULT NULL AFTER created_at");
        echo '<p class="success">✅ تم إضافة عمود started_at لجدول print_queue.</p>';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo '<p class="info">ℹ️ عمود started_at موجود مسبقاً.</p>';
        } else {
            throw $e;
        }
    }

    echo '<h2 class="success">🎉 اكتمل التحديث بنجاح!</h2>';

} catch (Exception $e) {
    echo '<h2 class="error">❌ فشل التحديث!</h2>';
    echo '<p>حدث خطأ: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '
    <hr style="border:0; border-top:1px solid #eee; margin:20px 0;">
    <p style="color:#e74c3c; font-weight:bold; font-size:0.9rem;">⚠️ يرجى حذف هذا الملف بعد الانتهاء.</p>
    <a href="admin/departments.php" class="btn">الذهاب لإدارة الأقسام</a>
</div>
</body>
</html>';

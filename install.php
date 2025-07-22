<?php
/**
 * Parfüm POS Sistemi - Quraşdırma Sihirbazı (Düzəldilmiş)
 * Yaradıldığı tarix: 2025-07-21 12:06:16
 * Müəllif: Kodaz-az
 */

// Error reporting aktiv et
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if already installed
if (file_exists('config/config.php') && !isset($_GET['reinstall'])) {
    header('Location: index.php');
    exit;
}

$step = intval($_GET['step'] ?? 1);
$maxStep = 5;

// Helper functions definitions
function performSystemChecks() {
    return [
        [
            'name' => 'PHP Versiyası (≥ 7.4)',
            'status' => version_compare(PHP_VERSION, '7.4.0', '>=')
        ],
        [
            'name' => 'PDO MySQL Extension',
            'status' => extension_loaded('pdo') && extension_loaded('pdo_mysql')
        ],
        [
            'name' => 'JSON Extension',
            'status' => extension_loaded('json')
        ],
        [
            'name' => 'MBString Extension',
            'status' => extension_loaded('mbstring')
        ],
        [
            'name' => 'FileInfo Extension',
            'status' => extension_loaded('fileinfo')
        ],
        [
            'name' => 'OpenSSL Extension',
            'status' => extension_loaded('openssl')
        ],
        [
            'name' => 'GD Extension (optional)',
            'status' => extension_loaded('gd')
        ],
        [
            'name' => 'Config Directory Writable',
            'status' => is_writable('.') || (is_dir('config') && is_writable('config'))
        ],
        [
            'name' => 'Uploads Directory',
            'status' => is_dir('uploads') ? is_writable('uploads') : mkdir('uploads', 0755, true)
        ],
        [
            'name' => 'Current Directory Writable',
            'status' => is_writable('.')
        ]
    ];
}

function createConfigFile($dbConfig, $systemSettings) {
    $configContent = "<?php
/**
 * Parfüm POS Sistemi - Konfiqurasiya Faylı
 * Yaradıldığı tarix: " . date('Y-m-d H:i:s') . "
 * Avtomatik yaradılıb - Quraşdırma Sihirbazı
 */

// Məlumat Bazası Konfiqurasiyası
define('DB_HOST', '{$dbConfig['host']}');
define('DB_NAME', '{$dbConfig['name']}');
define('DB_USER', '{$dbConfig['user']}');
define('DB_PASS', '{$dbConfig['pass']}');
define('DB_PORT', '{$dbConfig['port']}');
define('DB_CHARSET', 'utf8mb4');

// Tətbiq Konfiqurasiyası
define('APP_VERSION', '1.0.0');
define('ENVIRONMENT', 'production');

// PWA Konfiqurasiyası
define('PWA_NAME', '{$systemSettings['app_name']}');
define('PWA_SHORT_NAME', 'PerfumePOS');
define('PWA_DESCRIPTION', 'Parfüm satış və inventar idarəetmə sistemi');
define('PWA_THEME_COLOR', '#667eea');
define('PWA_BACKGROUND_COLOR', '#ffffff');

// Sistem Konfiqurasiyası
\$protocol = isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
\$host = \$_SERVER['HTTP_HOST'] ?? 'localhost';
\$path = dirname(\$_SERVER['SCRIPT_NAME']);
\$path = \$path === '/' ? '' : \$path;
define('BASE_URL', \$protocol . '://' . \$host . \$path . '/');
define('ROOT_PATH', __DIR__);

// Təhlükəsizlik
define('SECRET_KEY', '" . bin2hex(random_bytes(32)) . "');
define('CSRF_TOKEN_NAME', 'csrf_token');

// Saat Qurşağı
date_default_timezone_set('{$systemSettings['timezone']}');

// Xəta Hesabatı
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . '/logs/error.log');
}

// Session Konfiqurasiyası
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset(\$_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);

// Upload Konfiqurasiyası
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Sistem Email
define('SYSTEM_EMAIL', '{$systemSettings['company_email']}');
define('SYSTEM_NAME', '{$systemSettings['app_name']}');

?>";
    
    $configDir = 'config';
    if (!is_dir($configDir)) {
        if (!mkdir($configDir, 0755, true)) {
            throw new Exception('Config qovluğu yaradıla bilmədi');
        }
    }
    
    $configFile = $configDir . '/config.php';
    
    if (file_put_contents($configFile, $configContent) === false) {
        throw new Exception('Config faylı yaradıla bilmədi');
    }
    
    // Create .htaccess for config directory
    $htaccessContent = "Order Deny,Allow\nDeny from all";
    file_put_contents($configDir . '/.htaccess', $htaccessContent);
    
    return true;
}

function createDatabaseTables($dbConfig) {
    try {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Basic SQL for tables
        $sqlStatements = [
            // Users table
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                phone VARCHAR(20),
                role ENUM('admin', 'manager', 'seller') DEFAULT 'seller',
                base_salary DECIMAL(10,2) DEFAULT 0.00,
                commission_rate DECIMAL(5,2) DEFAULT 0.00,
                avatar VARCHAR(255),
                is_active TINYINT(1) DEFAULT 1,
                last_login DATETIME,
                last_activity DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            
            // Products table
            "CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                brand VARCHAR(100),
                category VARCHAR(100),
                barcode VARCHAR(50) UNIQUE,
                price DECIMAL(10,2) NOT NULL,
                cost_price DECIMAL(10,2) DEFAULT 0.00,
                stock_quantity INT DEFAULT 0,
                min_stock INT DEFAULT 5,
                description TEXT,
                size VARCHAR(50),
                gender ENUM('male', 'female', 'unisex') DEFAULT 'unisex',
                fragrance_family VARCHAR(100),
                top_notes TEXT,
                middle_notes TEXT,
                base_notes TEXT,
                image VARCHAR(255),
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            
            // Sales table
            "CREATE TABLE IF NOT EXISTS sales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sale_number VARCHAR(50) UNIQUE NOT NULL,
                user_id INT NOT NULL,
                customer_name VARCHAR(100),
                customer_phone VARCHAR(20),
                customer_email VARCHAR(100),
                subtotal DECIMAL(10,2) DEFAULT 0.00,
                discount_amount DECIMAL(10,2) DEFAULT 0.00,
                tax_amount DECIMAL(10,2) DEFAULT 0.00,
                final_amount DECIMAL(10,2) NOT NULL,
                payment_method ENUM('cash', 'card', 'transfer') DEFAULT 'cash',
                payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
                status ENUM('draft', 'completed', 'cancelled', 'refunded') DEFAULT 'completed',
                total_items INT DEFAULT 0,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            
            // Sale details table
            "CREATE TABLE IF NOT EXISTS sale_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sale_id INT NOT NULL,
                product_id INT NOT NULL,
                quantity INT NOT NULL,
                unit_price DECIMAL(10,2) NOT NULL,
                discount_rate DECIMAL(5,2) DEFAULT 0.00,
                total_price DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id)
            )",
            
            // System settings table
            "CREATE TABLE IF NOT EXISTS system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            
            // Chat rooms table
            "CREATE TABLE IF NOT EXISTS chat_rooms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                type ENUM('group', 'direct', 'channel') DEFAULT 'group',
                is_private TINYINT(1) DEFAULT 0,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            )",
            
            // Chat messages table
            "CREATE TABLE IF NOT EXISTS chat_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room_id INT,
                sender_id INT NOT NULL,
                recipient_id INT,
                message_type ENUM('text', 'image', 'file', 'audio', 'video') DEFAULT 'text',
                content TEXT NOT NULL,
                metadata JSON,
                reply_to INT,
                is_read TINYINT(1) DEFAULT 0,
                is_edited TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
                FOREIGN KEY (sender_id) REFERENCES users(id),
                FOREIGN KEY (recipient_id) REFERENCES users(id)
            )",
            
            // Notifications table
            "CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type ENUM('info', 'success', 'warning', 'error', 'new_sale', 'low_stock', 'new_message', 'incoming_call', 'salary_request', 'system') DEFAULT 'info',
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                data JSON,
                sender_id INT,
                priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
                is_read TINYINT(1) DEFAULT 0,
                read_at DATETIME,
                expires_at DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (sender_id) REFERENCES users(id)
            )",
            
            // Salary months table
            "CREATE TABLE IF NOT EXISTS salary_months (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                month TINYINT NOT NULL,
                year SMALLINT NOT NULL,
                base_salary DECIMAL(10,2) DEFAULT 0.00,
                commission_amount DECIMAL(10,2) DEFAULT 0.00,
                bonus_amount DECIMAL(10,2) DEFAULT 0.00,
                penalty_amount DECIMAL(10,2) DEFAULT 0.00,
                advance_amount DECIMAL(10,2) DEFAULT 0.00,
                total_salary DECIMAL(10,2) NOT NULL,
                is_paid TINYINT(1) DEFAULT 0,
                paid_at DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                UNIQUE KEY unique_user_month (user_id, month, year)
            )",
            
            // Salary requests table
            "CREATE TABLE IF NOT EXISTS salary_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                request_type ENUM('advance', 'bonus', 'adjustment') DEFAULT 'advance',
                amount DECIMAL(10,2) NOT NULL,
                reason TEXT,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                rejection_reason TEXT,
                processed_at DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )"
        ];
        
        foreach ($sqlStatements as $sql) {
            $pdo->exec($sql);
        }
        
        return true;
        
    } catch (Exception $e) {
        throw new Exception('Məlumat bazası cədvəlləri yaradıla bilmədi: ' . $e->getMessage());
    }
}

function createAdminUser($dbConfig, $adminData) {
    try {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $hashedPassword = password_hash($adminData['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password, full_name, role, is_active, created_at) 
                VALUES (?, ?, ?, ?, 'admin', 1, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $adminData['username'],
            $adminData['email'],
            $hashedPassword,
            $adminData['full_name']
        ]);
        
        return $result;
        
    } catch (Exception $e) {
        throw new Exception('Admin istifadəçi yaradıla bilmədi: ' . $e->getMessage());
    }
}

function createDefaultSettings($dbConfig, $systemSettings) {
    try {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $settings = [
            ['app_name', $systemSettings['app_name']],
            ['company_name', $systemSettings['company_name']],
            ['company_email', $systemSettings['company_email']],
            ['company_phone', $systemSettings['company_phone']],
            ['timezone', $systemSettings['timezone']],
            ['currency', $systemSettings['currency']],
            ['language', $systemSettings['language']],
            ['tax_rate', '18'],
            ['default_commission_rate', '5'],
            ['min_stock_alert', '5'],
            ['installation_date', date('Y-m-d H:i:s')],
            ['installation_version', '1.0.0']
        ];
        
        $sql = "INSERT INTO system_settings (setting_key, setting_value, created_at) VALUES (?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        
        foreach ($settings as $setting) {
            if (!empty($setting[1])) {
                $stmt->execute($setting);
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        throw new Exception('Standart tənzimləmələr yaradıla bilmədi: ' . $e->getMessage());
    }
}

function createDirectories() {
    $directories = [
        'uploads',
        'uploads/avatars',
        'uploads/products',
        'uploads/chat',
        'backups',
        'logs',
        'cache'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Exception("Qovluq yaradıla bilmədi: {$dir}");
            }
        }
        
        // Create index.html for security
        $indexFile = $dir . '/index.html';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Directory access is forbidden.</h1></body></html>');
        }
    }
    
    return true;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($step) {
            case 1:
                $checks = performSystemChecks();
                $allPassed = true;
                
                foreach ($checks as $check) {
                    if (!$check['status']) {
                        $allPassed = false;
                        break;
                    }
                }
                
                if ($allPassed) {
                    $_SESSION['install_step_1'] = true;
                    header('Location: install.php?step=2');
                    exit;
                } else {
                    $_SESSION['install_error'] = 'Sistem tələbləri qarşılanmır. Xətaları düzəldib yenidən cəhd edin.';
                }
                break;
                
            case 2:
                $dbConfig = [
                    'host' => $_POST['db_host'] ?? 'localhost',
                    'name' => $_POST['db_name'] ?? 'parfum_pos',
                    'user' => $_POST['db_user'] ?? 'root',
                    'pass' => $_POST['db_pass'] ?? '',
                    'port' => $_POST['db_port'] ?? '3306'
                ];
                
                // Test database connection
                $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                
                // Create database if not exists
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // Store config in session
                $_SESSION['db_config'] = $dbConfig;
                $_SESSION['install_step_2'] = true;
                
                header('Location: install.php?step=3');
                exit;
                break;
                
            case 3:
                $adminData = [
                    'username' => $_POST['admin_username'] ?? '',
                    'email' => $_POST['admin_email'] ?? '',
                    'password' => $_POST['admin_password'] ?? '',
                    'full_name' => $_POST['admin_full_name'] ?? '',
                    'confirm_password' => $_POST['admin_confirm_password'] ?? ''
                ];
                
                // Validation
                $errors = [];
                
                if (empty($adminData['username']) || strlen($adminData['username']) < 3) {
                    $errors[] = 'İstifadəçi adı ən azı 3 simvol olmalıdır';
                }
                
                if (empty($adminData['email']) || !filter_var($adminData['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Düzgün email ünvanı daxil edin';
                }
                
                if (empty($adminData['password']) || strlen($adminData['password']) < 6) {
                    $errors[] = 'Şifrə ən azı 6 simvol olmalıdır';
                }
                
                if ($adminData['password'] !== $adminData['confirm_password']) {
                    $errors[] = 'Şifrələr uyğun gəlmir';
                }
                
                if (empty($adminData['full_name'])) {
                    $errors[] = 'Ad və soyad tələb olunur';
                }
                
                if (empty($errors)) {
                    $_SESSION['admin_data'] = $adminData;
                    $_SESSION['install_step_3'] = true;
                    
                    header('Location: install.php?step=4');
                    exit;
                } else {
                    $_SESSION['install_error'] = implode('<br>', $errors);
                }
                break;
                
            case 4:
                $systemSettings = [
                    'app_name' => $_POST['app_name'] ?? 'Parfüm POS',
                    'company_name' => $_POST['company_name'] ?? '',
                    'company_email' => $_POST['company_email'] ?? '',
                    'company_phone' => $_POST['company_phone'] ?? '',
                    'timezone' => $_POST['timezone'] ?? 'Asia/Baku',
                    'currency' => $_POST['currency'] ?? 'AZN',
                    'language' => $_POST['language'] ?? 'az'
                ];
                
                $_SESSION['system_settings'] = $systemSettings;
                $_SESSION['install_step_4'] = true;
                
                header('Location: install.php?step=5');
                exit;
                break;
                
            case 5:
                // Final installation
                $dbConfig = $_SESSION['db_config'];
                $adminData = $_SESSION['admin_data'];
                $systemSettings = $_SESSION['system_settings'];
                
                // Create config file
                createConfigFile($dbConfig, $systemSettings);
                
                // Create database tables
                createDatabaseTables($dbConfig);
                
                // Create admin user
                createAdminUser($dbConfig, $adminData);
                
                // Create default settings
                createDefaultSettings($dbConfig, $systemSettings);
                
                // Create directories
                createDirectories();
                
                // Installation complete
                $_SESSION['install_complete'] = true;
                unset($_SESSION['db_config'], $_SESSION['admin_data'], $_SESSION['system_settings']);
                
                header('Location: install.php?step=6');
                exit;
                break;
        }
        
    } catch (Exception $e) {
        $_SESSION['install_error'] = 'Quraşdırma xətası: ' . $e->getMessage();
    }
}

// Delete installer if requested
if (isset($_GET['delete_installer'])) {
    unlink(__FILE__);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parfüm POS - Quraşdırma</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 800px;
            overflow: hidden;
        }
        
        .install-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .install-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .install-header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .progress-bar {
            height: 6px;
            background: rgba(255, 255, 255, 0.3);
            margin-top: 20px;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: white;
            transition: width 0.3s ease;
            border-radius: 3px;
        }
        
        .install-body {
            padding: 40px;
        }
        
        .step-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .step-description {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .requirements-list {
            list-style: none;
        }
        
        .requirements-list li {
            padding: 10px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .requirement-name {
            font-weight: 500;
        }
        
        .requirement-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pass {
            background: #d4edda;
            color: #155724;
        }
        
        .status-fail {
            background: #f8d7da;
            color: #721c24;
        }
        
        .install-complete {
            text-align: center;
            padding: 40px 0;
        }
        
        .install-complete h2 {
            color: #28a745;
            margin-bottom: 15px;
        }
        
        .install-complete p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>🏪 Parfüm POS</h1>
            <p>Quraşdırma Sihirbazı</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= ($step / $maxStep) * 100 ?>%"></div>
            </div>
        </div>
        
        <div class="install-body">
            <?php if (isset($_SESSION['install_error'])): ?>
                <div class="alert alert-error">
                    ⚠️ <?= $_SESSION['install_error'] ?>
                </div>
                <?php unset($_SESSION['install_error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['install_success'])): ?>
                <div class="alert alert-success">
                    ✅ <?= $_SESSION['install_success'] ?>
                </div>
                <?php unset($_SESSION['install_success']); ?>
            <?php endif; ?>
            
            switch ($step): 
                
                case 1: // System Requirements ?>
                    <h2 class="step-title">Sistem Tələbləri</h2>
                    <p class="step-description">
                        Quraşdırmadan əvvəl sistem tələblərini yoxlayaq
                    </p>
                    
                    <ul class="requirements-list">
                        <?php
                        $requirements = performSystemChecks();
                        foreach ($requirements as $req):
                        ?>
                            <li>
                                <span class="requirement-name"><?= $req['name'] ?></span>
                                <span class="requirement-status status-<?= $req['status'] ? 'pass' : 'fail' ?>">
                                    <?= $req['status'] ? 'Keçdi' : 'Uğursuz' ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <form method="POST">
                        <div class="form-actions">
                            <div></div>
                            <button type="submit" class="btn">
                                Növbəti Addım →
                            </button>
                        </div>
                    </form>
                    <?php break; ?>
                
              case 2: // Database Configuration ?>
                    <h2 class="step-title">Məlumat Bazası</h2>
                    <p class="step-description">
                        Məlumat bazası bağlantı məlumatlarını daxil edin
                    </p>
                    
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="db_host">Server</label>
                                <input type="text" id="db_host" name="db_host" 
                                       value="<?= $_POST['db_host'] ?? 'localhost' ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="db_port">Port</label>
                                <input type="number" id="db_port" name="db_port" 
                                       value="<?= $_POST['db_port'] ?? '3306' ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_name">Məlumat Bazası Adı</label>
                            <input type="text" id="db_name" name="db_name" 
                                   value="<?= $_POST['db_name'] ?? 'parfum_pos' ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="db_user">İstifadəçi</label>
                                <input type="text" id="db_user" name="db_user" 
                                       value="<?= $_POST['db_user'] ?? 'root' ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="db_pass">Şifrə</label>
                                <input type="password" id="db_pass" name="db_pass" 
                                       value="<?= $_POST['db_pass'] ?? '' ?>">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="install.php?step=1" class="btn btn-secondary">← Geri</a>
                            <button type="submit" class="btn">Test Et →</button>
                        </div>
                    </form>
                    <?php break; ?>
                
                case 3: // Admin Account ?>
                    <h2 class="step-title">Admin Hesabı</h2>
                    <p class="step-description">
                        Sistem administrator hesabını yaradın
                    </p>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="admin_full_name">Ad və Soyad</label>
                            <input type="text" id="admin_full_name" name="admin_full_name" 
                                   value="<?= $_POST['admin_full_name'] ?? '' ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="admin_username">İstifadəçi Adı</label>
                                <input type="text" id="admin_username" name="admin_username" 
                                       value="<?= $_POST['admin_username'] ?? 'admin' ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="admin_email">Email</label>
                                <input type="email" id="admin_email" name="admin_email" 
                                       value="<?= $_POST['admin_email'] ?? '' ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="admin_password">Şifrə</label>
                                <input type="password" id="admin_password" name="admin_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="admin_confirm_password">Şifrə Təkrarı</label>
                                <input type="password" id="admin_confirm_password" name="admin_confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="install.php?step=2" class="btn btn-secondary">← Geri</a>
                            <button type="submit" class="btn">Növbəti →</button>
                        </div>
                    </form>
                    <?php break; ?>
                
                 case 4: // System Settings ?>
                    <h2 class="step-title">Sistem Tənzimləmələri</h2>
                    <p class="step-description">
                        Əsas sistem tənzimləmələrini daxil edin
                    </p>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="app_name">Tətbiq Adı</label>
                            <input type="text" id="app_name" name="app_name" 
                                   value="<?= $_POST['app_name'] ?? 'Parfüm POS' ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="company_name">Şirkət Adı</label>
                            <input type="text" id="company_name" name="company_name" 
                                   value="<?= $_POST['company_name'] ?? '' ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="company_email">Email</label>
                                <input type="email" id="company_email" name="company_email" 
                                       value="<?= $_POST['company_email'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="company_phone">Telefon</label>
                                <input type="tel" id="company_phone" name="company_phone" 
                                       value="<?= $_POST['company_phone'] ?? '' ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="timezone">Saat Qurşağı</label>
                                <select id="timezone" name="timezone">
                                    <option value="Asia/Baku">Asia/Baku (GMT+4)</option>
                                    <option value="UTC">UTC (GMT+0)</option>
                                    <option value="Europe/Istanbul">Europe/Istanbul (GMT+3)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="currency">Valyuta</label>
                                <select id="currency" name="currency">
                                    <option value="AZN">Azərbaycan Manatı (₼)</option>
                                    <option value="USD">US Dollar ($)</option>
                                    <option value="EUR">Euro (€)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="install.php?step=3" class="btn btn-secondary">← Geri</a>
                            <button type="submit" class="btn">Növbəti →</button>
                        </div>
                    </form>
                    <?php break; ?>
                
               case 5: // Installation ?>
                    <h2 class="step-title">Quraşdırma</h2>
                    <p class="step-description">
                        Hər şey hazırdır! Quraşdırmanı başladın.
                    </p>
                    
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h4 style="margin-bottom: 15px;">📋 Quraşdırma Xülasəsi:</h4>
                        <ul style="list-style: none; line-height: 1.8;">
                            <li><strong>💾 Məlumat Bazası:</strong> <?= $_SESSION['db_config']['name'] ?? 'N/A' ?></li>
                            <li><strong>👤 Admin:</strong> <?= $_SESSION['admin_data']['full_name'] ?? 'N/A' ?></li>
                            <li><strong>🏪 Tətbiq:</strong> <?= $_SESSION['system_settings']['app_name'] ?? 'N/A' ?></li>
                            <li><strong>🏢 Şirkət:</strong> <?= $_SESSION['system_settings']['company_name'] ?? 'N/A' ?></li>
                        </ul>
                    </div>
                    
                    <form method="POST">
                        <div class="form-actions">
                            <a href="install.php?step=4" class="btn btn-secondary">← Geri</a>
                            <button type="submit" class="btn btn-success">🚀 Quraşdır</button>
                        </div>
                    </form>
                    <?php break; ?>
                
               case 6: // Complete ?>
                    <div class="install-complete">
                        <h2>🎉 Quraşdırma Tamamlandı!</h2>
                        <p>
                            Parfüm POS sistemi uğurla quraşdırıldı və istifadəyə hazırdır.<br>
                            İndi sistemə giriş edə bilərsiniz.
                        </p>
                        
                        <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: left;">
                            <h4 style="color: #1976d2; margin-bottom: 10px;">🛡️ Təhlükəsizlik:</h4>
                            <ul style="color: #1565c0; line-height: 1.6;">
                                <li>Bu install.php faylını silin</li>
                                <li>Qovluq icazələrini yoxlayın</li>
                                <li>HTTPS təyin edin</li>
                            </ul>
                        </div>
                        
                        <a href="index.php" class="btn btn-success" style="margin-right: 15px;">
                            🔐 Sistemə Gir
                        </a>
                        
                        <a href="?delete_installer" class="btn btn-secondary" 
                           onclick="return confirm('Install faylını silək?')">
                            🗑️ Install Faylını Sil
                        </a>
                    </div>
                    <?php break; ?>
            endswitch; 
        </div>
    </div>
</body>
</html>
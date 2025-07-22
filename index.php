<?php
/**
 * Parfüm POS - Tam Aktiv Sistem (Header/Footer ilə)
 * Kodaz-az - 2025-07-21 13:47:37 (UTC)
 * Login: Kodaz-az
 */

// Config yüklə
if (!file_exists('config/config.php')) {
    die('<h1>Xəta</h1><p>Config faylı tapılmadı. Zəhmət olmasa əvvəlcə konfiqurasiya faylını yaradın.</p>');
}

require_once 'config/config.php';
require_once 'includes/Database.php';

// Session başlat
session_start();

// Error reporting (development mode)
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Helper functions yüklə
if (file_exists('includes/functions.php')) {
    require_once 'includes/functions.php';
}

// Routing
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$allowedPages = ['dashboard', 'login', 'register', 'logout', 'products', 'sales', 'chat', 'calls', 'reports', 'users', 'settings', 'profile', 'salary'];

// Logout
if ($page === 'logout') {
    try {
        $db = Database::getInstance();
        if (isset($_SESSION['user_id'])) {
            $db->update("UPDATE users SET last_activity = NOW() WHERE id = ?", [$_SESSION['user_id']]);
        }
    } catch (Exception $e) {
        // Silent fail
    }
    
    session_destroy();
    header('Location: ?page=login');
    exit;
}

// Login yoxla
$isLoggedIn = isset($_SESSION['user_id']);

// Login olmayan istifadəçiləri yönləndir
if (!$isLoggedIn && !in_array($page, ['login', 'register'])) {
    header('Location: ?page=login');
    exit;
}

// Login olan istifadəçiləri dashboard-a yönləndir (login/register səhifələrindən)
if ($isLoggedIn && in_array($page, ['login', 'register'])) {
    header('Location: ?page=dashboard');
    exit;
}

// Update user activity
if ($isLoggedIn) {
    try {
        $db = Database::getInstance();
        $db->update("UPDATE users SET last_activity = NOW() WHERE id = ?", [$_SESSION['user_id']]);
    } catch (Exception $e) {
        // Silent fail
    }
}

// Login idarəetməsi
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $_SESSION['error_message'] = "İstifadəçi adı və şifrə tələb olunur!";
    } else {
        try {
            $db = Database::getInstance();
            $user = $db->selectOne(
                "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1", 
                [$username, $username]
            );
            
            if ($user) {
                $passwordValid = password_verify($password, $user['password']);
                $plainTextValid = ($password === 'admin123' && $user['username'] === 'admin');
                
                if ($passwordValid || $plainTextValid) {
                    if ($plainTextValid && !$passwordValid) {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $db->update("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $user['id']]);
                    }
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['email'];
                    
                    $db->update("UPDATE users SET last_login = NOW(), last_activity = NOW() WHERE id = ?", [$user['id']]);
                    
                    $_SESSION['success_message'] = "Xoş gəlmisiniz, " . $user['full_name'] . "!";
                    header('Location: ?page=dashboard');
                    exit;
                } else {
                    $_SESSION['error_message'] = "Yanlış şifrə!";
                }
            } else {
                $_SESSION['error_message'] = "İstifadəçi tapılmadı!";
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Sistem xətası: " . $e->getMessage();
        }
    }
}

// Qeydiyyat idarəetməsi
if ($page === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    
    $errors = [];
    
    if (empty($fullName)) $errors[] = "Ad və soyad tələb olunur";
    if (empty($username) || strlen($username) < 3) $errors[] = "İstifadəçi adı ən azı 3 simvol olmalıdır";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Düzgün email daxil edin";
    if (empty($password) || strlen($password) < 6) $errors[] = "Şifrə ən azı 6 simvol olmalıdır";
    if ($password !== $confirmPassword) $errors[] = "Şifrələr uyğun gəlmir";
    
    if (empty($errors)) {
        try {
            $db = Database::getInstance();
            
            $existingUser = $db->selectOne(
                "SELECT id FROM users WHERE username = ? OR email = ?", 
                [$username, $email]
            );
            
            if ($existingUser) {
                $errors[] = "Bu istifadəçi adı və ya email artıq mövcuddur";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $userId = $db->insert(
                    "INSERT INTO users (username, email, password, full_name, phone, role, is_active, created_at) 
                     VALUES (?, ?, ?, ?, ?, 'seller', 1, NOW())",
                    [$username, $email, $hashedPassword, $fullName, $phone]
                );
                
                if ($userId) {
                    $_SESSION['success_message'] = "Qeydiyyat uğurlu! İndi daxil ola bilərsiniz.";
                    header('Location: ?page=login');
                    exit;
                } else {
                    $errors[] = "Qeydiyyat zamanı xəta baş verdi";
                }
            }
        } catch (Exception $e) {
            $errors[] = "Sistem xətası: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
}

// Auth pages (login/register) - without header/footer
if (in_array($page, ['login', 'register'])) {
    ?>
    <!DOCTYPE html>
    <html lang="az">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $page === 'login' ? 'Giriş' : 'Qeydiyyat' ?> - <?= PWA_NAME ?></title>
        
        <!-- PWA Meta Tags -->
        <link rel="manifest" href="<?= BASE_URL ?>manifest.json">
        <meta name="theme-color" content="<?= PWA_THEME_COLOR ?>">
        
        <!-- Icons -->
        <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/icons/favicon-32x32.png">
        
        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        
        <style>
            :root {
                --primary-color: #667eea;
                --secondary-color: #764ba2;
                --success-color: #28a745;
                --danger-color: #dc3545;
                --white: #ffffff;
            }
            
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .auth-container {
                background: var(--white);
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.2);
                width: 100%;
                max-width: 450px;
                text-align: center;
            }
            
            .logo { 
                font-size: 4rem; 
                margin-bottom: 20px;
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            .auth-title { 
                color: #333; 
                margin-bottom: 10px;
                font-size: 2rem;
                font-weight: bold;
            }
            
            .auth-subtitle { 
                color: #666; 
                margin-bottom: 30px; 
                font-size: 14px; 
            }
            
            .form-group { 
                margin-bottom: 20px; 
                text-align: left; 
            }
            
            .form-group label { 
                display: block; 
                margin-bottom: 8px; 
                font-weight: 600; 
                color: #333; 
                font-size: 14px;
            }
            
            .form-group input,
            .form-group select { 
                width: 100%; 
                padding: 15px 20px; 
                border: 2px solid #e1e5e9; 
                border-radius: 12px; 
                font-size: 16px;
                transition: all 0.3s ease;
                background: #f8f9fa;
            }
            
            .form-group input:focus,
            .form-group select:focus { 
                outline: none; 
                border-color: var(--primary-color);
                background: var(--white);
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            .btn {
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                color: var(--white);
                border: none;
                padding: 15px 30px;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
                text-align: center;
                width: 100%;
                margin-bottom: 15px;
            }
            
            .btn:hover { 
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            }
            
            .btn-secondary {
                background: #6c757d;
                color: var(--white);
                text-decoration: none;
                display: inline-block;
                text-align: center;
                padding: 12px 25px;
                border-radius: 12px;
                transition: all 0.3s ease;
                margin-top: 10px;
            }
            
            .btn-secondary:hover { 
                transform: translateY(-1px);
                background: #5a6268;
            }
            
            .alert {
                padding: 15px 20px;
                border-radius: 12px;
                margin-bottom: 20px;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 10px;
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
            
            .demo-info { 
                margin-top: 20px; 
                padding: 20px; 
                background: linear-gradient(135deg, #e3f2fd, #e1f5fe); 
                border-radius: 12px; 
                font-size: 14px; 
                color: #1565c0;
                border: 1px solid #81d4fa;
            }
            
            .auth-switch {
                margin-top: 25px;
                padding-top: 20px;
                border-top: 1px solid #e1e5e9;
                font-size: 14px;
                color: #666;
            }
            
            .auth-switch a {
                color: var(--primary-color);
                text-decoration: none;
                font-weight: 600;
            }
            
            .auth-switch a:hover {
                text-decoration: underline;
            }
            
            .form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            @media (max-width: 768px) {
                .form-row { grid-template-columns: 1fr; }
                .auth-container { padding: 30px 20px; margin: 10px; }
                .logo { font-size: 3rem; }
            }
        </style>
    </head>
    <body>
        <div class="auth-container">
            <div class="logo"><i class="fas fa-store"></i></div>
            <h1 class="auth-title"><?= PWA_NAME ?></h1>
            <p class="auth-subtitle">Kodaz.az Parfüm Satış və İnventar Sistemi</p>
            
            <?php if (!empty($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $_SESSION['error_message'] ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <?php if (!empty($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $_SESSION['success_message'] ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if ($page === 'login'): ?>
                <h2 style="margin-bottom: 25px; color: #333; font-size: 1.5rem;">
                    <i class="fas fa-sign-in-alt"></i> Sistemə Daxil Olun
                </h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> İstifadəçi Adı və ya Email:</label>
                        <input type="text" name="username" value="admin" required placeholder="İstifadəçi adınızı daxil edin">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Şifrə:</label>
                        <input type="password" name="password" value="admin123" required placeholder="Şifrənizi daxil edin">
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Daxil Ol
                    </button>
                </form>
                
                <div class="demo-info">
                    <strong><i class="fas fa-info-circle"></i> Demo Giriş Məlumatları:</strong><br><br>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; text-align: left;">
                        <div><strong>İstifadəçi:</strong></div><div><code>admin</code></div>
                        <div><strong>Şifrə:</strong></div><div><code>admin123</code></div>
                    </div>
                    <small style="display: block; margin-top: 10px; opacity: 0.8;">
                        Bu məlumatlarla sistemi test edə bilərsiniz
                    </small>
                </div>
                
                <div class="auth-switch">
                    <i class="fas fa-user-plus"></i> Hesabınız yoxdur? 
                    <a href="?page=register">Qeydiyyatdan keçin</a>
                </div>
                
            <?php elseif ($page === 'register'): ?>
                <h2 style="margin-bottom: 25px; color: #333; font-size: 1.5rem;">
                    <i class="fas fa-user-plus"></i> Qeydiyyatdan Keçin
                </h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Ad və Soyad:</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required placeholder="Tam adınızı daxil edin">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> İstifadəçi Adı:</label>
                            <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required placeholder="İstifadəçi adı">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email:</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required placeholder="Email ünvanınız">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Telefon (İxtiyari):</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="+994xxxxxxxxx">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Şifrə:</label>
                            <input type="password" name="password" required placeholder="Minimum 6 simvol">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Şifrə Təkrarı:</label>
                            <input type="password" name="confirm_password" required placeholder="Şifrəni təkrar edin">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-user-plus"></i> Qeydiyyatdan Keç
                    </button>
                </form>
                
                <div class="auth-switch">
                    <i class="fas fa-sign-in-alt"></i> Artıq hesabınız var? 
                    <a href="?page=login">Daxil olun</a>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e1e5e9; font-size: 12px; color: #adb5bd;">
                <i class="fas fa-code"></i> Kodaz.az &copy; <?= date('Y') ?> | 
                <i class="fas fa-shield-alt"></i> Təhlükəsiz Giriş
            </div>
        </div>
        
        <script>
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // PWA Service Worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= BASE_URL ?>sw.js')
                .catch(error => console.log('SW registration failed'));
        }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Dashboard pages - with header/footer
if ($isLoggedIn) {
    // Include page content
    $pageFile = 'pages/' . $page . '.php';
    
    if (file_exists($pageFile)) {
        include $pageFile;
    } else {
        // 404 page
        $pageTitle = "Səhifə Tapılmadı - " . PWA_NAME;
        include 'includes/header.php';
        ?>
        <div class="content-box" style="text-align: center; padding: 60px 40px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: #ffc107; margin-bottom: 20px;"></i>
            <h2 style="color: #333; margin-bottom: 15px;">404 - Səhifə Tapılmadı</h2>
            <p style="color: #666; margin-bottom: 30px;">
                Axtardığınız səhifə mövcud deyil və ya silinib.
            </p>
            
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="?page=dashboard" class="btn btn-primary">
                    <i class="fas fa-home"></i> Ana Səhifə
                </a>
                <button onclick="history.back()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Geri
                </button>
            </div>
            
            <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                <h5 style="margin-bottom: 15px; color: #333;">
                    <i class="fas fa-link"></i> Mövcud Səhifələr:
                </h5>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; text-align: left;">
                    <a href="?page=dashboard" style="color: #667eea; text-decoration: none;">
                        <i class="fas fa-tachometer-alt"></i> Ana Səhifə
                    </a>
                    <a href="?page=sales" style="color: #667eea; text-decoration: none;">
                        <i class="fas fa-shopping-cart"></i> Satışlar
                    </a>
                    <a href="?page=products" style="color: #667eea; text-decoration: none;">
                        <i class="fas fa-box"></i> Məhsullar
                    </a>
                    <a href="?page=reports" style="color: #667eea; text-decoration: none;">
                        <i class="fas fa-chart-bar"></i> Hesabatlar
                    </a>
                    <a href="?page=chat" style="color: #667eea; text-decoration: none;">
                        <i class="fas fa-comments"></i> Chat
                    </a>
                    <a href="?page=calls" style="color: #667eea; text-decoration: none;">
                        <i class="fas fa-phone"></i> Zənglər
                    </a>
                    <a href="?page=users" style="color: #667eea; text-decoration: none;">
                        <i class="fas fa-users"></i> İstifadəçilər
                    </a>
                    <a href="?page=salary" style="color: #667eea; text-decoration: none;">
                        <i class="fas fa-money-bill-wave"></i> Maaş
                    </a>
                    <a href="?page=profile" style="color: #667eea; text-decoration: none;">
                        <i class="fas fa-user-circle"></i> Profil
                    </a>
                    <a href="?page=settings" style="color: #667eea; text-decoration: none;">
                        <i class="fas fa-cog"></i> Tənzimləmələr
                    </a>
                </div>
            </div>
        </div>
        <?php
        include 'includes/footer.php';
    }
}
?>
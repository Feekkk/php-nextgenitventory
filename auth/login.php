<?php
session_start();
require_once '../database/config.php';

function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function logLoginAttempt($pdo, $user_id, $staff_id, $email, $status, $failure_reason = null) {
    try {
        $ip_address = getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $session_id = session_id();
        
        $stmt = $pdo->prepare("INSERT INTO login_audit (user_id, staff_id, email, ip_address, user_agent, login_status, failure_reason, session_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $staff_id, $email, $ip_address, $user_agent, $status, $failure_reason, $session_id]);
    } catch (PDOException $e) {
        error_log("Failed to log login attempt: " . $e->getMessage());
    }
}

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: ../admin/Dashboard.php');
    } else {
        header('Location: ../technician/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
        if (!empty($email)) {
            try {
                $pdo = getDBConnection();
                logLoginAttempt($pdo, null, null, $email, 'failed', 'Empty email or password');
            } catch (PDOException $e) {
            }
        }
    } else {
        if ($email === 'admin@unikl.com' && $password === 'admin123') {
            try {
                $pdo = getDBConnection();
                logLoginAttempt($pdo, null, 'ADMIN001', $email, 'success');
            } catch (PDOException $e) {
            }
            
            $_SESSION['user_id'] = 'admin';
            $_SESSION['staff_id'] = 'ADMIN001';
            $_SESSION['full_name'] = 'Administrator';
            $_SESSION['email'] = 'admin@unikl.com';
            $_SESSION['role'] = 'admin';
            
            if ($remember) {
                setcookie('remember_token', base64_encode('admin'), time() + (86400 * 30), '/');
            }
            
            header('Location: ../admin/Dashboard.php');
            exit;
        } else {
            try {
                $pdo = getDBConnection();
                
                $stmt = $pdo->prepare("SELECT id, staff_id, full_name, email, password, role, status FROM technician WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] === 'inactive') {
                        $error = 'Your account is pending admin approval. Please wait for an administrator to activate your account before logging in.';
                        logLoginAttempt($pdo, $user['id'], $user['staff_id'], $email, 'failed', 'Account inactive - pending approval');
                    } else {
                        logLoginAttempt($pdo, $user['id'], $user['staff_id'], $email, 'success');
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['staff_id'] = $user['staff_id'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];
                        
                        if ($remember) {
                            setcookie('remember_token', base64_encode($user['id']), time() + (86400 * 30), '/');
                        }
                        
                        header('Location: ../technician/dashboard.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid email or password.';
                    logLoginAttempt($pdo, null, null, $email, 'failed', 'Invalid email or password');
                }
            } catch (PDOException $e) {
                $error = 'Login failed. Please try again.';
                try {
                    $pdo = getDBConnection();
                    logLoginAttempt($pdo, null, null, $email, 'failed', 'Database error');
                } catch (PDOException $logError) {
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UniKL RCMP IT Inventory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-background">
            <div class="auth-shape auth-shape-1"></div>
            <div class="auth-shape auth-shape-2"></div>
            <div class="auth-shape auth-shape-3"></div>
        </div>
        
        <div class="auth-card">
            <div class="auth-header">
                <a href="../index.php" class="auth-logo">
                    <img src="../public/unikl-rcmp.png" alt="UniKL RCMP Logo">
                </a>
                <h1>Welcome back</h1>
                <p>Sign in to your account to continue</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="">
                <div class="form-group">
                    <label for="email">Email address</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" placeholder="you@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="auth-btn">Sign in</button>
            </form>

            <div class="auth-divider">
                <span>or</span>
            </div>

            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Sign up</a></p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>


<?php
session_start();
require_once '../database/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id = trim($_POST['staff_id'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    if (empty($staff_id) || empty($full_name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo = getDBConnection();
            
            $stmt = $pdo->prepare("SELECT id FROM technician WHERE staff_id = ? OR email = ?");
            $stmt->execute([$staff_id, $email]);
            if ($stmt->fetch()) {
                $error = 'Staff ID or Email already exists.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $role = 'technician';
                $status = 'inactive';
                
                $stmt = $pdo->prepare("INSERT INTO technician (staff_id, full_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$staff_id, $full_name, $email, $hashedPassword, $role, $status]);
                
                $success = 'Registration successful! Your account is pending admin approval. You will be able to login once an administrator activates your account.';
            }
        } catch (PDOException $e) {
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - UniKL RCMP IT Inventory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/register.css">
    <style>
        .auth-back-home {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }

        .back-home-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            background: #f8f9fa;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            color: #2d3436;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            width: 100%;
        }

        .back-home-btn:hover {
            background: #1a1a2e;
            border-color: #1a1a2e;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.2);
        }

        .back-home-btn i {
            font-size: 0.9rem;
        }
    </style>
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
                <h1>Create your account</h1>
                <p>Get started with your IT inventory management</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" style="background: rgba(0, 184, 148, 0.1); color: #00b894; padding: 16px 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(0, 184, 148, 0.2); display: flex; align-items: flex-start; gap: 12px;">
                    <i class="fa-solid fa-circle-check" style="margin-top: 2px;"></i>
                    <div style="flex: 1;">
                        <div style="font-weight: 600; margin-bottom: 8px;"><?php echo htmlspecialchars($success); ?></div>
                        <div style="font-size: 0.9rem; margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(0, 184, 148, 0.2); display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-info-circle"></i>
                            <span>Please wait for an administrator to approve your account. You will not be able to login until your account is activated.</span>
                        </div>
                        <div style="margin-top: 12px;">
                            <a href="login.php" style="color: #00b894; text-decoration: underline; font-weight: 500;">Go to Login Page</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="">
                <div class="form-group">
                    <label for="staff_id">Staff ID</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-id-card input-icon"></i>
                        <input type="text" id="staff_id" name="staff_id" placeholder="e.g., TECH001" value="<?php echo htmlspecialchars($_POST['staff_id'] ?? ''); ?>" required autocomplete="username">
                    </div>
                    <p class="field-hint">Unique staff identification number</p>
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input type="text" id="full_name" name="full_name" placeholder="John Doe" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required autocomplete="name">
                    </div>
                </div>

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
                        <input type="password" id="password" name="password" placeholder="Create a strong password" required autocomplete="new-password">
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <p class="password-hint">Must be at least 8 characters</p>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required autocomplete="new-password">
                        <button type="button" class="password-toggle" id="toggleConfirmPassword">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="terms" id="terms" required>
                        <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
                    </label>
                </div>

                <button type="submit" class="auth-btn">Create account</button>
            </form>

            <div class="auth-divider">
                <span>or</span>
            </div>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Sign in</a></p>
            </div>

            <div class="auth-back-home">
                <a href="../index.php" class="back-home-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Back to Home</span>
                </a>
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

        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('confirmPassword');
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


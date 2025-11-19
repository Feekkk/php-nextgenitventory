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

            <form class="auth-form" method="POST" action="">
                <div class="form-group">
                    <label for="email">Email address</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email">
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


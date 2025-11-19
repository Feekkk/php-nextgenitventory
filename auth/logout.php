<?php
session_start();

// Clear session data
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out - UniKL RCMP IT Inventory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7ff 0%, #eef3ff 100%);
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logout-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 40px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08);
            text-align: center;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .logout-card h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            color: #1a1a2e;
        }

        .logout-card p {
            color: #636e72;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            background: #1a1a2e;
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: #0f0f1a;
            box-shadow: 0 10px 20px rgba(26, 26, 46, 0.2);
        }

        .note {
            font-size: 0.9rem;
            color: #a0a3b1;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="logout-card">
        <h1>Signed out successfully</h1>
        <p>Your session has ended. Click the button below if you need to log back into the UniKL RCMP IT Inventory portal.</p>
        <a class="btn-primary" href="login.php">
            Back to Login
        </a>
        <div class="note">Need help? Contact IT Support.</div>
    </div>
</body>
</html>


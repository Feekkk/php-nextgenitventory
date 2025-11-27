<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$error = '';
$success = '';

$statusOptions = ['available', 'unavailable', 'queue', 'pending', 'handovers'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_name = trim($_POST['staff_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $faculty = trim($_POST['faculty'] ?? '');
    $status = $_POST['status'] ?? 'available';
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($staff_name)) {
        $error = 'Staff name is required.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (!in_array($status, $statusOptions, true)) {
        $error = 'Invalid status selected.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO staff_list (staff_name, email, phone, faculty, status, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $staff_name,
                $email ?: null,
                $phone ?: null,
                $faculty ?: null,
                $status,
                $remarks ?: null,
                $_SESSION['user_id'] ?? null
            ]);

            $success = 'Staff added successfully! Redirecting to Manage Users...';
            $_POST = [];
            header("refresh:2;url=ManageUser.php");
        } catch (PDOException $e) {
            $error = 'Failed to add staff. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Staff - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .add-staff-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .back-btn {
            padding: 8px 16px;
            background: #f8f9fa;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            color: #2d3436;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .back-btn:hover {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #000000;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.75);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 40px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .alert {
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
        }

        .alert-error {
            background: rgba(214, 48, 49, 0.1);
            color: #d63031;
            border: 1px solid rgba(214, 48, 49, 0.2);
        }

        .alert-success {
            background: rgba(0, 184, 148, 0.1);
            color: #00b894;
            border: 1px solid rgba(0, 184, 148, 0.2);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3436;
            font-size: 0.95rem;
        }

        .form-group label.required::after {
            content: ' *';
            color: #d63031;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #636e72;
            z-index: 1;
            pointer-events: none;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 0.95rem;
            color: #2d3436;
            transition: all 0.2s ease;
            background: #ffffff;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
            padding-left: 44px;
        }

        .form-group select {
            padding-left: 44px;
            cursor: pointer;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #1a1a2e;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #0f0f1a;
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.3);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #2d3436;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .btn-secondary:hover {
            background: #e9ecef;
        }

        @media (max-width: 768px) {
            .add-staff-container {
                padding: 20px 15px;
            }

            .form-card {
                padding: 25px 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/ADMINheader.php"); ?>

    <div class="add-staff-container">
        <div class="page-header">
            <a href="ManageUser.php" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i>
                Back
            </a>
            <h1 class="page-title">Add Staff</h1>
        </div>

        <div class="form-card">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="staff_name" class="required">Staff Name</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input type="text" id="staff_name" name="staff_name" placeholder="Jane Doe" value="<?php echo htmlspecialchars($_POST['staff_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-envelope input-icon"></i>
                            <input type="email" id="email" name="email" placeholder="staff@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-phone input-icon"></i>
                            <input type="tel" id="phone" name="phone" placeholder="+60 12-345 6789" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="faculty">Faculty / Department</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-building input-icon"></i>
                            <input type="text" id="faculty" name="faculty" placeholder="ICT Department" value="<?php echo htmlspecialchars($_POST['faculty'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status" class="required">Status</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-toggle-on input-icon"></i>
                            <select id="status" name="status" required>
                                <?php foreach ($statusOptions as $option): ?>
                                    <option value="<?php echo $option; ?>" <?php echo (($_POST['status'] ?? 'available') === $option) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-note-sticky input-icon"></i>
                        <textarea id="remarks" name="remarks" placeholder="Additional notes or assignment details"><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="ManageUser.php" class="btn btn-secondary">
                        <i class="fa-solid fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-user-plus"></i>
                        Add Staff
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <?php include_once("../components/footer.php"); ?>
    </footer>
</body>
</html>

<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$error = '';
$success = '';
$pendingQueues = [];
$searchTerm = trim($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_name = trim($_POST['staff_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $faculty = trim($_POST['faculty'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    
    if (empty($staff_name)) {
        $error = 'Staff Name is required.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        try {
            if (!empty($email)) {
                $stmt = $pdo->prepare("SELECT staff_id FROM staff_list WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email already exists in the staff list.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO staff_list (staff_name, email, phone, faculty, remarks, created_by, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$staff_name, $email ?: null, $phone ?: null, $faculty ?: null, $remarks ?: null, $_SESSION['user_id']]);
                    
                    $success = 'Staff added to queue successfully!';
                    header("refresh:2;url=QUEUEpage.php");
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO staff_list (staff_name, email, phone, faculty, remarks, created_by, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$staff_name, null, $phone ?: null, $faculty ?: null, $remarks ?: null, $_SESSION['user_id']]);
                
                $success = 'Staff added to queue successfully!';
                header("refresh:2;url=QUEUEpage.php");
            }
        } catch (PDOException $e) {
            $error = 'Failed to add staff to queue. Please try again.';
        }
    }
}

try {
    $pendingSql = "SELECT staff_id, staff_name, email, phone, faculty, remarks, created_at FROM staff_list WHERE status IN ('pending', 'queue')";
    $pendingParams = [];

    if ($searchTerm !== '') {
        $pendingSql .= " AND (staff_name LIKE :search OR email LIKE :search)";
        $pendingParams['search'] = '%' . $searchTerm . '%';
    }

    $pendingSql .= " ORDER BY created_at DESC";

    $pendingStmt = $pdo->prepare($pendingSql);
    $pendingStmt->execute($pendingParams);
    $pendingQueues = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $error ?: 'Unable to load pending queue data right now.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Queue - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .form-page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #000000;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #636e72;
            font-size: 1rem;
        }

        .queue-form {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 40px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .queue-search {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            margin-bottom: 25px;
        }

        .queue-search .form-group {
            flex: 1;
            margin: 0;
        }

        .queue-search .btn {
            margin-top: 24px;
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

        .form-section {
            margin-bottom: 30px;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(26, 26, 46, 0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2d3436;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
            background: #ffffff;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #1a1a2e;
            color: #ffffff;
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #2d3436;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .btn-primary:hover {
            background: #0f0f1a;
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.3);
        }

        .btn-secondary:hover {
            background: #e9ecef;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
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
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="form-page-container">
        <div class="page-header">
            <h1>Add Queue List</h1>
            <p>Queue handover assets by adding staff information to the queue list.</p>
        </div>

        <div class="queue-form">
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
                <div class="form-section">
                    <h3 class="form-section-title">Staff Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="staff_name" class="required">Staff Name</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-user input-icon"></i>
                                <input type="text" id="staff_name" name="staff_name" placeholder="John Doe" value="<?php echo htmlspecialchars($_POST['staff_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-envelope input-icon"></i>
                                <input type="email" id="email" name="email" placeholder="staff@unikl.edu.my" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-phone input-icon"></i>
                                <input type="tel" id="phone" name="phone" placeholder="+60 12-345 6789" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="faculty">Faculty</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-building input-icon"></i>
                                <input type="text" id="faculty" name="faculty" placeholder="e.g., Faculty of Engineering" value="<?php echo htmlspecialchars($_POST['faculty'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" placeholder="Add any additional notes or remarks" style="width: 100%; padding: 12px 16px; border: 1px solid rgba(0, 0, 0, 0.1); border-radius: 8px; font-size: 0.95rem; font-family: 'Inter', sans-serif; resize: vertical; min-height: 100px;"><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                        <i class="fa-solid fa-times"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        Add to Queue
                    </button>
                </div>
            </form>
        </div>

        <div class="queue-form" style="margin-top: 30px;">
            <h3 class="form-section-title">Pending Queue</h3>
            <form method="GET" class="queue-search">
                <div class="form-group">
                    <label for="search">Search by Staff Name or Email</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-magnifying-glass input-icon"></i>
                        <input type="text" id="search" name="search" placeholder="e.g., John or john@unikl.edu.my" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-search"></i>
                    Search
                </button>
                <?php if ($searchTerm !== ''): ?>
                    <a href="QUEUEpage.php" class="btn btn-secondary" style="text-decoration:none;">
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset
                    </a>
                <?php endif; ?>
            </form>

            <?php if ($searchTerm !== ''): ?>
                <p>Showing results for "<strong><?php echo htmlspecialchars($searchTerm); ?></strong>".</p>
            <?php endif; ?>

            <?php if (empty($pendingQueues)): ?>
                <p><?php echo $searchTerm === '' ? 'No pending staff in the queue.' : 'No pending staff match your search.'; ?></p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f2f2f2;">
                                <th style="text-align:left; padding:12px; border-bottom:1px solid #ddd;">ID</th>
                                <th style="text-align:left; padding:12px; border-bottom:1px solid #ddd;">Staff Name</th>
                                <th style="text-align:left; padding:12px; border-bottom:1px solid #ddd;">Email</th>
                                <th style="text-align:left; padding:12px; border-bottom:1px solid #ddd;">Phone</th>
                                <th style="text-align:left; padding:12px; border-bottom:1px solid #ddd;">Faculty</th>
                                <th style="text-align:left; padding:12px; border-bottom:1px solid #ddd;">Remarks</th>
                                <th style="text-align:left; padding:12px; border-bottom:1px solid #ddd;">Queued At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingQueues as $queueItem): ?>
                                <tr>
                                    <td style="padding:12px; border-bottom:1px solid #f1f1f1;"><?php echo htmlspecialchars($queueItem['staff_id']); ?></td>
                                    <td style="padding:12px; border-bottom:1px solid #f1f1f1;"><?php echo htmlspecialchars($queueItem['staff_name']); ?></td>
                                    <td style="padding:12px; border-bottom:1px solid #f1f1f1;"><?php echo htmlspecialchars($queueItem['email'] ?? '-'); ?></td>
                                    <td style="padding:12px; border-bottom:1px solid #f1f1f1;"><?php echo htmlspecialchars($queueItem['phone'] ?? '-'); ?></td>
                                    <td style="padding:12px; border-bottom:1px solid #f1f1f1;"><?php echo htmlspecialchars($queueItem['faculty'] ?? '-'); ?></td>
                                    <td style="padding:12px; border-bottom:1px solid #f1f1f1;"><?php echo htmlspecialchars($queueItem['remarks'] ?? '-'); ?></td>
                                    <td style="padding:12px; border-bottom:1px solid #f1f1f1;"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($queueItem['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <?php include_once("../components/footer.php"); ?>
    </footer>
</body>
</html>


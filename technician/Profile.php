
<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Placeholder user info; replace with actual query
$user = [
    'full_name' => $_SESSION['full_name'] ?? 'Technician Name',
    'email' => $_SESSION['email'] ?? 'tech@unikl.edu.my',
    'employee_id' => 'RCMP-IT-001',
    'role' => 'IT Support Technician',
    'department' => 'IT Services',
    'phone' => '+60 12-345 6789',
    'office' => 'Block B, Level 2',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - UniKL RCMP IT Inventory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .profile-page-container {
            max-width: 1100px;
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

        .profile-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
        }

        .profile-summary {
            border-right: 1px solid rgba(0, 0, 0, 0.08);
            padding-right: 20px;
        }

        .avatar-wrapper {
            width: 120px;
            height: 120px;
            border-radius: 20px;
            background: linear-gradient(135deg, #6c5ce7, #00cec9);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .profile-summary h2 {
            margin: 0 0 5px;
            color: #1a1a2e;
        }

        .profile-summary span {
            color: #636e72;
        }

        .summary-list {
            margin-top: 20px;
            list-style: none;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .summary-list li {
            color: #2d3436;
            font-size: 0.95rem;
        }

        .summary-list i {
            color: #6c5ce7;
            margin-right: 10px;
        }

        .profile-form {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .form-section-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 15px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.9rem;
            font-weight: 500;
            color: #2d3436;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 14px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 10px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: #1a1a2e;
            color: #ffffff;
        }

        .btn-secondary {
            background: #f1f2f6;
            color: #2d3436;
        }

        .btn-primary:hover {
            background: #0f0f1a;
            box-shadow: 0 8px 15px rgba(26, 26, 46, 0.25);
        }

        .btn-secondary:hover {
            background: #e3e6ed;
        }

        @media (max-width: 920px) {
            .profile-card {
                grid-template-columns: 1fr;
            }

            .profile-summary {
                border-right: none;
                border-bottom: 1px solid rgba(0, 0, 0, 0.08);
                padding-bottom: 20px;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="profile-page-container">
        <div class="page-header">
            <h1>Edit Profile</h1>
            <p>Update your account details so colleagues know who to contact for each request.</p>
        </div>

        <div class="profile-card">
            <div class="profile-summary">
                <div class="avatar-wrapper">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                <span><?php echo htmlspecialchars($user['role']); ?></span>
                <ul class="summary-list">
                    <li><i class="fa-solid fa-id-badge"></i><?php echo htmlspecialchars($user['employee_id']); ?></li>
                    <li><i class="fa-solid fa-envelope"></i><?php echo htmlspecialchars($user['email']); ?></li>
                    <li><i class="fa-solid fa-building"></i><?php echo htmlspecialchars($user['department']); ?></li>
                    <li><i class="fa-solid fa-location-dot"></i><?php echo htmlspecialchars($user['office']); ?></li>
                </ul>
            </div>
            <form class="profile-form">
                <section>
                    <h3 class="form-section-title">Personal Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fullName">Full Name</label>
                            <input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="employeeId">Employee ID</label>
                            <input type="text" id="employeeId" name="employeeId" value="<?php echo htmlspecialchars($user['employee_id']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="role">Role / Title</label>
                            <input type="text" id="role" name="role" value="<?php echo htmlspecialchars($user['role']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="department">Department</label>
                            <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($user['department']); ?>">
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="form-section-title">Contact Info</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="office">Office Location</label>
                            <input type="text" id="office" name="office" value="<?php echo htmlspecialchars($user['office']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="availability">Availability Notes</label>
                            <input type="text" id="availability" name="availability" placeholder="e.g., On-site Mon-Thu, remote Friday">
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="form-section-title">Account Security</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="currentPassword">Current Password</label>
                            <input type="password" id="currentPassword" name="currentPassword" placeholder="Enter current password">
                        </div>
                        <div class="form-group">
                            <label for="newPassword">New Password</label>
                            <input type="password" id="newPassword" name="newPassword" placeholder="Choose a strong password">
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Re-enter new password">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="signature">Digital Signature</label>
                            <textarea id="signature" name="signature" placeholder="Add a short signature or note for approvals"></textarea>
                        </div>
                    </div>
                </section>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>
</body>
</html>


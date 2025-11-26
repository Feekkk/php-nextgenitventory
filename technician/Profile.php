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

function logProfileAudit($pdo, $user_id, $staff_id, $email, $action_type, $fields_changed = [], $old_values = [], $new_values = []) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $session_id = session_id();
        
        $fields_changed_str = !empty($fields_changed) ? json_encode($fields_changed) : null;
        $old_values_str = !empty($old_values) ? json_encode($old_values) : null;
        $new_values_str = !empty($new_values) ? json_encode($new_values) : null;
        
        $stmt = $pdo->prepare("INSERT INTO profile_audit (user_id, staff_id, email, action_type, fields_changed, old_values, new_values, ip_address, user_agent, session_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $staff_id, $email, $action_type, $fields_changed_str, $old_values_str, $new_values_str, $ip_address, $user_agent, $session_id]);
    } catch (PDOException $e) {
        error_log("Failed to log profile audit: " . $e->getMessage());
    }
}

try {
    $stmt = $pdo->prepare("SELECT id, staff_id, full_name, email, phone, role, status, profile_picture, created_at FROM technician WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: ../auth/login.php');
        exit;
    }
} catch (PDOException $e) {
    $error = 'Failed to load user data.';
    $user = null;
}

$profile_dir = '../profile/';
if (!file_exists($profile_dir)) {
    mkdir($profile_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $profile_picture = $user['profile_picture'] ?? null;
    
    if (empty($full_name) || empty($email)) {
        $error = 'Full Name and Email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        try {
            if ($email !== $user['email']) {
                $stmt = $pdo->prepare("SELECT id FROM technician WHERE email = ? AND id != ?");
                $stmt->execute([$email, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $error = 'Email already exists.';
                }
            }
            
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_picture'];
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024;
                
                if (!in_array($file['type'], $allowed_types)) {
                    $error = 'Invalid file type. Only JPEG, PNG, and GIF images are allowed.';
                } elseif ($file['size'] > $max_size) {
                    $error = 'File size too large. Maximum size is 5MB.';
                } else {
                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $new_filename = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                    $upload_path = $profile_dir . $new_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        if ($profile_picture && file_exists($profile_dir . basename($profile_picture))) {
                            unlink($profile_dir . basename($profile_picture));
                        }
                        $profile_picture = 'profile/' . $new_filename;
                    } else {
                        $error = 'Failed to upload profile picture.';
                    }
                }
            }
            
            if (!$error) {
                $fields_changed = [];
                $old_values = [];
                $new_values = [];
                $action_types = [];
                
                if ($full_name !== $user['full_name']) {
                    $fields_changed[] = 'full_name';
                    $old_values['full_name'] = $user['full_name'];
                    $new_values['full_name'] = $full_name;
                    $action_types[] = 'update_name';
                }
                
                if ($email !== $user['email']) {
                    $fields_changed[] = 'email';
                    $old_values['email'] = $user['email'];
                    $new_values['email'] = $email;
                    $action_types[] = 'update_email';
                }
                
                if (($phone ?: '') !== ($user['phone'] ?: '')) {
                    $fields_changed[] = 'phone';
                    $old_values['phone'] = $user['phone'] ?? '';
                    $new_values['phone'] = $phone ?: '';
                    $action_types[] = 'update_phone';
                }
                
                if ($profile_picture !== $user['profile_picture'] && $profile_picture !== null) {
                    $fields_changed[] = 'profile_picture';
                    $old_values['profile_picture'] = $user['profile_picture'] ?? '';
                    $new_values['profile_picture'] = $profile_picture;
                    $action_types[] = 'upload_picture';
                }
                
                if (!empty($newPassword)) {
                    if (empty($currentPassword)) {
                        $error = 'Current password is required to change password.';
                    } else {
                        $stmt = $pdo->prepare("SELECT password FROM technician WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $currentUser = $stmt->fetch();
                        
                        if (!password_verify($currentPassword, $currentUser['password'])) {
                            $error = 'Current password is incorrect.';
                        } elseif (strlen($newPassword) < 8) {
                            $error = 'New password must be at least 8 characters long.';
                        } elseif ($newPassword !== $confirmPassword) {
                            $error = 'New passwords do not match.';
                        } else {
                            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE technician SET full_name = ?, email = ?, phone = ?, password = ?, profile_picture = ? WHERE id = ?");
                            $stmt->execute([$full_name, $email, $phone ?: null, $hashedPassword, $profile_picture, $_SESSION['user_id']]);
                            
                            $_SESSION['full_name'] = $full_name;
                            $_SESSION['email'] = $email;
                            $success = 'Profile updated successfully!';
                            
                            $action_types[] = 'change_password';
                            $fields_changed[] = 'password';
                            $old_values['password'] = '***';
                            $new_values['password'] = '***';
                            
                            if (!empty($action_types)) {
                                $primary_action = in_array('change_password', $action_types) ? 'change_password' : 
                                                 (in_array('upload_picture', $action_types) ? 'upload_picture' : 'update_profile');
                                logProfileAudit($pdo, $_SESSION['user_id'], $user['staff_id'], $email, $primary_action, $fields_changed, $old_values, $new_values);
                            }
                            
                            header("refresh:1;url=Profile.php");
                        }
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE technician SET full_name = ?, email = ?, phone = ?, profile_picture = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $phone ?: null, $profile_picture, $_SESSION['user_id']]);
                    
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    $success = 'Profile updated successfully!';
                    
                    if (!empty($fields_changed)) {
                        $primary_action = in_array('upload_picture', $action_types) ? 'upload_picture' : 'update_profile';
                        logProfileAudit($pdo, $_SESSION['user_id'], $user['staff_id'], $email, $primary_action, $fields_changed, $old_values, $new_values);
                    }
                    
                    header("refresh:1;url=Profile.php");
                }
                
                if ($success) {
                    $stmt = $pdo->prepare("SELECT id, staff_id, full_name, email, phone, role, status, profile_picture, created_at FROM technician WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                }
            }
        } catch (PDOException $e) {
            $error = 'Failed to update profile. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 40px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
        }

        .profile-summary {
            border-right: 1px solid rgba(0, 0, 0, 0.08);
            padding-right: 30px;
        }

        .avatar-wrapper {
            width: 120px;
            height: 120px;
            border-radius: 20px;
            background: linear-gradient(135deg, #1a1a2e, #6c5ce7);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .avatar-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-wrapper .avatar-text {
            position: absolute;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-picture-upload {
            margin-bottom: 20px;
        }

        .profile-picture-upload label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #2d3436;
        }

        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload-input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            border: 2px dashed rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #f8f9fa;
        }

        .file-upload-label:hover {
            border-color: #1a1a2e;
            background: #e9ecef;
        }

        .file-upload-label i {
            color: #636e72;
        }

        .file-upload-label span {
            color: #2d3436;
            font-size: 0.9rem;
        }

        .file-upload-preview {
            margin-top: 10px;
            display: none;
        }

        .file-upload-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .profile-summary h2 {
            margin: 0 0 5px;
            color: #1a1a2e;
            font-size: 1.5rem;
        }

        .profile-summary .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 5px;
        }

        .profile-summary .role-badge.technician {
            background: rgba(0, 206, 201, 0.1);
            color: #00cec9;
        }

        .summary-list {
            margin-top: 25px;
            list-style: none;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .summary-list li {
            color: #2d3436;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .summary-list i {
            color: #1a1a2e;
            width: 18px;
        }

        .profile-form {
            display: flex;
            flex-direction: column;
            gap: 30px;
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #2d3436;
        }

        .form-group input {
            padding: 12px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
            background: #ffffff;
        }

        .form-group input:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .form-group input:disabled {
            background: #f8f9fa;
            color: #636e72;
            cursor: not-allowed;
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #636e72;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
        }

        .password-toggle:hover {
            color: #1a1a2e;
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

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 10px;
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

        @media (max-width: 920px) {
            .profile-card {
                grid-template-columns: 1fr;
            }

            .profile-summary {
                border-right: none;
                border-bottom: 1px solid rgba(0, 0, 0, 0.08);
                padding-bottom: 25px;
                padding-right: 0;
                margin-bottom: 25px;
            }

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

    <div class="profile-page-container">
        <div class="page-header">
            <h1>Edit Profile</h1>
            <p>Update your account information and manage your profile settings.</p>
        </div>

        <?php if ($user): ?>
            <div class="profile-card">
                <div class="profile-summary">
                    <div class="avatar-wrapper">
                        <?php if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])): ?>
                            <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <div class="avatar-text"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>
                    <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <span class="role-badge <?php echo htmlspecialchars($user['role']); ?>">
                        <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                    </span>
                    <ul class="summary-list">
                        <li>
                            <i class="fa-solid fa-id-badge"></i>
                            <span><?php echo htmlspecialchars($user['staff_id']); ?></span>
                        </li>
                        <li>
                            <i class="fa-solid fa-envelope"></i>
                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                        </li>
                        <?php if ($user['phone']): ?>
                            <li>
                                <i class="fa-solid fa-phone"></i>
                                <span><?php echo htmlspecialchars($user['phone']); ?></span>
                            </li>
                        <?php endif; ?>
                        <li>
                            <i class="fa-solid fa-calendar"></i>
                            <span>Joined <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                        </li>
                    </ul>
                </div>
                <form class="profile-form" method="POST" action="" enctype="multipart/form-data">
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

                    <section>
                        <h3 class="form-section-title">Profile Picture</h3>
                        <div class="profile-picture-upload">
                            <label for="profile_picture">Upload Profile Picture</label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif" class="file-upload-input" onchange="previewImage(this)">
                                <label for="profile_picture" class="file-upload-label">
                                    <i class="fa-solid fa-upload"></i>
                                    <span>Choose Image (Max 5MB)</span>
                                </label>
                            </div>
                            <div class="file-upload-preview" id="imagePreview">
                                <img id="previewImg" src="" alt="Preview">
                            </div>
                            <small style="color: #636e72; font-size: 0.85rem; display: block; margin-top: 8px;">Accepted formats: JPEG, PNG, GIF</small>
                        </div>
                    </section>

                    <section>
                        <h3 class="form-section-title">Personal Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="staff_id">Staff ID</label>
                                <input type="text" id="staff_id" name="staff_id" value="<?php echo htmlspecialchars($user['staff_id']); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+60 12-345 6789">
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3 class="form-section-title">Change Password</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="currentPassword">Current Password</label>
                                <div class="password-wrapper">
                                    <input type="password" id="currentPassword" name="currentPassword" placeholder="Enter current password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('currentPassword', this)">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                                <small style="color: #636e72; font-size: 0.85rem;">Leave blank if not changing password</small>
                            </div>
                            <div class="form-group">
                                <label for="newPassword">New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('newPassword', this)">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                                <small style="color: #636e72; font-size: 0.85rem;">Must be at least 8 characters</small>
                            </div>
                            <div class="form-group">
                                <label for="confirmPassword">Confirm New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm new password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                            <i class="fa-solid fa-times"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-save"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <?php include_once("../components/footer.php"); ?>
    </footer>

    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        }

        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
</body>
</html>

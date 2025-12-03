<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$users = [];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $user_id = $_POST['user_id'] ?? null;
        
        if ($action === 'delete' && $user_id) {
            try {
                if ($user_id == $_SESSION['user_id']) {
                    $error = 'You cannot delete your own account.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM technician WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success = 'User deleted successfully.';
                }
            } catch (PDOException $e) {
                $error = 'Failed to delete user.';
            }
        }
    }
}

try {
    $stmt = $pdo->query("SELECT id, tech_id, tech_name, email, role, status, phone, profile_picture, created_at FROM technician ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Failed to load users: ' . $e->getMessage());
    $error = 'Failed to load users.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .users-page-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #000000;
        }

        .page-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filters-container {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-box input {
            padding: 10px 16px 10px 44px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 0.95rem;
            width: 300px;
            transition: all 0.2s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 16px;
            color: #636e72;
        }

        .filter-select {
            padding: 10px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 0.95rem;
            background: #ffffff;
            color: #2d3436;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 140px;
        }

        .filter-select:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .filter-select:hover {
            border-color: #1a1a2e;
        }

        .btn-add {
            padding: 10px 20px;
            background: #1a1a2e;
            color: #ffffff;
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

        .btn-add:hover {
            background: #0f0f1a;
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.3);
        }

        .alert {
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
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

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }

        .user-card {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 24px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .user-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(26, 26, 46, 0.15);
            border-color: rgba(26, 26, 46, 0.1);
        }

        .user-card-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .user-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a1a2e, #6c5ce7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 2rem;
            flex-shrink: 0;
            overflow: hidden;
            position: relative;
        }

        .user-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-avatar-large .avatar-text {
            position: absolute;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-info-header {
            flex: 1;
            min-width: 0;
        }

        .user-name-large {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 4px;
            word-wrap: break-word;
        }

        .user-staff-id {
            font-size: 0.85rem;
            color: #636e72;
            font-weight: 500;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
            flex: 1;
        }

        .user-detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #2d3436;
        }

        .user-detail-item i {
            width: 18px;
            color: #636e72;
            font-size: 0.9rem;
        }

        .user-detail-item span {
            flex: 1;
            word-break: break-word;
        }

        .user-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 16px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            margin-top: auto;
        }

        .user-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .role-badge.admin {
            background: rgba(108, 92, 231, 0.1);
            color: #6c5ce7;
        }

        .role-badge.technician {
            background: rgba(0, 206, 201, 0.1);
            color: #00cec9;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-badge.active {
            background: rgba(0, 184, 148, 0.1);
            color: #00b894;
        }

        .status-badge.inactive {
            background: rgba(253, 121, 168, 0.1);
            color: #fd79a8;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            padding: 8px 12px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: #ffffff;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #2d3436;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-action:hover {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.2);
        }

        .btn-action.danger:hover {
            background: #d63031;
            border-color: #d63031;
            box-shadow: 0 4px 12px rgba(214, 48, 49, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #636e72;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: rgba(26, 26, 46, 0.2);
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2d3436;
        }

        .empty-state span {
            font-size: 0.9rem;
            color: #636e72;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: #ffffff;
            margin: 5% auto;
            padding: 30px;
            border-radius: 16px;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            color: #2d3436;
        }

        .close-modal {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #636e72;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .close-modal:hover {
            background: rgba(0, 0, 0, 0.05);
            color: #2d3436;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-body p {
            color: #636e72;
            line-height: 1.6;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-modal {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-modal-cancel {
            background: #f8f9fa;
            color: #2d3436;
        }

        .btn-modal-cancel:hover {
            background: #e9ecef;
        }

        .btn-modal-confirm {
            background: #d63031;
            color: #ffffff;
        }

        .btn-modal-confirm:hover {
            background: #c0392b;
        }

        .btn-modal-toggle {
            background: #1a1a2e;
            color: #ffffff;
        }

        .btn-modal-toggle:hover {
            background: #0f0f1a;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-box input {
                width: 100%;
            }

            .filters-container {
                width: 100%;
            }

            .filter-select {
                width: 100%;
            }

            .users-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .user-card {
                padding: 20px;
            }

            .user-avatar-large {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .user-name-large {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/ADMINheader.php"); ?>

    <div class="users-page-container">
        <div class="page-header">
            <h1 class="page-title">Manage Users</h1>
            <div class="page-actions">
                <div class="filters-container">
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" placeholder="Search users..." id="searchInput">
                    </div>
                    <select class="filter-select" id="roleFilter">
                        <option value="all">All Roles</option>
                        <option value="admin">Admin</option>
                        <option value="technician">Technician</option>
                    </select>
                    <select class="filter-select" id="statusFilter">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <a href="AddUser.php" class="btn-add">
                    <i class="fa-solid fa-plus"></i>
                    Add User
                </a>
            </div>
        </div>

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

        <div class="users-grid" id="usersGrid">
            <?php if (empty($users)): ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <i class="fa-solid fa-users"></i>
                    <p>No users found</p>
                    <span>Start by adding your first user</span>
                </div>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <div class="user-card" data-role="<?php echo htmlspecialchars($user['role']); ?>" data-status="<?php echo htmlspecialchars($user['status']); ?>">
                        <div class="user-card-header">
                            <div class="user-avatar-large">
                                <?php if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])): ?>
                                    <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                                <?php else: ?>
                                    <div class="avatar-text"><?php echo strtoupper(substr($user['tech_name'], 0, 1)); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="user-info-header">
                                <div class="user-name-large"><?php echo htmlspecialchars($user['tech_name']); ?></div>
                                <div class="user-staff-id"><?php echo htmlspecialchars($user['tech_id']); ?></div>
                            </div>
                        </div>
                        
                        <div class="user-details">
                            <div class="user-detail-item">
                                <i class="fa-solid fa-envelope"></i>
                                <span><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                            <?php if ($user['phone']): ?>
                                <div class="user-detail-item">
                                    <i class="fa-solid fa-phone"></i>
                                    <span><?php echo htmlspecialchars($user['phone']); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="user-detail-item">
                                <i class="fa-solid fa-calendar"></i>
                                <span>Joined <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>

                        <div class="user-card-footer">
                            <div class="user-badges">
                                <span class="role-badge <?php echo htmlspecialchars($user['role']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                </span>
                                <span class="status-badge <?php echo htmlspecialchars($user['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                                </span>
                            </div>
                            <div class="action-buttons">
                                <a href="EditUser.php?id=<?php echo $user['id']; ?>" class="btn-action" title="Edit">
                                    <i class="fa-solid fa-edit"></i>
                                    <span>Edit</span>
                                </a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn-action danger" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['tech_name']); ?>')" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                        <span>Delete</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Confirm Action</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="modalMessage"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal btn-modal-cancel" onclick="closeModal()">Cancel</button>
                <form id="modalForm" method="POST" style="display: inline;">
                    <input type="hidden" name="action" id="modalAction">
                    <input type="hidden" name="user_id" id="modalUserId">
                    <button type="submit" class="btn-modal" id="modalConfirmBtn">Confirm</button>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <?php include_once("../components/footer.php"); ?>
    </footer>

    <script>
        function filterUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const cards = document.querySelectorAll('.user-card');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                const cardRole = card.getAttribute('data-role');
                const cardStatus = card.getAttribute('data-status');
                
                const matchesSearch = searchTerm === '' || text.includes(searchTerm);
                const matchesRole = roleFilter === 'all' || cardRole === roleFilter;
                const matchesStatus = statusFilter === 'all' || cardStatus === statusFilter;
                
                if (matchesSearch && matchesRole && matchesStatus) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        document.getElementById('searchInput').addEventListener('input', filterUsers);
        document.getElementById('roleFilter').addEventListener('change', filterUsers);
        document.getElementById('statusFilter').addEventListener('change', filterUsers);


        function confirmDelete(userId, userName) {
            document.getElementById('modalTitle').textContent = 'Confirm Deletion';
            document.getElementById('modalMessage').textContent = `Are you sure you want to delete user "${userName}"? This action cannot be undone.`;
            document.getElementById('modalAction').value = 'delete';
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalConfirmBtn').textContent = 'Delete';
            document.getElementById('modalConfirmBtn').className = 'btn-modal btn-modal-confirm';
            document.getElementById('confirmModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>


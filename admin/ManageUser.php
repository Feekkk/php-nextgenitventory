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
        
        if ($action === 'toggle_status' && $user_id) {
            try {
                $stmt = $pdo->prepare("SELECT status FROM technician WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $new_status = $user['status'] === 'active' ? 'inactive' : 'active';
                    $stmt = $pdo->prepare("UPDATE technician SET status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $user_id]);
                    $success = 'User status updated successfully.';
                }
            } catch (PDOException $e) {
                $error = 'Failed to update user status.';
            }
        } elseif ($action === 'delete' && $user_id) {
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
    $stmt = $pdo->query("SELECT id, staff_id, full_name, email, role, status, phone, created_at FROM technician ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
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

        .users-table-container {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 30px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table thead {
            background: rgba(26, 26, 46, 0.05);
        }

        .users-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2d3436;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(26, 26, 46, 0.1);
        }

        .users-table td {
            padding: 18px 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: #2d3436;
            font-size: 0.95rem;
        }

        .users-table tbody tr {
            transition: all 0.2s ease;
        }

        .users-table tbody tr:hover {
            background: rgba(26, 26, 46, 0.03);
        }

        .staff-id {
            font-weight: 600;
            color: #1a1a2e;
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
            padding: 6px 12px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: #ffffff;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #2d3436;
            font-size: 0.85rem;
        }

        .btn-action:hover {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
        }

        .btn-action.danger:hover {
            background: #d63031;
            border-color: #d63031;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #636e72;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: rgba(26, 26, 46, 0.2);
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 10px;
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

            .users-table-container {
                padding: 15px;
            }

            .users-table {
                font-size: 0.85rem;
            }

            .users-table th,
            .users-table td {
                padding: 10px 8px;
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

        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Staff ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fa-solid fa-users"></i>
                                    <p>No users found</p>
                                    <span>Start by adding your first user</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr data-role="<?php echo htmlspecialchars($user['role']); ?>" data-status="<?php echo htmlspecialchars($user['status']); ?>">
                                <td class="staff-id"><?php echo htmlspecialchars($user['staff_id']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="role-badge <?php echo htmlspecialchars($user['role']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo htmlspecialchars($user['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action" onclick="toggleStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')" title="Toggle Status">
                                            <i class="fa-solid fa-<?php echo $user['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
                                        </button>
                                        <button class="btn-action" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn-action danger" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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
            const rows = document.querySelectorAll('.users-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const rowRole = row.getAttribute('data-role');
                const rowStatus = row.getAttribute('data-status');
                
                const matchesSearch = searchTerm === '' || text.includes(searchTerm);
                const matchesRole = roleFilter === 'all' || rowRole === roleFilter;
                const matchesStatus = statusFilter === 'all' || rowStatus === statusFilter;
                
                if (matchesSearch && matchesRole && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        document.getElementById('searchInput').addEventListener('input', filterUsers);
        document.getElementById('roleFilter').addEventListener('change', filterUsers);
        document.getElementById('statusFilter').addEventListener('change', filterUsers);

        function toggleStatus(userId, currentStatus) {
            const action = currentStatus === 'active' ? 'deactivate' : 'activate';
            document.getElementById('modalTitle').textContent = 'Confirm Status Change';
            document.getElementById('modalMessage').textContent = `Are you sure you want to ${action} this user?`;
            document.getElementById('modalAction').value = 'toggle_status';
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalConfirmBtn').textContent = action === 'activate' ? 'Activate' : 'Deactivate';
            document.getElementById('modalConfirmBtn').className = 'btn-modal btn-modal-toggle';
            document.getElementById('confirmModal').style.display = 'block';
        }

        function editUser(userId) {
            alert('Edit user functionality coming soon');
        }

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


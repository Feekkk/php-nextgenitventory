<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$laptopAssets = [];
$laptopAssetsError = '';

try {
    $stmt = $pdo->query("
        SELECT la.*, sl.staff_name AS assigned_to_name
        FROM laptop_desktop_assets la
        LEFT JOIN staff_list sl ON la.staff_id = sl.staff_id
        ORDER BY la.created_at DESC, la.asset_id DESC
    ");
    $laptopAssets = $stmt->fetchAll();
} catch (PDOException $e) {
    $laptopAssetsError = 'Unable to load laptop/desktop assets right now. Please try again later.';
}

function formatAssetId($id)
{
    return sprintf('LAP-%05d', $id);
}

function formatStatusClass($status)
{
    $status = strtoupper(trim($status ?? ''));
    $map = [
        'AVAILABLE' => 'available',
        'IN-USE' => 'in-use',
        'MAINTENANCE' => 'maintenance',
        'DISPOSED' => 'disposed',
    ];
    return $map[$status] ?? 'unknown';
}

function formatStatusLabel($status)
{
    $status = trim((string)$status);
    return $status === '' ? 'Unknown' : ucwords(str_replace('-', ' ', $status));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laptop & Desktop Assets - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .assets-page-container {
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
            position: relative;
            flex-wrap: wrap;
        }

        .actions-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .dropdown-wrapper {
            position: relative;
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
        }

        .btn-add:hover {
            background: #0f0f1a;
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.3);
        }

        .btn-queue {
            padding: 10px 20px;
            background: #0984e3;
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
            box-shadow: 0 4px 12px rgba(9, 132, 227, 0.2);
        }

        .btn-queue:hover {
            background: #0770c4;
            box-shadow: 0 6px 16px rgba(9, 132, 227, 0.3);
        }

        .btn-add i.fa-chevron-down {
            font-size: 0.8rem;
        }

        .dropdown-menu {
            position: absolute;
            top: 110%;
            right: 0;
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
            min-width: 220px;
            overflow: hidden;
            opacity: 0;
            transform: translateY(-10px);
            pointer-events: none;
            transition: all 0.2s ease;
            z-index: 10;
        }

        .dropdown-menu.open {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .dropdown-menu button {
            width: 100%;
            background: transparent;
            border: none;
            padding: 14px 18px;
            text-align: left;
            font-size: 0.95rem;
            color: #2d3436;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .dropdown-menu button:hover {
            background: rgba(26, 26, 46, 0.05);
        }

        .dropdown-menu button i {
            color: #6c5ce7;
            width: 18px;
            text-align: center;
        }

        .dropdown-menu button.import i {
            color: #0984e3;
        }

        .assets-table-container {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 30px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .assets-table {
            width: 100%;
            border-collapse: collapse;
        }

        .assets-table thead {
            background: rgba(26, 26, 46, 0.05);
        }

        .assets-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2d3436;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(26, 26, 46, 0.1);
        }

        .assets-table td {
            padding: 18px 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: #2d3436;
            font-size: 0.95rem;
        }

        .assets-table tbody tr {
            transition: all 0.2s ease;
        }

        .assets-table tbody tr:hover {
            background: rgba(26, 26, 46, 0.03);
        }

        .asset-id {
            font-weight: 600;
            color: #1a1a2e;
        }

        .asset-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .asset-type.laptop {
            background: rgba(108, 92, 231, 0.1);
            color: #6c5ce7;
        }

        .asset-type.desktop {
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

        .status-badge.available {
            background: rgba(0, 184, 148, 0.1);
            color: #00b894;
        }

        .status-badge.in-use {
            background: rgba(108, 92, 231, 0.1);
            color: #6c5ce7;
        }

        .status-badge.maintenance {
            background: rgba(253, 121, 168, 0.1);
            color: #fd79a8;
        }

        .status-badge.disposed {
            background: rgba(99, 110, 114, 0.1);
            color: #636e72;
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

        .data-message {
            text-align: center;
            padding: 20px;
            color: #c0392b;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-box input {
                width: 100%;
            }

            .assets-table-container {
                padding: 15px;
            }

            .assets-table {
                font-size: 0.85rem;
            }

            .assets-table th,
            .assets-table td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="assets-page-container">
        <div class="page-header">
            <h1 class="page-title">Laptop & Desktop Assets</h1>
            <div class="page-actions">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" placeholder="Search assets..." id="searchInput">
                </div>
                <div class="actions-group">
                    <div class="dropdown-wrapper">
                        <button class="btn-add" id="btn-add" type="button">
                            <i class="fa-solid fa-plus"></i>
                            Add Assets
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu" id="addDropdown">
                            <button type="button" onclick="window.location.href='LAPTOPadd.php'">
                                <i class="fa-solid fa-file-circle-plus"></i>
                                Add single asset
                            </button>
                            <button type="button" class="import" onclick="window.location.href='LAPTOPcsv.php'">
                                <i class="fa-solid fa-file-import"></i>
                                Import via CSV
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn-queue" onclick="window.location.href='QUEUEpage.php'">
                        <i class="fa-solid fa-list-check"></i>
                        Add Queue
                    </button>
                </div>
            </div>
        </div>

        <div class="assets-table-container">
            <table class="assets-table">
                <thead>
                    <tr>
                        <th>Asset ID</th>
                        <th>Type</th>
                        <th>Brand/Model</th>
                        <th>Serial Number</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="assetsTableBody">
                    <?php if ($laptopAssetsError) : ?>
                        <tr>
                            <td colspan="7">
                                <div class="data-message"><?php echo htmlspecialchars($laptopAssetsError); ?></div>
                            </td>
                        </tr>
                    <?php elseif (empty($laptopAssets)) : ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fa-solid fa-laptop"></i>
                                    <p>No assets found</p>
                                    <span>Start by adding your first laptop or desktop asset</span>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($laptopAssets as $asset) : ?>
                            <?php
                                $statusClass = formatStatusClass($asset['status'] ?? '');
                                $statusLabel = formatStatusLabel($asset['status'] ?? '');
                                $category = trim((string)($asset['category'] ?? ''));
                                $brand = trim((string)($asset['brand'] ?? ''));
                                $model = trim((string)($asset['model'] ?? ''));
                                $brandModel = trim($brand . ' ' . $model);
                                if ($brandModel === '') {
                                    $brandModel = '-';
                                }
                                $serial = trim((string)($asset['serial_num'] ?? ''));
                                if ($serial === '') {
                                    $serial = '-';
                                }
                                $assignedTo = trim((string)($asset['assigned_to_name'] ?? ''));
                                if ($assignedTo === '') {
                                    $assignedTo = '-';
                                }
                            ?>
                            <tr>
                                <td class="asset-id"><?php echo htmlspecialchars(formatAssetId($asset['asset_id'])); ?></td>
                                <td>
                                    <span class="asset-type <?php echo htmlspecialchars(strtolower($category ?: 'other')); ?>">
                                        <?php echo htmlspecialchars($category ?: 'Other'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($brandModel); ?></td>
                                <td><?php echo htmlspecialchars($serial); ?></td>
                                <td><?php echo htmlspecialchars($assignedTo); ?></td>
                                <td>
                                    <span class="status-badge <?php echo htmlspecialchars($statusClass); ?>">
                                        <?php echo htmlspecialchars($statusLabel); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action" onclick="window.location.href='LAPTOPview.php?id=<?php echo $asset['asset_id']; ?>'">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>

    <script>
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.assets-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        const addButton = document.getElementById('btn-add');
        const dropdown = document.getElementById('addDropdown');

        addButton.addEventListener('click', () => {
            dropdown.classList.toggle('open');
        });

        document.addEventListener('click', (event) => {
            if (!addButton.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('open');
            }
        });
    </script>
</body>
</html>


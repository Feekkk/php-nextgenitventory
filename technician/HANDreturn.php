
<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Handover Records - UniKL RCMP IT Inventory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .records-page-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #000000;
            margin: 0;
        }

        .page-description {
            color: #636e72;
            margin-top: 6px;
        }

        .filters-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 10px 16px 10px 44px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 0.95rem;
            width: 280px;
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
            top: 50%;
            transform: translateY(-50%);
            color: #636e72;
        }

        .filter-select,
        .date-filter {
            padding: 10px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 0.95rem;
            background: #ffffff;
        }

        .records-table-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: rgba(26, 26, 46, 0.05);
        }

        th, td {
            padding: 16px 14px;
            text-align: left;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: #2d3436;
            font-size: 0.95rem;
        }

        th {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge.pending {
            background: rgba(253, 203, 110, 0.2);
            color: #e1a500;
        }

        .badge.overdue {
            background: rgba(243, 69, 69, 0.15);
            color: #d63031;
        }

        .badge.completed {
            background: rgba(0, 184, 148, 0.15);
            color: #00b894;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            padding: 6px 12px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            background: #ffffff;
            cursor: pointer;
            transition: all 0.2s ease;
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

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .summary-card span {
            color: #636e72;
            font-size: 0.9rem;
        }

        .summary-card strong {
            font-size: 1.8rem;
            color: #1a1a2e;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .filters-bar {
                width: 100%;
            }

            .search-box input {
                width: 100%;
            }

            .records-table-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="records-page-container">
        <div class="page-header">
            <div>
                <h1 class="page-title">Handover & Return Records</h1>
                <p class="page-description">Track active loans, due dates, and assets waiting to be returned.</p>
            </div>
            <div class="filters-bar">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by asset, recipient, or ID...">
                </div>
                <select class="filter-select" id="statusFilter">
                    <option value="">All statuses</option>
                    <option value="pending">Pending Return</option>
                    <option value="overdue">Overdue</option>
                    <option value="completed">Completed</option>
                </select>
                <select class="filter-select" id="categoryFilter">
                    <option value="">All categories</option>
                    <option value="laptop">Laptop/Desktop</option>
                    <option value="av">Audio/Visual</option>
                    <option value="network">Network</option>
                    <option value="peripheral">Peripheral</option>
                </select>
                <input type="date" class="date-filter" id="dateFilter">
            </div>
        </div>

        <div class="summary-cards">
            <div class="summary-card">
                <span>Items on loan</span>
                <strong>12</strong>
            </div>
            <div class="summary-card">
                <span>Due this week</span>
                <strong>5</strong>
            </div>
            <div class="summary-card">
                <span>Overdue</span>
                <strong style="color:#d63031;">2</strong>
            </div>
            <div class="summary-card">
                <span>Returned this month</span>
                <strong>18</strong>
            </div>
        </div>

        <div class="records-table-card">
            <table>
                <thead>
                    <tr>
                        <th>Asset ID</th>
                        <th>Category</th>
                        <th>Recipient</th>
                        <th>Issued</th>
                        <th>Due</th>
                        <th>Technician</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="handoverTableBody">
                    <tr>
                        <td><strong>LT-000245</strong></td>
                        <td>Laptop</td>
                        <td>Nur Aisyah<br><small class="text-muted">Academic Affairs</small></td>
                        <td>2025-01-12</td>
                        <td>2025-02-12</td>
                        <td>Farhan Z.</td>
                        <td><span class="badge pending">Pending Return</span></td>
                        <td class="action-buttons">
                            <button class="btn-action"><i class="fa-solid fa-eye"></i> Details</button>
                            <button class="btn-action"><i class="fa-solid fa-clipboard-check"></i> Return</button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>AV-000088</strong></td>
                        <td>Projector</td>
                        <td>Admin Office<br><small class="text-muted">Student Affairs</small></td>
                        <td>2024-12-01</td>
                        <td>2024-12-15</td>
                        <td>Siti N.</td>
                        <td><span class="badge overdue">Overdue</span></td>
                        <td class="action-buttons">
                            <button class="btn-action"><i class="fa-solid fa-envelope"></i> Remind</button>
                            <button class="btn-action"><i class="fa-solid fa-eye"></i> Details</button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>NET-000321</strong></td>
                        <td>Switch</td>
                        <td>Operations Lab<br><small class="text-muted">Engineering</small></td>
                        <td>2024-11-05</td>
                        <td>2024-12-05</td>
                        <td>Amin R.</td>
                        <td><span class="badge completed">Returned</span></td>
                        <td class="action-buttons">
                            <button class="btn-action"><i class="fa-solid fa-file-arrow-down"></i> PDF</button>
                            <button class="btn-action"><i class="fa-solid fa-eye"></i> Details</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>

    <script>
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const categoryFilter = document.getElementById('categoryFilter');
        const dateFilter = document.getElementById('dateFilter');
        const rows = Array.from(document.querySelectorAll('#handoverTableBody tr'));

        function applyFilters() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusTerm = statusFilter.value;
            const categoryTerm = categoryFilter.value;
            const dateTerm = dateFilter.value;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const status = row.querySelector('.badge')?.textContent.toLowerCase() || '';
                const category = row.children[1].textContent.toLowerCase();
                const issuedDate = row.children[3].textContent;

                let visible = true;

                if (searchTerm && !text.includes(searchTerm)) {
                    visible = false;
                }

                if (visible && statusTerm) {
                    visible = status.includes(statusTerm);
                }

                if (visible && categoryTerm) {
                    visible = category.includes(categoryTerm);
                }

                if (visible && dateTerm) {
                    visible = issuedDate === dateTerm;
                }

                row.style.display = visible ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', applyFilters);
        statusFilter.addEventListener('change', applyFilters);
        categoryFilter.addEventListener('change', applyFilters);
        dateFilter.addEventListener('change', applyFilters);
    </script>
</body>
</html>


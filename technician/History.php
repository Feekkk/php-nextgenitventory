
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
    <title>Audit Trail - UniKL RCMP IT Inventory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .history-page-container {
            max-width: 1500px;
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

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #000000;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #636e72;
            max-width: 600px;
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

        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #636e72;
        }

        .search-box input:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .filter-select,
        .date-filter {
            padding: 10px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 0.95rem;
            background: #ffffff;
        }

        .timeline-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }

        .timeline {
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: relative;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 28px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: rgba(26, 26, 46, 0.1);
        }

        .timeline-item {
            position: relative;
            padding-left: 70px;
        }

        .timeline-badge {
            position: absolute;
            left: 12px;
            top: 12px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #1a1a2e;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            box-shadow: 0 8px 20px rgba(26, 26, 46, 0.25);
        }

        .timeline-content {
            background: rgba(26, 26, 46, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .timeline-title {
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 4px;
        }

        .timeline-meta {
            color: #636e72;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .timeline-details {
            color: #2d3436;
            font-size: 0.95rem;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(26, 26, 46, 0.08);
            color: #1a1a2e;
            margin-right: 6px;
        }

        .tag.create {
            background: rgba(0, 184, 148, 0.15);
            color: #00b894;
        }

        .tag.update {
            background: rgba(108, 92, 231, 0.15);
            color: #6c5ce7;
        }

        .tag.delete {
            background: rgba(243, 69, 69, 0.15);
            color: #d63031;
        }

        .tag.handover {
            background: rgba(253, 203, 110, 0.2);
            color: #e1a500;
        }

        .timeline-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action {
            padding: 8px 14px;
            border-radius: 9px;
            border: 1px solid rgba(0, 0, 0, 0.1);
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

        @media (max-width: 768px) {
            .timeline::before {
                left: 18px;
            }
            .timeline-badge {
                left: 2px;
            }
            .timeline-item {
                padding-left: 60px;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="history-page-container">
        <div class="page-header">
            <div>
                <h1>Audit Trail</h1>
                <p>See who changed what across inventory, handover, and disposal modules. Use the filters to focus on a specific asset or technician.</p>
            </div>
            <div class="filters-bar">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by user, asset, action...">
                </div>
                <select class="filter-select" id="moduleFilter">
                    <option value="">All modules</option>
                    <option value="laptop">Laptop/Desktop</option>
                    <option value="av">Audio/Visual</option>
                    <option value="network">Network</option>
                    <option value="handover">Handover</option>
                    <option value="profile">Profile</option>
                </select>
                <select class="filter-select" id="actionFilter">
                    <option value="">All actions</option>
                    <option value="create">Create</option>
                    <option value="update">Update</option>
                    <option value="delete">Delete</option>
                    <option value="handover">Handover</option>
                    <option value="login">Login</option>
                </select>
                <input type="date" class="date-filter" id="dateFilter">
            </div>
        </div>

        <div class="timeline-card">
            <div class="timeline" id="historyTimeline">
                <div class="timeline-item" data-module="handover" data-action="handover" data-date="2025-01-16">
                    <div class="timeline-badge"><i class="fa-solid fa-handshake"></i></div>
                    <div class="timeline-content">
                        <div>
                            <div class="timeline-title">Handover created for LT-000245</div>
                            <div class="timeline-meta">2025-01-16 10:45 · by Farhan Z.</div>
                            <div class="timeline-details">
                                <span class="tag handover">Handover</span>
                                Assigned to Nur Aisyah (Academic Affairs). Expected return 2025-02-16.
                            </div>
                        </div>
                        <div class="timeline-actions">
                            <button class="btn-action"><i class="fa-solid fa-eye"></i> View</button>
                            <button class="btn-action"><i class="fa-solid fa-file-arrow-down"></i> Export</button>
                        </div>
                    </div>
                </div>
                <div class="timeline-item" data-module="network" data-action="update" data-date="2025-01-15">
                    <div class="timeline-badge"><i class="fa-solid fa-plug"></i></div>
                    <div class="timeline-content">
                        <div>
                            <div class="timeline-title">NET-000122 firmware updated</div>
                            <div class="timeline-meta">2025-01-15 18:20 · by Amin R.</div>
                            <div class="timeline-details">
                                <span class="tag update">Update</span>
                                Firmware 17.5.3 → 17.6.2, IP 10.10.10.24, status remains "In Use".
                            </div>
                        </div>
                        <div class="timeline-actions">
                            <button class="btn-action"><i class="fa-solid fa-clipboard-list"></i> Diff</button>
                            <button class="btn-action"><i class="fa-solid fa-eye"></i> View</button>
                        </div>
                    </div>
                </div>
                <div class="timeline-item" data-module="profile" data-action="update" data-date="2025-01-14">
                    <div class="timeline-badge"><i class="fa-solid fa-user-gear"></i></div>
                    <div class="timeline-content">
                        <div>
                            <div class="timeline-title">Profile updated: Siti N.</div>
                            <div class="timeline-meta">2025-01-14 09:05 · by Siti N.</div>
                            <div class="timeline-details">
                                <span class="tag update">Update</span>
                                Changed phone number and office location.
                            </div>
                        </div>
                        <div class="timeline-actions">
                            <button class="btn-action"><i class="fa-solid fa-eye"></i> View</button>
                        </div>
                    </div>
                </div>
                <div class="timeline-item" data-module="av" data-action="create" data-date="2025-01-12">
                    <div class="timeline-badge"><i class="fa-solid fa-tv"></i></div>
                    <div class="timeline-content">
                        <div>
                            <div class="timeline-title">AV-000345 registered</div>
                            <div class="timeline-meta">2025-01-12 11:30 · by Nur F.</div>
                            <div class="timeline-details">
                                <span class="tag create">Create</span>
                                New Epson EB-PU1008 projector installed at Lecture Hall C.
                            </div>
                        </div>
                        <div class="timeline-actions">
                            <button class="btn-action"><i class="fa-solid fa-eye"></i> View</button>
                        </div>
                    </div>
                </div>
                <div class="timeline-item" data-module="laptop" data-action="delete" data-date="2025-01-10">
                    <div class="timeline-badge"><i class="fa-solid fa-trash"></i></div>
                    <div class="timeline-content">
                        <div>
                            <div class="timeline-title">LT-000011 disposal record</div>
                            <div class="timeline-meta">2025-01-10 15:50 · by System</div>
                            <div class="timeline-details">
                                <span class="tag delete">Disposal</span>
                                Auto archival after disposal certificate uploaded.
                            </div>
                        </div>
                        <div class="timeline-actions">
                            <button class="btn-action"><i class="fa-solid fa-file-arrow-down"></i> Certificate</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>

    <script>
        const searchInput = document.getElementById('searchInput');
        const moduleFilter = document.getElementById('moduleFilter');
        const actionFilter = document.getElementById('actionFilter');
        const dateFilter = document.getElementById('dateFilter');
        const items = Array.from(document.querySelectorAll('.timeline-item'));

        function applyFilters() {
            const searchTerm = searchInput.value.toLowerCase();
            const moduleTerm = moduleFilter.value;
            const actionTerm = actionFilter.value;
            const dateTerm = dateFilter.value;

            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                const module = item.dataset.module;
                const action = item.dataset.action;
                const date = item.dataset.date;

                let visible = true;

                if (searchTerm && !text.includes(searchTerm)) visible = false;
                if (visible && moduleTerm && module !== moduleTerm) visible = false;
                if (visible && actionTerm && action !== actionTerm) visible = false;
                if (visible && dateTerm && date !== dateTerm) visible = false;

                item.style.display = visible ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', applyFilters);
        moduleFilter.addEventListener('change', applyFilters);
        actionFilter.addEventListener('change', applyFilters);
        dateFilter.addEventListener('change', applyFilters);
    </script>
</body>
</html>


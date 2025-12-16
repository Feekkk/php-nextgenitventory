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
    <title>User Manual - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .manual-page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        .page-header {
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #64748b;
            font-size: 0.95rem;
        }

        .manual-sections {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .manual-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .manual-section.active {
            border-color: #1a1a2e;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            cursor: pointer;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.15s ease;
        }

        .manual-section.active .section-header {
            background: #f8fafc;
            border-bottom-color: #cbd5e1;
        }

        .section-header:hover {
            background: #f8fafc;
        }

        .section-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-icon {
            width: 36px;
            height: 36px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: #ffffff;
            flex-shrink: 0;
            background: #475569;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 500;
            color: #1e293b;
            margin: 0;
        }

        .section-toggle {
            font-size: 0.9rem;
            color: #64748b;
            transition: transform 0.2s ease;
        }

        .manual-section.active .section-toggle {
            transform: rotate(180deg);
        }

        .section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: #ffffff;
        }

        .manual-section.active .section-content {
            max-height: 2000px;
        }

        .section-body {
            padding: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .section-image-container {
            width: 100%;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            background: #ffffff;
        }

        .section-image {
            width: 100%;
            height: auto;
            display: block;
            object-fit: cover;
        }

        .details-button {
            width: 100%;
            padding: 12px 20px;
            background: #1a1a2e;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s ease;
            text-decoration: none;
        }

        .details-button:hover {
            background: #2d3436;
        }

        .details-button i {
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .manual-page-container {
                padding: 30px 16px 60px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .section-header {
                padding: 16px 20px;
            }

            .section-title {
                font-size: 1.1rem;
            }

            .section-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }

            .section-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="manual-page-container">
        <div class="page-header">
            <h1>User Manual</h1>
            <p>Access comprehensive guides and documentation for the IT Inventory System</p>
        </div>

        <div class="manual-sections">
            <div class="manual-section">
                <div class="section-header" onclick="toggleSection(this)">
                    <div class="section-header-left">
                        <div class="section-icon">
                            <i class="fa fa-laptop"></i>
                        </div>
                        <h2 class="section-title">Getting Started</h2>
                    </div>
                    <i class="fa fa-chevron-down section-toggle"></i>
                </div>
                <div class="section-content">
                    <div class="section-body">
                        <div class="section-image-container">
                            <img src="../manual/gettingStarted.png" alt="Getting Started" class="section-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'800\' height=\'400\'%3E%3Crect fill=\'%23f0f0f0\' width=\'800\' height=\'400\'/%3E%3Ctext fill=\'%23999\' font-family=\'sans-serif\' font-size=\'20\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dominant-baseline=\'middle\'%3EGetting Started Guide%3C/text%3E%3C/svg%3E'">
                        </div>
                        <a href="../manual/getting-started.pdf" target="_blank" class="details-button">
                            <i class="fa fa-file-pdf"></i>
                            View Details PDF
                        </a>
                    </div>
                </div>
            </div>

            <div class="manual-section">
                <div class="section-header" onclick="toggleSection(this)">
                    <div class="section-header-left">
                        <div class="section-icon">
                            <i class="fa fa-boxes"></i>
                        </div>
                        <h2 class="section-title">How to Add Assets</h2>
                    </div>
                    <i class="fa fa-chevron-down section-toggle"></i>
                </div>
                <div class="section-content">
                    <div class="section-body">
                        <div class="section-image-container">
                            <img src="../manual/add_assets.png" alt="Add Assets" class="section-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'800\' height=\'400\'%3E%3Crect fill=\'%23f0f0f0\' width=\'800\' height=\'400\'/%3E%3Ctext fill=\'%23999\' font-family=\'sans-serif\' font-size=\'20\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dominant-baseline=\'middle\'%3EInventory Management Guide%3C/text%3E%3C/svg%3E'">
                        </div>
                        <a href="../manual/inventory-management.pdf" target="_blank" class="details-button">
                            <i class="fa fa-file-pdf"></i>
                            View Details PDF
                        </a>
                    </div>
                </div>
            </div>

            <div class="manual-section">
                <div class="section-header" onclick="toggleSection(this)">
                    <div class="section-header-left">
                        <div class="section-icon">
                            <i class="fa fa-handshake"></i>
                        </div>
                        <h2 class="section-title">Handover Process</h2>
                    </div>
                    <i class="fa fa-chevron-down section-toggle"></i>
                </div>
                <div class="section-content">
                    <div class="section-body">
                        <div class="section-image-container">
                            <img src="../manual/images/handover-process.jpg" alt="Handover Process" class="section-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'800\' height=\'400\'%3E%3Crect fill=\'%23f0f0f0\' width=\'800\' height=\'400\'/%3E%3Ctext fill=\'%23999\' font-family=\'sans-serif\' font-size=\'20\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dominant-baseline=\'middle\'%3EHandover Process Guide%3C/text%3E%3C/svg%3E'">
                        </div>
                        <a href="../manual/handover-process.pdf" target="_blank" class="details-button">
                            <i class="fa fa-file-pdf"></i>
                            View Details PDF
                        </a>
                    </div>
                </div>
            </div>

            <div class="manual-section">
                <div class="section-header" onclick="toggleSection(this)">
                    <div class="section-header-left">
                        <div class="section-icon">
                            <i class="fa fa-cog"></i>
                        </div>
                        <h2 class="section-title">System Features</h2>
                    </div>
                    <i class="fa fa-chevron-down section-toggle"></i>
                </div>
                <div class="section-content">
                    <div class="section-body">
                        <div class="section-image-container">
                            <img src="../manual/images/system-features.jpg" alt="System Features" class="section-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'800\' height=\'400\'%3E%3Crect fill=\'%23f0f0f0\' width=\'800\' height=\'400\'/%3E%3Ctext fill=\'%23999\' font-family=\'sans-serif\' font-size=\'20\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dominant-baseline=\'middle\'%3ESystem Features Guide%3C/text%3E%3C/svg%3E'">
                        </div>
                        <a href="../manual/system-features.pdf" target="_blank" class="details-button">
                            <i class="fa fa-file-pdf"></i>
                            View Details PDF
                        </a>
                    </div>
                </div>
            </div>

            <div class="manual-section">
                <div class="section-header" onclick="toggleSection(this)">
                    <div class="section-header-left">
                        <div class="section-icon">
                            <i class="fa fa-question-circle"></i>
                        </div>
                        <h2 class="section-title">Troubleshooting</h2>
                    </div>
                    <i class="fa fa-chevron-down section-toggle"></i>
                </div>
                <div class="section-content">
                    <div class="section-body">
                        <div class="section-image-container">
                            <img src="../manual/images/troubleshooting.jpg" alt="Troubleshooting" class="section-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'800\' height=\'400\'%3E%3Crect fill=\'%23f0f0f0\' width=\'800\' height=\'400\'/%3E%3Ctext fill=\'%23999\' font-family=\'sans-serif\' font-size=\'20\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dominant-baseline=\'middle\'%3ETroubleshooting Guide%3C/text%3E%3C/svg%3E'">
                        </div>
                        <a href="../manual/troubleshooting.pdf" target="_blank" class="details-button">
                            <i class="fa fa-file-pdf"></i>
                            View Details PDF
                        </a>
                    </div>
                </div>
            </div>

            <div class="manual-section">
                <div class="section-header" onclick="toggleSection(this)">
                    <div class="section-header-left">
                        <div class="section-icon">
                            <i class="fa fa-shield-alt"></i>
                        </div>
                        <h2 class="section-title">Security & Best Practices</h2>
                    </div>
                    <i class="fa fa-chevron-down section-toggle"></i>
                </div>
                <div class="section-content">
                    <div class="section-body">
                        <div class="section-image-container">
                            <img src="../manual/images/security-guide.jpg" alt="Security & Best Practices" class="section-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'800\' height=\'400\'%3E%3Crect fill=\'%23f0f0f0\' width=\'800\' height=\'400\'/%3E%3Ctext fill=\'%23999\' font-family=\'sans-serif\' font-size=\'20\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dominant-baseline=\'middle\'%3ESecurity Guide%3C/text%3E%3C/svg%3E'">
                        </div>
                        <a href="../manual/security-guide.pdf" target="_blank" class="details-button">
                            <i class="fa fa-file-pdf"></i>
                            View Details PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <?php include_once("../components/footer.php"); ?>
    </footer>

    <script>
        function toggleSection(header) {
            const section = header.closest('.manual-section');
            const isActive = section.classList.contains('active');
            
            document.querySelectorAll('.manual-section').forEach(s => {
                s.classList.remove('active');
            });
            
            if (!isActive) {
                section.classList.add('active');
            }
        }
    </script>
</body>
</html>


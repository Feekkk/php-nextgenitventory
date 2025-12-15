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
            font-size: 2.2rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #64748b;
            font-size: 1.05rem;
        }

        .manual-sections {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .manual-section {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
            transition: all 0.3s ease;
        }

        .manual-section.active {
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            cursor: pointer;
            background: #ffffff;
            transition: background 0.2s ease;
        }

        .section-header:hover {
            background: #f8fafc;
        }

        .section-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .section-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #ffffff;
            flex-shrink: 0;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #0f172a;
            margin: 0;
        }

        .section-toggle {
            font-size: 1.2rem;
            color: #64748b;
            transition: transform 0.3s ease;
        }

        .manual-section.active .section-toggle {
            transform: rotate(180deg);
        }

        .section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
            background: #f8fafc;
        }

        .manual-section.active .section-content {
            max-height: 2000px;
        }

        .section-body {
            padding: 24px;
        }

        .section-image-container {
            width: 100%;
            margin-bottom: 20px;
            border-radius: 12px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .section-image {
            width: 100%;
            height: auto;
            display: block;
            object-fit: cover;
        }

        .details-button {
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, #1a1a2e 0%, #2d3436 100%);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .details-button:hover {
            background: linear-gradient(135deg, #2d3436 0%, #1a1a2e 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 26, 46, 0.3);
        }

        .details-button i {
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .manual-page-container {
                padding: 30px 16px 60px;
            }

            .page-header h1 {
                font-size: 1.8rem;
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
            <h1><i class="fa fa-book"></i> User Manual</h1>
            <p>Access comprehensive guides and documentation for the IT Inventory System</p>
        </div>

        <div class="manual-sections">
            <div class="manual-section">
                <div class="section-header" onclick="toggleSection(this)">
                    <div class="section-header-left">
                        <div class="section-icon" style="background: linear-gradient(135deg, #6c5ce7 0%, #5a4dd4 100%);">
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
                        <div class="section-icon" style="background: linear-gradient(135deg, #00cec9 0%, #00b894 100%);">
                            <i class="fa fa-boxes"></i>
                        </div>
                        <h2 class="section-title">Inventory Management</h2>
                    </div>
                    <i class="fa fa-chevron-down section-toggle"></i>
                </div>
                <div class="section-content">
                    <div class="section-body">
                        <div class="section-image-container">
                            <img src="../manual/images/inventory-management.jpg" alt="Inventory Management" class="section-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'800\' height=\'400\'%3E%3Crect fill=\'%23f0f0f0\' width=\'800\' height=\'400\'/%3E%3Ctext fill=\'%23999\' font-family=\'sans-serif\' font-size=\'20\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dominant-baseline=\'middle\'%3EInventory Management Guide%3C/text%3E%3C/svg%3E'">
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
                        <div class="section-icon" style="background: linear-gradient(135deg, #fd79a8 0%, #e84393 100%);">
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
                        <div class="section-icon" style="background: linear-gradient(135deg, #fdcb6e 0%, #e17055 100%);">
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
                        <div class="section-icon" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);">
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
                        <div class="section-icon" style="background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%);">
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


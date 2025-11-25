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
            max-width: 1400px;
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
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .manual-section {
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .manual-section:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 50px rgba(15, 23, 42, 0.12);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid rgba(26, 26, 46, 0.08);
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
            background: linear-gradient(135deg, #6c5ce7 0%, #5a4dd4 100%);
        }

        .section-header h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
            color: #0f172a;
        }

        .section-description {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .pdf-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .pdf-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: #f8fafc;
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            text-decoration: none;
            color: #0f172a;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .pdf-item:hover {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
            transform: translateX(4px);
        }

        .pdf-item:hover .pdf-icon {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }

        .pdf-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: rgba(108, 92, 231, 0.1);
            color: #6c5ce7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .pdf-info {
            flex: 1;
            min-width: 0;
        }

        .pdf-name {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .pdf-meta {
            font-size: 0.85rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pdf-item:hover .pdf-meta {
            color: rgba(255, 255, 255, 0.8);
        }

        .pdf-arrow {
            color: #94a3b8;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .pdf-item:hover .pdf-arrow {
            color: #ffffff;
            transform: translateX(4px);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            color: #cbd5e1;
        }

        .empty-state p {
            margin: 0;
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            .manual-page-container {
                padding: 30px 16px 60px;
            }

            .page-header h1 {
                font-size: 1.8rem;
            }

            .manual-sections {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .section-header h2 {
                font-size: 1.2rem;
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
                <div class="section-header">
                    <div class="section-icon" style="background: linear-gradient(135deg, #6c5ce7 0%, #5a4dd4 100%);">
                        <i class="fa fa-laptop"></i>
                    </div>
                    <h2>Getting Started</h2>
                </div>
                <p class="section-description">Essential guides for new users to get started with the system</p>
                <div class="pdf-list">
                    <a href="../manual/getting-started.pdf" target="_blank" class="pdf-item">
                        <div class="pdf-icon">
                            <i class="fa fa-file-pdf"></i>
                        </div>
                        <div class="pdf-info">
                            <div class="pdf-name">Getting Started Guide</div>
                            <div class="pdf-meta">
                                <i class="fa fa-file"></i>
                                <span>System Introduction</span>
                            </div>
                        </div>
                        <i class="fa fa-arrow-right pdf-arrow"></i>
                    </a>
                    <a href="../manual/quick-start.pdf" target="_blank" class="pdf-item">
                        <div class="pdf-icon">
                            <i class="fa fa-file-pdf"></i>
                        </div>
                        <div class="pdf-info">
                            <div class="pdf-name">Quick Start Guide</div>
                            <div class="pdf-meta">
                                <i class="fa fa-file"></i>
                                <span>Basic Operations</span>
                            </div>
                        </div>
                        <i class="fa fa-arrow-right pdf-arrow"></i>
                    </a>
                </div>
            </div>

            <div class="manual-section">
                <div class="section-header">
                    <div class="section-icon" style="background: linear-gradient(135deg, #00cec9 0%, #00b894 100%);">
                        <i class="fa fa-boxes"></i>
                    </div>
                    <h2>Inventory Management</h2>
                </div>
                <p class="section-description">Learn how to manage and track inventory items effectively</p>
                <div class="pdf-list">
                    <a href="../manual/inventory-management.pdf" target="_blank" class="pdf-item">
                        <div class="pdf-icon">
                            <i class="fa fa-file-pdf"></i>
                        </div>
                        <div class="pdf-info">
                            <div class="pdf-name">Inventory Management</div>
                            <div class="pdf-meta">
                                <i class="fa fa-file"></i>
                                <span>Complete Guide</span>
                            </div>
                        </div>
                        <i class="fa fa-arrow-right pdf-arrow"></i>
                    </a>
                    <a href="../manual/asset-tracking.pdf" target="_blank" class="pdf-item">
                        <div class="pdf-icon">
                            <i class="fa fa-file-pdf"></i>
                        </div>
                        <div class="pdf-info">
                            <div class="pdf-name">Asset Tracking</div>
                            <div class="pdf-meta">
                                <i class="fa fa-file"></i>
                                <span>Tracking Procedures</span>
                            </div>
                        </div>
                        <i class="fa fa-arrow-right pdf-arrow"></i>
                    </a>
                </div>
            </div>

            <div class="manual-section">
                <div class="section-header">
                    <div class="section-icon" style="background: linear-gradient(135deg, #fd79a8 0%, #e84393 100%);">
                        <i class="fa fa-handshake"></i>
                    </div>
                    <h2>Handover Process</h2>
                </div>
                <p class="section-description">Step-by-step instructions for asset handover and return procedures</p>
                <div class="pdf-list">
                    <a href="../manual/handover-process.pdf" target="_blank" class="pdf-item">
                        <div class="pdf-icon">
                            <i class="fa fa-file-pdf"></i>
                        </div>
                        <div class="pdf-info">
                            <div class="pdf-name">Handover Process</div>
                            <div class="pdf-meta">
                                <i class="fa fa-file"></i>
                                <span>Complete Workflow</span>
                            </div>
                        </div>
                        <i class="fa fa-arrow-right pdf-arrow"></i>
                    </a>
                    <a href="../manual/return-procedure.pdf" target="_blank" class="pdf-item">
                        <div class="pdf-icon">
                            <i class="fa fa-file-pdf"></i>
                        </div>
                        <div class="pdf-info">
                            <div class="pdf-name">Return Procedure</div>
                            <div class="pdf-meta">
                                <i class="fa fa-file"></i>
                                <span>Return Guidelines</span>
                            </div>
                        </div>
                        <i class="fa fa-arrow-right pdf-arrow"></i>
                    </a>
                </div>
            </div>

            <div class="manual-section">
                <div class="section-header">
                    <div class="section-icon" style="background: linear-gradient(135deg, #fdcb6e 0%, #e17055 100%);">
                        <i class="fa fa-cog"></i>
                    </div>
                    <h2>System Features</h2>
                </div>
                <p class="section-description">Detailed documentation on system features and functionalities</p>
                <div class="pdf-list">
                    <a href="../manual/system-features.pdf" target="_blank" class="pdf-item">
                        <div class="pdf-icon">
                            <i class="fa fa-file-pdf"></i>
                        </div>
                        <div class="pdf-info">
                            <div class="pdf-name">System Features</div>
                            <div class="pdf-meta">
                                <i class="fa fa-file"></i>
                                <span>Feature Overview</span>
                            </div>
                        </div>
                        <i class="fa fa-arrow-right pdf-arrow"></i>
                    </a>
                    <a href="../manual/user-interface.pdf" target="_blank" class="pdf-item">
                        <div class="pdf-icon">
                            <i class="fa fa-file-pdf"></i>
                        </div>
                        <div class="pdf-info">
                            <div class="pdf-name">User Interface Guide</div>
                            <div class="pdf-meta">
                                <i class="fa fa-file"></i>
                                <span>UI Navigation</span>
                            </div>
                        </div>
                        <i class="fa fa-arrow-right pdf-arrow"></i>
                    </a>
                </div>
            </div>

            <div class="manual-section">
                <div class="section-header">
                    <div class="section-icon" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);">
                        <i class="fa fa-question-circle"></i>
                    </div>
                    <h2>Troubleshooting</h2>
                </div>
                <p class="section-description">Common issues and solutions to help resolve problems quickly</p>
                <div class="pdf-list">
                    <a href="../manual/troubleshooting.pdf" target="_blank" class="pdf-item">
                        <div class="pdf-icon">
                            <i class="fa fa-file-pdf"></i>
                        </div>
                        <div class="pdf-info">
                            <div class="pdf-name">Troubleshooting Guide</div>
                            <div class="pdf-meta">
                                <i class="fa fa-file"></i>
                                <span>Common Issues</span>
                            </div>
                        </div>
                        <i class="fa fa-arrow-right pdf-arrow"></i>
                    </a>
                    <a href="../manual/faq.pdf" target="_blank" class="pdf-item">
                        <div class="pdf-icon">
                            <i class="fa fa-file-pdf"></i>
                        </div>
                        <div class="pdf-info">
                            <div class="pdf-name">Frequently Asked Questions</div>
                            <div class="pdf-meta">
                                <i class="fa fa-file"></i>
                                <span>FAQ Document</span>
                            </div>
                        </div>
                        <i class="fa fa-arrow-right pdf-arrow"></i>
                    </a>
                </div>
            </div>

            <div class="manual-section">
                <div class="section-header">
                    <div class="section-icon" style="background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%);">
                        <i class="fa fa-shield-alt"></i>
                    </div>
                    <h2>Security & Best Practices</h2>
                </div>
                <p class="section-description">Security guidelines and best practices for system usage</p>
                <div class="pdf-list">
                    <a href="../manual/security-guide.pdf" target="_blank" class="pdf-item">
                        <div class="pdf-icon">
                            <i class="fa fa-file-pdf"></i>
                        </div>
                        <div class="pdf-info">
                            <div class="pdf-name">Security Guide</div>
                            <div class="pdf-meta">
                                <i class="fa fa-file"></i>
                                <span>Security Practices</span>
                            </div>
                        </div>
                        <i class="fa fa-arrow-right pdf-arrow"></i>
                    </a>
                    <a href="../manual/best-practices.pdf" target="_blank" class="pdf-item">
                        <div class="pdf-icon">
                            <i class="fa fa-file-pdf"></i>
                        </div>
                        <div class="pdf-info">
                            <div class="pdf-name">Best Practices</div>
                            <div class="pdf-meta">
                                <i class="fa fa-file"></i>
                                <span>Recommended Practices</span>
                            </div>
                        </div>
                        <i class="fa fa-arrow-right pdf-arrow"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <?php include_once("../components/footer.php"); ?>
    </footer>
</body>
</html>


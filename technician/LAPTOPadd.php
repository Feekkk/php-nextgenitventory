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
    <title>Add Laptop/Desktop Asset - UniKL RCMP IT Inventory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .form-page-container {
            max-width: 1200px;
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

        .asset-form {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 15px;
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
            font-size: 0.95rem;
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

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
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

        .btn-primary:hover {
            background: #0f0f1a;
            box-shadow: 0 8px 15px rgba(26, 26, 46, 0.25);
        }

        .btn-secondary {
            background: #f1f2f6;
            color: #2d3436;
        }

        .btn-secondary:hover {
            background: #e3e6ed;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="form-page-container">
        <div class="page-header">
            <h1>Add Laptop/Desktop Asset</h1>
            <p>Register new laptops or desktops, track their assignment, and document key details.</p>
        </div>

        <form class="asset-form">
            <div class="form-section">
                <h3 class="form-section-title">Asset Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="assetId">Asset ID</label>
                        <input type="text" id="assetId" name="assetId" placeholder="e.g., LT-000123">
                    </div>
                    <div class="form-group">
                        <label for="deviceType">Device Type</label>
                        <select id="deviceType" name="deviceType">
                            <option value="">Select device type</option>
                            <option value="laptop">Laptop</option>
                            <option value="desktop">Desktop</option>
                            <option value="workstation">Workstation</option>
                            <option value="all-in-one">All-in-One</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="brand">Brand</label>
                        <input type="text" id="brand" name="brand" placeholder="e.g., Dell">
                    </div>
                    <div class="form-group">
                        <label for="model">Model</label>
                        <input type="text" id="model" name="model" placeholder="e.g., Latitude 7420">
                    </div>
                    <div class="form-group">
                        <label for="serialNumber">Serial Number</label>
                        <input type="text" id="serialNumber" name="serialNumber" placeholder="Enter serial number">
                    </div>
                    <div class="form-group">
                        <label for="assetTag">Asset Tag</label>
                        <input type="text" id="assetTag" name="assetTag" placeholder="Enter asset tag">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Hardware Specifications</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="cpu">CPU</label>
                        <input type="text" id="cpu" name="cpu" placeholder="e.g., Intel Core i7-1185G7">
                    </div>
                    <div class="form-group">
                        <label for="ram">RAM</label>
                        <input type="text" id="ram" name="ram" placeholder="e.g., 16GB DDR4">
                    </div>
                    <div class="form-group">
                        <label for="storage">Storage</label>
                        <input type="text" id="storage" name="storage" placeholder="e.g., 512GB NVMe SSD">
                    </div>
                    <div class="form-group">
                        <label for="gpu">Graphics</label>
                        <input type="text" id="gpu" name="gpu" placeholder="e.g., Intel Iris Xe">
                    </div>
                    <div class="form-group">
                        <label for="os">Operating System</label>
                        <input type="text" id="os" name="os" placeholder="e.g., Windows 11 Pro">
                    </div>
                    <div class="form-group">
                        <label for="additionalSpecs">Additional Specs</label>
                        <input type="text" id="additionalSpecs" name="additionalSpecs" placeholder="e.g., 14\" FHD Touch, Fingerprint Reader">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Deployment Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" placeholder="e.g., IT Office, Block B">
                    </div>
                    <div class="form-group">
                        <label for="assignedTo">Assigned To</label>
                        <input type="text" id="assignedTo" name="assignedTo" placeholder="e.g., John Doe">
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" placeholder="e.g., IT Support">
                    </div>
                    <div class="form-group">
                        <label for="purchaseDate">Purchase Date</label>
                        <input type="date" id="purchaseDate" name="purchaseDate">
                    </div>
                    <div class="form-group">
                        <label for="warrantyExpiry">Warranty Expiry</label>
                        <input type="date" id="warrantyExpiry" name="warrantyExpiry">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">Select status</option>
                            <option value="available">Available</option>
                            <option value="in-use">In Use</option>
                            <option value="maintenance">Under Maintenance</option>
                            <option value="disposed">Disposed</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Additional Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="supplier">Supplier / Vendor</label>
                        <input type="text" id="supplier" name="supplier" placeholder="Enter supplier name">
                    </div>
                    <div class="form-group">
                        <label for="cost">Purchase Cost (MYR)</label>
                        <input type="number" step="0.01" id="cost" name="cost" placeholder="Enter cost">
                    </div>


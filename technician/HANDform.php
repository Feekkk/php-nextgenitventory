
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
    <title>Asset Handover Form - UniKL RCMP IT Inventory</title>
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

        .handover-form {
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

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group label {
            margin: 0;
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
            <h1>Asset Handover Form</h1>
            <p>Document the transfer of equipment between personnel or departments. All fields are captured for traceability.</p>
        </div>

        <form class="handover-form">
            <div class="form-section">
                <h3 class="form-section-title">Asset Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="assetId">Asset ID</label>
                        <input type="text" id="assetId" name="assetId" placeholder="e.g., LT-004512">
                    </div>
                    <div class="form-group">
                        <label for="assetCategory">Category</label>
                        <select id="assetCategory" name="assetCategory">
                            <option value="">Select category</option>
                            <option value="laptop">Laptop/Desktop</option>
                            <option value="av">Audio/Visual</option>
                            <option value="network">Network</option>
                            <option value="peripheral">Peripheral</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="assetBrand">Brand</label>
                        <input type="text" id="assetBrand" name="assetBrand" placeholder="e.g., Dell">
                    </div>
                    <div class="form-group">
                        <label for="assetModel">Model</label>
                        <input type="text" id="assetModel" name="assetModel" placeholder="e.g., Latitude 5440">
                    </div>
                    <div class="form-group">
                        <label for="serialNumber">Serial Number</label>
                        <input type="text" id="serialNumber" name="serialNumber" placeholder="Enter serial number">
                    </div>
                    <div class="form-group">
                        <label for="accessories">Included Accessories</label>
                        <input type="text" id="accessories" name="accessories" placeholder="e.g., Charger, Bag, Docking Station">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Handover Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="handoverType">Handover Type</label>
                        <select id="handoverType" name="handoverType">
                            <option value="">Select type</option>
                            <option value="loan">Loan</option>
                            <option value="permanent">Permanent Assignment</option>
                            <option value="replacement">Replacement</option>
                            <option value="temporary">Temporary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="handoverDate">Handover Date</label>
                        <input type="date" id="handoverDate" name="handoverDate">
                    </div>
                    <div class="form-group">
                        <label for="expectedReturn">Expected Return Date</label>
                        <input type="date" id="expectedReturn" name="expectedReturn">
                    </div>
                    <div class="form-group">
                        <label for="handoverLocation">Location</label>
                        <input type="text" id="handoverLocation" name="handoverLocation" placeholder="e.g., IT Support Office, Block B">
                    </div>
                    <div class="form-group">
                        <label for="handoverBy">Handover By (Technician)</label>
                        <input type="text" id="handoverBy" name="handoverBy" placeholder="Technician full name">
                    </div>
                    <div class="form-group">
                        <label for="handoverTo">Received By</label>
                        <input type="text" id="handoverTo" name="handoverTo" placeholder="Recipient name">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Recipient Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="unitDepartment">Unit / Department</label>
                        <input type="text" id="unitDepartment" name="unitDepartment" placeholder="e.g., Academic Affairs">
                    </div>
                    <div class="form-group">
                        <label for="contactNumber">Contact Number</label>
                        <input type="text" id="contactNumber" name="contactNumber" placeholder="e.g., +60 12-345 6789">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="e.g., user@unikl.edu.my">
                    </div>
                    <div class="form-group">
                        <label for="usagePurpose">Purpose / Justification</label>
                        <textarea id="usagePurpose" name="usagePurpose" placeholder="Describe how the asset will be used"></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Acknowledgements</h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <div class="checkbox-group">
                            <input type="checkbox" id="conditionAgreement" name="conditionAgreement">
                            <label for="conditionAgreement">Recipient confirms asset condition is acceptable and agrees to report any issues immediately.</label>
                        </div>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <div class="checkbox-group">
                            <input type="checkbox" id="policyAgreement" name="policyAgreement">
                            <label for="policyAgreement">Recipient agrees to abide by UniKL IT asset usage policies and return the asset when requested.</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="handoverNotes">Additional Notes</label>
                        <textarea id="handoverNotes" name="handoverNotes" placeholder="Add remarks about condition, software, tags, etc."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="signOff">Digital Sign-off (Recipient)</label>
                        <input type="text" id="signOff" name="signOff" placeholder="Type full name as signature">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Handover</button>
            </div>
        </form>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>
</body>
</html>



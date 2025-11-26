<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_code = trim($_POST['item_code'] ?? '');
    $item_name = trim($_POST['item_name'] ?? '');
    $category = $_POST['category'] ?? 'other';
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $specifications = trim($_POST['specifications'] ?? '');
    $purchase_date = $_POST['purchase_date'] ?? null;
    $purchase_price = $_POST['purchase_price'] ?? null;
    $supplier = trim($_POST['supplier'] ?? '');
    $warranty_period = $_POST['warranty_period'] ?? null;
    $warranty_expiry = $_POST['warranty_expiry'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $status = $_POST['status'] ?? 'available';
    $condition = $_POST['condition'] ?? 'good';
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($item_code) || empty($item_name)) {
        $error = 'Item Code and Item Name are required.';
    } elseif (!in_array($category, ['laptop', 'av', 'network', 'other'])) {
        $error = 'Invalid category selected.';
    } elseif (!in_array($status, ['available', 'assigned', 'maintenance', 'disposed'])) {
        $error = 'Invalid status selected.';
    } elseif (!in_array($condition, ['new', 'good', 'fair', 'poor'])) {
        $error = 'Invalid condition selected.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM inventory WHERE item_code = ?");
            $stmt->execute([$item_code]);
            if ($stmt->fetch()) {
                $error = 'Item code already exists.';
            } else {
                $purchase_price = !empty($purchase_price) ? $purchase_price : null;
                $purchase_date = !empty($purchase_date) ? $purchase_date : null;
                $warranty_period = !empty($warranty_period) ? (int)$warranty_period : null;
                $warranty_expiry = !empty($warranty_expiry) ? $warranty_expiry : null;
                
                $stmt = $pdo->prepare("INSERT INTO inventory (item_code, item_name, category, brand, model, serial_number, specifications, purchase_date, purchase_price, supplier, warranty_period, warranty_expiry, location, status, condition, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $item_code,
                    $item_name,
                    $category,
                    $brand ?: null,
                    $model ?: null,
                    $serial_number ?: null,
                    $specifications ?: null,
                    $purchase_date,
                    $purchase_price,
                    $supplier ?: null,
                    $warranty_period,
                    $warranty_expiry,
                    $location ?: null,
                    $status,
                    $condition,
                    $notes ?: null,
                    $_SESSION['user_id']
                ]);
                
                $success = 'Inventory item added successfully!';
                header("refresh:2;url=ADDitems.php");
            }
        } catch (PDOException $e) {
            $error = 'Failed to add inventory item. Please try again.';
            error_log("Inventory insert error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Inventory Item - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .add-item-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .back-btn {
            padding: 8px 16px;
            background: #f8f9fa;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            color: #2d3436;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .back-btn:hover {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #000000;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 40px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .alert {
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
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

        .form-section {
            margin-bottom: 35px;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(26, 26, 46, 0.1);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3436;
            font-size: 0.95rem;
        }

        .form-group label.required::after {
            content: ' *';
            color: #d63031;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #636e72;
            z-index: 1;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group input[type="email"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 0.95rem;
            color: #2d3436;
            transition: all 0.2s ease;
            background: #ffffff;
            font-family: 'Inter', sans-serif;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
            padding-top: 12px;
        }

        .form-group select {
            padding-left: 44px;
            cursor: pointer;
        }

        .input-wrapper .input-icon {
            pointer-events: none;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }

        .field-hint {
            margin-top: 6px;
            font-size: 0.85rem;
            color: #636e72;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .btn {
            padding: 12px 24px;
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

        .btn-primary {
            background: #1a1a2e;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #0f0f1a;
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.3);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #2d3436;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .btn-secondary:hover {
            background: #e9ecef;
        }

        @media (max-width: 768px) {
            .add-item-container {
                padding: 20px 15px;
            }

            .form-card {
                padding: 25px 20px;
            }

            .form-row,
            .form-row-3 {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/ADMINheader.php"); ?>

    <div class="add-item-container">
        <div class="page-header">
            <a href="Dashboard.php" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i>
                Back
            </a>
            <h1 class="page-title">Add Inventory Item</h1>
        </div>

        <div class="form-card">
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

            <form method="POST" action="">
                <div class="form-section">
                    <h3 class="form-section-title">Basic Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="item_code" class="required">Item Code</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-barcode input-icon"></i>
                                <input type="text" id="item_code" name="item_code" value="<?php echo htmlspecialchars($_POST['item_code'] ?? ''); ?>" required placeholder="e.g., IT-LAP-001">
                            </div>
                            <p class="field-hint">Unique identifier for this item</p>
                        </div>

                        <div class="form-group">
                            <label for="item_name" class="required">Item Name</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-box input-icon"></i>
                                <input type="text" id="item_name" name="item_name" value="<?php echo htmlspecialchars($_POST['item_name'] ?? ''); ?>" required placeholder="e.g., Dell Laptop">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category" class="required">Category</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-tags input-icon"></i>
                                <select id="category" name="category" required>
                                    <option value="laptop" <?php echo (isset($_POST['category']) && $_POST['category'] === 'laptop') ? 'selected' : ''; ?>>Laptop</option>
                                    <option value="av" <?php echo (isset($_POST['category']) && $_POST['category'] === 'av') ? 'selected' : ''; ?>>AV Equipment</option>
                                    <option value="network" <?php echo (isset($_POST['category']) && $_POST['category'] === 'network') ? 'selected' : ''; ?>>Network Equipment</option>
                                    <option value="other" <?php echo (!isset($_POST['category']) || $_POST['category'] === 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="status" class="required">Status</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-toggle-on input-icon"></i>
                                <select id="status" name="status" required>
                                    <option value="available" <?php echo (!isset($_POST['status']) || $_POST['status'] === 'available') ? 'selected' : ''; ?>>Available</option>
                                    <option value="assigned" <?php echo (isset($_POST['status']) && $_POST['status'] === 'assigned') ? 'selected' : ''; ?>>Assigned</option>
                                    <option value="maintenance" <?php echo (isset($_POST['status']) && $_POST['status'] === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                    <option value="disposed" <?php echo (isset($_POST['status']) && $_POST['status'] === 'disposed') ? 'selected' : ''; ?>>Disposed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="condition" class="required">Condition</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-star input-icon"></i>
                                <select id="condition" name="condition" required>
                                    <option value="new" <?php echo (isset($_POST['condition']) && $_POST['condition'] === 'new') ? 'selected' : ''; ?>>New</option>
                                    <option value="good" <?php echo (!isset($_POST['condition']) || $_POST['condition'] === 'good') ? 'selected' : ''; ?>>Good</option>
                                    <option value="fair" <?php echo (isset($_POST['condition']) && $_POST['condition'] === 'fair') ? 'selected' : ''; ?>>Fair</option>
                                    <option value="poor" <?php echo (isset($_POST['condition']) && $_POST['condition'] === 'poor') ? 'selected' : ''; ?>>Poor</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="location">Location</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-map-marker-alt input-icon"></i>
                                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" placeholder="e.g., Lab 1, Office 201">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">Product Details</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="brand">Brand</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-trademark input-icon"></i>
                                <input type="text" id="brand" name="brand" value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>" placeholder="e.g., Dell, HP, Lenovo">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="model">Model</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-cube input-icon"></i>
                                <input type="text" id="model" name="model" value="<?php echo htmlspecialchars($_POST['model'] ?? ''); ?>" placeholder="e.g., Latitude 5520">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="serial_number">Serial Number</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-hashtag input-icon"></i>
                            <input type="text" id="serial_number" name="serial_number" value="<?php echo htmlspecialchars($_POST['serial_number'] ?? ''); ?>" placeholder="Serial number of the item">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="specifications">Specifications</label>
                        <textarea id="specifications" name="specifications" placeholder="Enter detailed specifications (e.g., Processor, RAM, Storage, etc.)"><?php echo htmlspecialchars($_POST['specifications'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">Purchase Information</h3>
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="purchase_date">Purchase Date</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-calendar input-icon"></i>
                                <input type="date" id="purchase_date" name="purchase_date" value="<?php echo htmlspecialchars($_POST['purchase_date'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="purchase_price">Purchase Price (RM)</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-dollar-sign input-icon"></i>
                                <input type="number" id="purchase_price" name="purchase_price" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['purchase_price'] ?? ''); ?>" placeholder="0.00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="supplier">Supplier</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-truck input-icon"></i>
                                <input type="text" id="supplier" name="supplier" value="<?php echo htmlspecialchars($_POST['supplier'] ?? ''); ?>" placeholder="Supplier name">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="warranty_period">Warranty Period (Months)</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-shield-alt input-icon"></i>
                                <input type="number" id="warranty_period" name="warranty_period" min="0" value="<?php echo htmlspecialchars($_POST['warranty_period'] ?? ''); ?>" placeholder="e.g., 12, 24">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="warranty_expiry">Warranty Expiry Date</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-calendar-times input-icon"></i>
                                <input type="date" id="warranty_expiry" name="warranty_expiry" value="<?php echo htmlspecialchars($_POST['warranty_expiry'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">Additional Notes</h3>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" placeholder="Any additional notes or remarks about this item"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset Form
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        Add Inventory Item
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <?php include_once("../components/footer.php"); ?>
    </footer>
</body>
</html>


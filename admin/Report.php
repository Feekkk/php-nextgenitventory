<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$currentDate = date('F Y');
$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .reports-page-container {
            max-width: 1800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .report-title-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #000000;
            margin: 0 0 8px 0;
        }

        .report-title-section p {
            color: #636e72;
            font-size: 1rem;
            margin: 0;
        }

        .report-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-export {
            padding: 12px 24px;
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

        .btn-export:hover {
            background: #0f0f1a;
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.3);
        }

        .date-filter {
            padding: 10px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 0.95rem;
            background: #ffffff;
            color: #2d3436;
            cursor: pointer;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 25px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .kpi-title {
            font-size: 0.9rem;
            color: #636e72;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #ffffff;
        }

        .kpi-icon.primary {
            background: linear-gradient(135deg, #6c5ce7 0%, #5a4dd4 100%);
        }

        .kpi-icon.success {
            background: linear-gradient(135deg, #00b894 0%, #00a085 100%);
        }

        .kpi-icon.warning {
            background: linear-gradient(135deg, #fdcb6e 0%, #e17055 100%);
        }

        .kpi-icon.danger {
            background: linear-gradient(135deg, #d63031 0%, #c0392b 100%);
        }

        .kpi-icon.info {
            background: linear-gradient(135deg, #0984e3 0%, #0770c4 100%);
        }

        .kpi-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2d3436;
            margin: 10px 0;
        }

        .kpi-change {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .kpi-change.positive {
            color: #00b894;
        }

        .kpi-change.negative {
            color: #d63031;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 30px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .chart-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2d3436;
        }

        .chart-body {
            min-height: 250px;
        }

        .progress-bar-container {
            margin-bottom: 20px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #636e72;
        }

        .progress-label strong {
            color: #2d3436;
        }

        .progress-bar {
            height: 12px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .progress-fill.primary {
            background: linear-gradient(90deg, #6c5ce7 0%, #5a4dd4 100%);
        }

        .progress-fill.success {
            background: linear-gradient(90deg, #00b894 0%, #00a085 100%);
        }

        .progress-fill.warning {
            background: linear-gradient(90deg, #fdcb6e 0%, #e17055 100%);
        }

        .progress-fill.danger {
            background: linear-gradient(90deg, #d63031 0%, #c0392b 100%);
        }

        .progress-fill.info {
            background: linear-gradient(90deg, #0984e3 0%, #0770c4 100%);
        }

        .bar-chart {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 250px;
            gap: 15px;
            padding: 20px 0;
        }

        .bar-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .bar {
            width: 100%;
            background: linear-gradient(180deg, #6c5ce7 0%, #5a4dd4 100%);
            border-radius: 8px 8px 0 0;
            transition: all 0.3s ease;
            min-height: 20px;
        }

        .bar:hover {
            opacity: 0.8;
            transform: scaleY(1.05);
        }

        .bar-label {
            font-size: 0.85rem;
            color: #636e72;
            font-weight: 500;
        }

        .bar-value {
            font-size: 0.9rem;
            color: #2d3436;
            font-weight: 600;
        }

        .data-table-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 30px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2d3436;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: rgba(26, 26, 46, 0.05);
        }

        .data-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2d3436;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(26, 26, 46, 0.1);
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: #2d3436;
            font-size: 0.95rem;
        }

        .data-table tbody tr {
            transition: all 0.2s ease;
        }

        .data-table tbody tr:hover {
            background: rgba(26, 26, 46, 0.03);
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-success {
            background: rgba(0, 184, 148, 0.1);
            color: #00b894;
        }

        .badge-warning {
            background: rgba(253, 203, 110, 0.1);
            color: #e17055;
        }

        .badge-danger {
            background: rgba(214, 48, 49, 0.1);
            color: #d63031;
        }

        .badge-info {
            background: rgba(9, 132, 227, 0.1);
            color: #0984e3;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
            background: rgba(26, 26, 46, 0.03);
            border-radius: 12px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #636e72;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media (max-width: 768px) {
            .report-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .kpi-grid {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            .report-actions {
                display: none;
            }

            .kpi-card, .chart-card, .data-table-card {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/ADMINheader.php"); ?>

    <div class="reports-page-container">
        <div class="report-header">
            <div class="report-title-section">
                <h1>Inventory Reports</h1>
                <p>Comprehensive overview for <?php echo $currentDate; ?></p>
            </div>
            <div class="report-actions">
                <select class="date-filter" id="dateFilter">
                    <option value="month">This Month</option>
                    <option value="quarter">This Quarter</option>
                    <option value="year" selected>This Year</option>
                    <option value="all">All Time</option>
                </select>
                <button class="btn-export" onclick="window.print()">
                    <i class="fa-solid fa-print"></i>
                    Print Report
                </button>
                <button class="btn-export" onclick="exportToPDF()">
                    <i class="fa-solid fa-file-pdf"></i>
                    Export PDF
                </button>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Total Assets</span>
                    <div class="kpi-icon primary">
                        <i class="fa-solid fa-boxes"></i>
                    </div>
                </div>
                <div class="kpi-value">1,247</div>
                <div class="kpi-change positive">
                    <i class="fa-solid fa-arrow-up"></i>
                    <span>+12% from last month</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Active Users</span>
                    <div class="kpi-icon success">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <div class="kpi-value">48</div>
                <div class="kpi-change positive">
                    <i class="fa-solid fa-arrow-up"></i>
                    <span>+3 new users</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Handovers</span>
                    <div class="kpi-icon info">
                        <i class="fa-solid fa-handshake"></i>
                    </div>
                </div>
                <div class="kpi-value">342</div>
                <div class="kpi-change positive">
                    <i class="fa-solid fa-arrow-up"></i>
                    <span>+18% from last month</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Maintenance</span>
                    <div class="kpi-icon warning">
                        <i class="fa-solid fa-tools"></i>
                    </div>
                </div>
                <div class="kpi-value">23</div>
                <div class="kpi-change negative">
                    <i class="fa-solid fa-arrow-down"></i>
                    <span>-5 from last month</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Utilization Rate</span>
                    <div class="kpi-icon success">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                </div>
                <div class="kpi-value">87%</div>
                <div class="kpi-change positive">
                    <i class="fa-solid fa-arrow-up"></i>
                    <span>+4% improvement</span>
                </div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Asset Distribution by Category</h3>
                </div>
                <div class="chart-body">
                    <div class="progress-bar-container">
                        <div class="progress-label">
                            <strong>Laptops & Desktops</strong>
                            <span>542 (43.5%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill primary" style="width: 43.5%"></div>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-label">
                            <strong>AV Equipment</strong>
                            <span>368 (29.5%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill success" style="width: 29.5%"></div>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-label">
                            <strong>Network Equipment</strong>
                            <span>337 (27.0%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill info" style="width: 27%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Asset Status Overview</h3>
                </div>
                <div class="chart-body">
                    <div class="progress-bar-container">
                        <div class="progress-label">
                            <strong>In Use</strong>
                            <span>892 (71.5%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill success" style="width: 71.5%"></div>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-label">
                            <strong>Available</strong>
                            <span>232 (18.6%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill info" style="width: 18.6%"></div>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-label">
                            <strong>Maintenance</strong>
                            <span>89 (7.1%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill warning" style="width: 7.1%"></div>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-label">
                            <strong>Disposed</strong>
                            <span>34 (2.7%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill danger" style="width: 2.7%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Monthly Handover Trends</h3>
                </div>
                <div class="chart-body">
                    <div class="bar-chart">
                        <div class="bar-item">
                            <div class="bar-value">42</div>
                            <div class="bar" style="height: 60%"></div>
                            <div class="bar-label">Jan</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">38</div>
                            <div class="bar" style="height: 54%"></div>
                            <div class="bar-label">Feb</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">45</div>
                            <div class="bar" style="height: 64%"></div>
                            <div class="bar-label">Mar</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">52</div>
                            <div class="bar" style="height: 74%"></div>
                            <div class="bar-label">Apr</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">48</div>
                            <div class="bar" style="height: 69%"></div>
                            <div class="bar-label">May</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">55</div>
                            <div class="bar" style="height: 79%"></div>
                            <div class="bar-label">Jun</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">61</div>
                            <div class="bar" style="height: 87%"></div>
                            <div class="bar-label">Jul</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">58</div>
                            <div class="bar" style="height: 83%"></div>
                            <div class="bar-label">Aug</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">49</div>
                            <div class="bar" style="height: 70%"></div>
                            <div class="bar-label">Sep</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">54</div>
                            <div class="bar" style="height: 77%"></div>
                            <div class="bar-label">Oct</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">47</div>
                            <div class="bar" style="height: 67%"></div>
                            <div class="bar-label">Nov</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">52</div>
                            <div class="bar" style="height: 74%"></div>
                            <div class="bar-label">Dec</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Asset Acquisition by Month</h3>
                </div>
                <div class="chart-body">
                    <div class="bar-chart">
                        <div class="bar-item">
                            <div class="bar-value">28</div>
                            <div class="bar" style="height: 45%; background: linear-gradient(180deg, #00b894 0%, #00a085 100%);"></div>
                            <div class="bar-label">Jan</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">35</div>
                            <div class="bar" style="height: 56%; background: linear-gradient(180deg, #00b894 0%, #00a085 100%);"></div>
                            <div class="bar-label">Feb</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">42</div>
                            <div class="bar" style="height: 68%; background: linear-gradient(180deg, #00b894 0%, #00a085 100%);"></div>
                            <div class="bar-label">Mar</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">31</div>
                            <div class="bar" style="height: 50%; background: linear-gradient(180deg, #00b894 0%, #00a085 100%);"></div>
                            <div class="bar-label">Apr</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">39</div>
                            <div class="bar" style="height: 63%; background: linear-gradient(180deg, #00b894 0%, #00a085 100%);"></div>
                            <div class="bar-label">May</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">46</div>
                            <div class="bar" style="height: 74%; background: linear-gradient(180deg, #00b894 0%, #00a085 100%);"></div>
                            <div class="bar-label">Jun</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">52</div>
                            <div class="bar" style="height: 84%; background: linear-gradient(180deg, #00b894 0%, #00a085 100%);"></div>
                            <div class="bar-label">Jul</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">48</div>
                            <div class="bar" style="height: 77%; background: linear-gradient(180deg, #00b894 0%, #00a085 100%);"></div>
                            <div class="bar-label">Aug</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">41</div>
                            <div class="bar" style="height: 66%; background: linear-gradient(180deg, #00b894 0%, #00a085 100%);"></div>
                            <div class="bar-label">Sep</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">37</div>
                            <div class="bar" style="height: 60%; background: linear-gradient(180deg, #00b894 0%, #00a085 100%);"></div>
                            <div class="bar-label">Oct</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">33</div>
                            <div class="bar" style="height: 53%; background: linear-gradient(180deg, #00b894 0%, #00a085 100%);"></div>
                            <div class="bar-label">Nov</div>
                        </div>
                        <div class="bar-item">
                            <div class="bar-value">29</div>
                            <div class="bar" style="height: 47%; background: linear-gradient(180deg, #00b894 0%, #00a085 100%);"></div>
                            <div class="bar-label">Dec</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="data-table-card">
            <div class="table-header">
                <h3 class="table-title">Top Performing Technicians</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Technician</th>
                        <th>Staff ID</th>
                        <th>Handovers</th>
                        <th>Assets Managed</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>#1</strong></td>
                        <td>Farhan Zulkifli</td>
                        <td>STF-2021-001</td>
                        <td>142</td>
                        <td>89</td>
                        <td><span class="badge badge-success">Active</span></td>
                    </tr>
                    <tr>
                        <td><strong>#2</strong></td>
                        <td>Siti Nur Aisyah</td>
                        <td>STF-2021-015</td>
                        <td>128</td>
                        <td>76</td>
                        <td><span class="badge badge-success">Active</span></td>
                    </tr>
                    <tr>
                        <td><strong>#3</strong></td>
                        <td>Ahmad Firdaus</td>
                        <td>STF-2022-008</td>
                        <td>115</td>
                        <td>68</td>
                        <td><span class="badge badge-success">Active</span></td>
                    </tr>
                    <tr>
                        <td><strong>#4</strong></td>
                        <td>Nurul Huda</td>
                        <td>STF-2021-022</td>
                        <td>98</td>
                        <td>62</td>
                        <td><span class="badge badge-success">Active</span></td>
                    </tr>
                    <tr>
                        <td><strong>#5</strong></td>
                        <td>Muhammad Hafiz</td>
                        <td>STF-2022-012</td>
                        <td>87</td>
                        <td>54</td>
                        <td><span class="badge badge-success">Active</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="data-table-card">
            <div class="table-header">
                <h3 class="table-title">Recent Handover Activities</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Asset ID</th>
                        <th>Category</th>
                        <th>Recipient</th>
                        <th>Technician</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>2025-01-15</td>
                        <td><strong>LT-000245</strong></td>
                        <td>Laptop</td>
                        <td>Dr. Sarah Ahmad</td>
                        <td>Farhan Z.</td>
                        <td><span class="badge badge-info">Active</span></td>
                    </tr>
                    <tr>
                        <td>2025-01-14</td>
                        <td><strong>AV-000088</strong></td>
                        <td>Projector</td>
                        <td>Academic Affairs</td>
                        <td>Siti N.</td>
                        <td><span class="badge badge-info">Active</span></td>
                    </tr>
                    <tr>
                        <td>2025-01-13</td>
                        <td><strong>NET-000156</strong></td>
                        <td>Router</td>
                        <td>IT Department</td>
                        <td>Ahmad F.</td>
                        <td><span class="badge badge-success">Completed</span></td>
                    </tr>
                    <tr>
                        <td>2025-01-12</td>
                        <td><strong>LT-000189</strong></td>
                        <td>Desktop</td>
                        <td>Admin Office</td>
                        <td>Nurul H.</td>
                        <td><span class="badge badge-info">Active</span></td>
                    </tr>
                    <tr>
                        <td>2025-01-11</td>
                        <td><strong>AV-000092</strong></td>
                        <td>Sound System</td>
                        <td>Event Hall</td>
                        <td>Muhammad H.</td>
                        <td><span class="badge badge-success">Completed</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="data-table-card">
            <div class="table-header">
                <h3 class="table-title">Asset Summary Statistics</h3>
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value">542</div>
                    <div class="stat-label">Laptops</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">368</div>
                    <div class="stat-label">AV Equipment</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">337</div>
                    <div class="stat-label">Network Devices</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">892</div>
                    <div class="stat-label">In Use</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">232</div>
                    <div class="stat-label">Available</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">89</div>
                    <div class="stat-label">Maintenance</div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <?php include_once("../components/footer.php"); ?>
    </footer>

    <script>
        function exportToPDF() {
            alert('PDF export functionality will be implemented soon. For now, please use the Print function and save as PDF.');
        }

        document.getElementById('dateFilter').addEventListener('change', function() {
            const filter = this.value;
            console.log('Filter changed to:', filter);
        });
    </script>
</body>
</html>


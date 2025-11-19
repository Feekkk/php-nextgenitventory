<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniKL RCMP - IT Inventory System</title>
    <link rel="icon" type="image/png" href="public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="background-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <?php include 'components/header.php'; ?>

    <section class="hero" id="home">
        <h1 id="typed-title"></h1>
        <p>Streamline your inventory, track assets, and manage handovers with the power of modern technology. Designed for UniKL RCMP IT Department.</p>
        <a href="#features" class="btn-cta" style="background-color: #6c5ce7; color: #fff; border: none;">Explore Features</a>
    </section>
    <section class="features" id="features">
        <h2 class="section-title">Core Capabilities</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-server"></i></div>
                <h3>IT Assets</h3>
                <p>Comprehensive tracking of all hardware and software assets. Real-time status updates and detailed specifications.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-handshake"></i></div>
                <h3>Handover Process</h3>
                <p>Digitalize the handover process with secure logging, digital signatures, and automated notifications.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-recycle"></i></div>
                <h3>Disposal</h3>
                <p>Manage the end-of-life cycle for equipment. Compliant disposal tracking and documentation.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-chart-pie"></i></div>
                <h3>Data Analytics</h3>
                <p>Visual insights into asset utilization, age distribution, and department allocation.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <h3>History</h3>
                <p>Complete audit trails for every item. Track movement, maintenance, and ownership history.</p>
            </div>
        </div>
    </section>

    <?php include 'components/footer.php'; ?>

    <script src="js/home.js"></script>
</body>
</html>
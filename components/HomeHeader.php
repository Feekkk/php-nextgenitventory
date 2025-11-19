<style>
:root {
    --primary-color: #6c5ce7;
    --secondary-color: #00cec9;
    --accent-color: #fd79a8;
    --header-bg: #ffffff;
    --sidebar-bg: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
    --text-dark: #2d3436;
    --text-muted: #636e72;
    --border-color: rgba(0, 0, 0, 0.08);
    --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.08);
    --shadow-hover: 0 8px 30px rgba(108, 92, 231, 0.15);
    --gradient-primary: linear-gradient(135deg, #6c5ce7 0%, #5a4dd4 100%);
    --gradient-secondary: linear-gradient(135deg, #00cec9 0%, #00b894 100%);
    --gradient-accent: linear-gradient(135deg, #fd79a8 0%, #e84393 100%);
    --gradient-mixed: linear-gradient(135deg, #6c5ce7 0%, #00cec9 50%, #fd79a8 100%);
}

/* Sidebar styles */
.sidebar {
    position: fixed;
    top: 0;
    left: -280px;
    width: 280px;
    height: 100vh;
    background: #ffffff;
    color: var(--text-dark);
    z-index: 1100;
    transition: left 0.3s ease;
    box-shadow: 2px 0 20px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
    border-right: 1px solid var(--border-color);
}

.sidebar::-webkit-scrollbar {
    display: none;
}

.sidebar.open {
    left: 0;
}

.sidebar .sidebar-header {
    padding: 25px 20px;
    background: #ffffff;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar .sidebar-header img {
    height: 38px;
}

.sidebar .sidebar-header span {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-dark);
    letter-spacing: 0.3px;
}

.close-sidebar-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-muted);
    font-size: 1.4rem;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.close-sidebar-btn:hover {
    background: #1a1a2e;
    border-color: #1a1a2e;
    color: #ffffff;
}

.sidebar ul {
    list-style: none;
    padding: 20px 0;
    margin: 0;
    flex: 1;
}

.sidebar ul li {
    margin: 0;
}

.sidebar ul li a {
    display: flex;
    align-items: center;
    padding: 14px 24px;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
    gap: 14px;
}

.sidebar ul li a i {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
    color: var(--text-muted);
    transition: color 0.2s ease;
}

.sidebar ul li a:hover {
    background: #1a1a2e;
    color: #ffffff;
    border-left-color: #1a1a2e;
}

.sidebar ul li a:hover i {
    color: #ffffff;
}

.sidebar ul li a.active {
    background: #1a1a2e;
    color: #ffffff;
    border-left-color: #1a1a2e;
    font-weight: 600;
}

.sidebar ul li a.active i {
    color: #ffffff;
}

/* Header styles */
.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
    color: var(--text-dark);
    height: 70px;
    padding: 0 30px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1050;
    font-family: 'Inter', sans-serif;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border-bottom: 1px solid var(--border-color);
}

.menu-btn {
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-dark);
    font-size: 1.2rem;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    margin-right: 15px;
}

.menu-btn:hover {
    background: #1a1a2e;
    border-color: #1a1a2e;
    color: #ffffff;
}

.header-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo {
    display: flex;
    align-items: center;
    font-size: 1.3rem;
    font-weight: 600;
    gap: 12px;
    text-decoration: none;
    color: var(--text-dark);
    transition: opacity 0.2s ease;
}

.logo:hover {
    opacity: 0.8;
}

.logo img {
    height: 38px;
}

.logo span {
    color: var(--text-dark);
    letter-spacing: 0.3px;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.user-menu {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    background: #f8f9fa;
    border: 1px solid var(--border-color);
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.user-menu:hover {
    background: #1a1a2e;
    border-color: #1a1a2e;
}

.user-menu:hover .user-name {
    color: #ffffff;
}

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 600;
    font-size: 0.85rem;
}

.user-name {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-dark);
}

/* Overlay blur effect */
.blur-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(8px);
    z-index: 1090;
    display: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.blur-overlay.open {
    display: block;
    opacity: 1;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 260px;
        left: -260px;
    }

    .header {
        height: 65px;
        padding: 0 20px;
    }

    .logo span {
        font-size: 1.1rem;
    }

    .user-name {
        display: none;
    }

    .menu-btn {
        width: 38px;
        height: 38px;
        font-size: 1.1rem;
    }

    .logo img {
        height: 36px;
    }
}

@media (max-width: 480px) {
    .sidebar {
        width: 240px;
        left: -240px;
    }

    .header {
        padding: 0 15px;
        height: 60px;
    }

    .logo span {
        display: none;
    }

    .logo img {
        height: 32px;
    }

    .menu-btn {
        width: 36px;
        height: 36px;
        margin-right: 10px;
        font-size: 1rem;
    }
}
</style>

<div id="header-root">
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <button class="close-sidebar-btn" id="closeSidebarBtn" aria-label="Close menu">
            <i class="fa fa-times"></i>
        </button>
        <div class="sidebar-header">
            <img src="../public/unikl-rcmp.png" alt="UniKL RCMP Logo">
            <span>Menu</span>
        </div>
        <ul>
            <li><a href="../index.php"><i class="fa fa-home"></i> Home</a></li>
            <li><a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a></li>
            <li><a href="../auth/login.php"><i class="fa fa-right-to-bracket"></i> Login</a></li>
            <li><a href="../auth/register.php"><i class="fa fa-user-plus"></i> Register</a></li>
        </ul>
    </nav>
    
    <!-- Main header -->
    <header class="header">
        <button class="menu-btn" id="openSidebarBtn" aria-label="Open menu">
            <i class="fa fa-bars"></i>
        </button>
        <div class="header-content">
            <a class="logo" href="../index.php">
                <img src="../public/unikl-rcmp.png" alt="UniKL RCMP Logo">
                <span>UniKL RCMP IT Inventory</span>
            </a>
            <div class="header-actions">
                <?php if (isset($_SESSION['full_name'])): ?>
                    <div class="user-menu">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <!-- Blur Overlay -->
    <div class="blur-overlay" id="blurOverlay"></div>
</div>

<script>
// Sidebar & Blur Logic
const sidebar = document.getElementById('sidebar');
const blurOverlay = document.getElementById('blurOverlay');
const openSidebarBtn = document.getElementById('openSidebarBtn');
const closeSidebarBtn = document.getElementById('closeSidebarBtn');

function openSidebar() {
    sidebar.classList.add('open');
    blurOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    sidebar.classList.remove('open');
    blurOverlay.classList.remove('open');
    document.body.style.overflow = '';
}

openSidebarBtn.addEventListener('click', openSidebar);
closeSidebarBtn.addEventListener('click', closeSidebar);
blurOverlay.addEventListener('click', closeSidebar);

window.addEventListener('keydown', function (e) {
    if (e.key === "Escape" && sidebar.classList.contains('open')) {
        closeSidebar();
    }
});

// Set active menu item based on current page
document.addEventListener('DOMContentLoaded', function() {
    const currentPath = window.location.pathname;
    const menuLinks = document.querySelectorAll('.sidebar ul li a');
    
    menuLinks.forEach(link => {
        if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('active');
        }
    });
});
</script>

<!-- FontAwesome CDN for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

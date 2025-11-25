<?php
$profile_picture = null;
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../database/config.php';
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT profile_picture FROM technician WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        if ($result && !empty($result['profile_picture'])) {
            $profile_picture = $result['profile_picture'];
        }
    } catch (Exception $e) {
        // Silently fail if database query fails
    }
}
?>
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

/* Sidebar footer status */
.sidebar-status {
    margin: 20px;
    padding: 16px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(108, 92, 231, 0.12), rgba(0, 206, 201, 0.12));
    border: 1px solid rgba(0, 0, 0, 0.05);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04);
}

.sidebar-status h4 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 700;
    color: #1a1a2e;
}

.sidebar-status p {
    margin: 6px 0 0 0;
    font-size: 0.85rem;
    color: #2d3436;
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #00b894;
    box-shadow: 0 0 8px rgba(0, 184, 148, 0.7);
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

.sidebar .menu-item {
    position: relative;
}

.sidebar .menu-item.has-dropdown > a::after {
    content: '\f107';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    margin-left: auto;
    font-size: 0.9rem;
    transition: transform 0.3s ease;
}

.sidebar .menu-item.has-dropdown.open > a::after {
    transform: rotate(180deg);
}

.sidebar .submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    background: rgba(26, 26, 46, 0.05);
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar .menu-item.open .submenu {
    max-height: 500px;
}

.sidebar .submenu li {
    margin: 0;
}

.sidebar .submenu li a {
    padding-left: 50px;
    font-size: 0.9rem;
    border-left: none;
}

.sidebar .submenu li a:hover {
    background: #1a1a2e;
    color: #ffffff;
}

.sidebar .submenu li a.active {
    background: #1a1a2e;
    color: #ffffff;
    border-left: 3px solid #1a1a2e;
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
    overflow: hidden;
    position: relative;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-avatar .avatar-text {
    position: absolute;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
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
            <span>IT Inventory</span>
        </div>
        <ul>
            <li><a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a></li>
            <li class="menu-item has-dropdown">
                <a href="#" class="dropdown-toggle"><i class="fa fa-boxes"></i> Inventory</a>
                <ul class="submenu">
                    <li><a href="LAPTOPpage.php"><i class="fa fa-laptop"></i> Laptop</a></li>
                    <li><a href="AVpage.php"><i class="fa fa-tv"></i> AV</a></li>
                    <li><a href="NETpage.php"><i class="fa fa-network-wired"></i> Network</a></li>
                </ul>
            </li>
            <li class="menu-item has-dropdown">
                <a href="#" class="dropdown-toggle"><i class="fa fa-handshake"></i> Handover</a>
                <ul class="submenu">
                    <li><a href="HANDform.php"><i class="fa fa-file-signature"></i> Form</a></li>
                    <li><a href="HANDreturn.php"><i class="fa fa-undo"></i> Return</a></li>
                </ul>
            </li>
            <li><a href="#"><i class="fa fa-recycle"></i> Disposal</a></li>
            <li><a href="History.php"><i class="fa fa-clock-rotate-left"></i> History</a></li>
            <li><a href="UserManual.php"><i class="fa fa-book"></i> User Manual</a></li>
            <li><a href="Profile.php"><i class="fa fa-user"></i> Profile</a></li>
            <li><a href="../auth/logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <div class="sidebar-status">
            <h4>System Status</h4>
            <p><span class="status-indicator"></span> Online</p>
            <p><i class="fa fa-code-branch"></i> Version 1.0.0</p>
        </div>
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
                            <?php if (!empty($profile_picture) && file_exists(__DIR__ . '/../' . $profile_picture)): ?>
                                <img src="../<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <div class="avatar-text"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
                            <?php endif; ?>
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

// Dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const menuItem = this.parentElement;
            const isOpen = menuItem.classList.contains('open');
            
            // Close all other dropdowns
            document.querySelectorAll('.menu-item.open').forEach(item => {
                if (item !== menuItem) {
                    item.classList.remove('open');
                }
            });
            
            // Toggle current dropdown
            if (isOpen) {
                menuItem.classList.remove('open');
            } else {
                menuItem.classList.add('open');
            }
        });
    });
    
    // Set active menu item based on current page
    const currentPath = window.location.pathname;
    const menuLinks = document.querySelectorAll('.sidebar ul li a:not(.dropdown-toggle)');
    
    menuLinks.forEach(link => {
        if (link.getAttribute('href') && link.getAttribute('href') !== '#' && currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('active');
            // If it's in a submenu, open the parent dropdown
            const submenuItem = link.closest('.submenu');
            if (submenuItem) {
                const parentMenuItem = submenuItem.parentElement;
                parentMenuItem.classList.add('open');
            }
        }
    });
});
</script>

<!-- FontAwesome CDN for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

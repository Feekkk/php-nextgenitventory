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
    top: 84px;
    left: 50%;
    transform: translate(-50%, -130%);
    width: min(1040px, 92vw);
    max-height: 78vh;
    background: rgba(255, 255, 255, 0.9);
    color: #1f2937;
    z-index: 1100;
    transition: transform 0.35s ease, opacity 0.35s ease;
    box-shadow:
        0 18px 60px rgba(15, 23, 42, 0.12),
        0 0 0 1px rgba(226, 232, 240, 0.9),
        0 14px 30px rgba(148, 163, 184, 0.22);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 20px;
    backdrop-filter: blur(14px);
    opacity: 0;
    pointer-events: none;
}

.sidebar::before {
    content: '';
    position: absolute;
    inset: 1px;
    border-radius: 18px;
    background: radial-gradient(circle at 20% 20%, rgba(17, 24, 39, 0.03), transparent 35%),
                radial-gradient(circle at 80% 10%, rgba(59, 130, 246, 0.06), transparent 30%),
                rgba(255, 255, 255, 0.6);
    pointer-events: none;
}

.sidebar::-webkit-scrollbar {
    display: none;
}

.sidebar.open {
    transform: translate(-50%, 0);
    opacity: 1;
    pointer-events: all;
}

.sidebar .sidebar-header {
    padding: 18px 20px;
    background: linear-gradient(120deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.9));
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    z-index: 1;
}

.sidebar .sidebar-header img {
    height: 38px;
    filter: drop-shadow(0 6px 12px rgba(0, 0, 0, 0.18));
}

.sidebar .sidebar-header span {
    font-size: 1.1rem;
    font-weight: 600;
    color: #0f172a;
    letter-spacing: 0.3px;
}

.close-sidebar-btn {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(148, 163, 184, 0.6);
    color: #0f172a;
    font-size: 1.2rem;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    backdrop-filter: blur(10px);
    z-index: 2;
}

.close-sidebar-btn:hover {
    background: #0f172a;
    border-color: #0f172a;
    color: #fff;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.18);
}

.sidebar-status {
    margin: 0 16px 16px;
    padding: 14px 16px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(244, 246, 249, 0.9), rgba(255, 255, 255, 0.9));
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
    color: #0f172a;
    position: relative;
    z-index: 1;
}

.sidebar-status h4 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
}

.sidebar-status p {
    margin: 6px 0 0 0;
    font-size: 0.86rem;
    color: #334155;
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-indicator {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 10px rgba(34, 197, 94, 0.5);
}

.sidebar ul {
    list-style: none;
    padding: 14px 16px 22px;
    margin: 0;
    flex: 1;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    position: relative;
    z-index: 1;
}

.sidebar ul li {
    margin: 0;
}

.sidebar ul li a {
    display: flex;
    align-items: center;
    padding: 14px 16px;
    color: #111827;
    text-decoration: none;
    font-size: 0.96rem;
    font-weight: 600;
    transition: all 0.2s ease;
    gap: 14px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.sidebar ul li a i {
    font-size: 1.1rem;
    width: 22px;
    text-align: center;
    color: #6366f1;
    transition: color 0.2s ease, transform 0.2s ease;
}

.sidebar ul li a:hover {
    background: linear-gradient(120deg, #ffffff, #f8fafc);
    border-color: #cbd5e1;
    box-shadow: 0 10px 30px rgba(148, 163, 184, 0.24);
    transform: translateY(-1px);
}

.sidebar ul li a:hover i {
    color: #0f172a;
    transform: translateY(-1px);
}

.sidebar ul li a.active {
    background: linear-gradient(120deg, #eef2ff, #e0f2fe);
    color: #0f172a;
    border-color: #a5b4fc;
    box-shadow: 0 12px 30px rgba(99, 102, 241, 0.18);
}

.sidebar ul li a.active i {
    color: #4338ca;
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
    transition: transform 0.3s ease, color 0.2s ease;
    color: rgba(15, 23, 42, 0.6);
}

.sidebar .menu-item.has-dropdown.open > a::after {
    transform: rotate(180deg);
    color: #111827;
}

.sidebar .submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.35s ease;
    background: rgba(248, 250, 252, 0.9);
    list-style: none;
    padding: 0 10px;
    margin: 8px 0 0;
    border-radius: 12px;
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.sidebar .menu-item.open .submenu {
    max-height: 500px;
}

.sidebar .submenu li {
    margin: 0;
}

.sidebar .submenu li a {
    padding: 12px 14px 12px 44px;
    font-size: 0.9rem;
    border: none;
    background: transparent;
    color: #1f2937;
}

.sidebar .submenu li a:hover {
    background: rgba(226, 232, 240, 0.7);
    color: #0f172a;
}

.sidebar .submenu li a.active {
    background: rgba(226, 232, 240, 0.9);
    color: #0f172a;
    border: 1px solid rgba(165, 180, 252, 0.8);
}

/* Header styles */
.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(255, 255, 255, 0.9);
    color: var(--text-dark);
    height: 70px;
    padding: 0 30px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1050;
    font-family: 'Inter', sans-serif;
    box-shadow: 0 10px 35px rgba(15, 23, 42, 0.06);
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
    backdrop-filter: blur(10px);
}

.menu-btn {
    background: linear-gradient(120deg, #ffffff, #f8fafc);
    border: 1px solid rgba(148, 163, 184, 0.6);
    color: #0f172a;
    font-size: 1.2rem;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    margin-right: 15px;
    box-shadow: 0 10px 25px rgba(148, 163, 184, 0.25);
}

.menu-btn:hover {
    background: linear-gradient(120deg, #e2e8f0, #ffffff);
    border-color: #94a3b8;
    color: #0f172a;
    box-shadow: 0 12px 30px rgba(148, 163, 184, 0.35);
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
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.user-menu:hover {
    background: #e2e8f0;
    border-color: #cbd5e1;
    box-shadow: 0 10px 25px rgba(148, 163, 184, 0.25);
}

.user-menu:hover .user-name {
    color: #0f172a;
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
    background: rgba(15, 23, 42, 0.35);
    backdrop-filter: blur(12px);
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
@media (max-width: 992px) {
    .sidebar {
        width: 260px;
        left: -260px;
        top: 0;
        transform: none;
        max-height: 100vh;
        border-radius: 0 16px 16px 0;
        opacity: 1;
        pointer-events: all;
        background: #ffffff;
        color: var(--text-dark);
        box-shadow: 2px 0 20px rgba(0, 0, 0, 0.08);
    }

    .sidebar::before {
        display: none;
    }

    .sidebar.open {
        left: 0;
    }

    .sidebar ul {
        grid-template-columns: 1fr;
    }

    .sidebar ul li a {
        background: transparent;
        border: none;
        color: var(--text-muted);
        box-shadow: none;
    }

    .sidebar ul li a:hover,
    .sidebar ul li a.active {
        background: #1a1a2e;
        color: #ffffff;
        border-left: 3px solid #1a1a2e;
        border-radius: 0;
    }

    .sidebar .submenu {
        background: rgba(26, 26, 46, 0.05);
        border: none;
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
            <li><a href="../pages/dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a></li>
            <li class="menu-item has-dropdown">
                <a href="#" class="dropdown-toggle"><i class="fa fa-boxes"></i> Inventory</a>
                <ul class="submenu">
                    <li><a href="../pages/LAPTOPpage.php"><i class="fa fa-laptop"></i> Laptop</a></li>
                    <li><a href="../pages/AVpage.php"><i class="fa fa-tv"></i> AV</a></li>
                    <li><a href="../pages/NETpage.php"><i class="fa fa-network-wired"></i> Network</a></li>
                </ul>
            </li>
            <li><a href="#"><i class="fa fa-recycle"></i> Disposal</a></li>
            <li><a href="../pages/History.php"><i class="fa fa-clock-rotate-left"></i> History</a></li>
            <li><a href="../pages/UserManual.php"><i class="fa fa-book"></i> User Manual</a></li>
            <li><a href="../pages/Profile.php"><i class="fa fa-user"></i> Profile</a></li>
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

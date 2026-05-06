<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$current_page = basename($_SERVER['PHP_SELF']);
if (isLoggedIn() && mustChangePassword() && $current_page !== 'change_password.php') {
    redirect('/leave_management_system/auth/change_password.php');
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Leave Management System</title>
    <link rel="stylesheet" href="/leave_management_system/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <div class="header-brand">
                <?php if (isLoggedIn()): ?>
                <button type="button" class="mobile-menu-toggle" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false" onclick="toggleSidebar(true)">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <?php endif; ?>
                <div class="logo">
                    <img src="https://lendi.edu.in/assets/img/lendi-full-logo.png" alt="Lendi Institute of Engineering & Technology" class="site-logo">
                </div>
                <div class="header-accreditation" aria-label="Accreditation badge">
                    <img src="https://lendi.edu.in/assets/img/icons/accreditation.png" alt="Accreditation" class="accreditation-icon">
                </div>
            </div>
            <?php if (isLoggedIn()): ?>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <?php if (!empty($_SESSION['identifier_no'])): ?>
                <span class="user-meta"><?php echo htmlspecialchars($_SESSION['identifier_no']); ?></span>
                <?php endif; ?>
                <?php if (!empty($_SESSION['department'])): ?>
                <span class="user-meta"><?php echo htmlspecialchars(getDepartmentLabel($_SESSION['department'])); ?></span>
                <?php endif; ?>
                <span class="role-badge"><?php echo ucfirst($_SESSION['role']); ?></span>
                <a href="/leave_management_system/auth/logout.php" class="btn btn-logout">Logout</a>
            </div>
            <?php endif; ?>
        </div>
    </header>
    
    <?php if ($flash): ?>
    <div class="flash-message flash-<?php echo $flash['type']; ?>">
        <?php echo htmlspecialchars($flash['message']); ?>
        <span class="flash-close" onclick="this.parentElement.remove()">&times;</span>
    </div>
    <?php endif; ?>

    <?php if (isLoggedIn()): ?>
    <div class="sidebar-overlay" onclick="toggleSidebar(false)" aria-hidden="true"></div>
    <script>
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');

    function toggleSidebar(forceOpen) {
        const body = document.body;
        const isOpen = body.classList.contains('sidebar-open');
        const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !isOpen;

        if (shouldOpen) {
            body.classList.add('sidebar-open');
        } else {
            body.classList.remove('sidebar-open');
        }

        if (mobileMenuToggle) {
            mobileMenuToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        }
    }

    document.addEventListener('click', function (event) {
        if (window.innerWidth > 900) {
            return;
        }

        const sidebar = document.querySelector('.sidebar');
        const toggle = document.querySelector('.mobile-menu-toggle');
        if (!sidebar || !toggle) {
            return;
        }

        if (document.body.classList.contains('sidebar-open') && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
            toggleSidebar(false);
        }
    });

    document.addEventListener('click', function (event) {
        if (window.innerWidth > 900) {
            return;
        }

        if (event.target.closest('.sidebar .nav-link')) {
            toggleSidebar(false);
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) {
            document.body.classList.remove('sidebar-open');
            if (mobileMenuToggle) {
                mobileMenuToggle.setAttribute('aria-expanded', 'false');
            }
        }
    });
    </script>
    <?php endif; ?>

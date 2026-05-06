<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
?>
<aside class="sidebar">
    <div class="sidebar-mobile-header">
        <div>
            <div class="sidebar-title">Navigation</div>
            <div class="sidebar-subtitle"><?php echo htmlspecialchars(ucfirst($role)); ?> panel</div>
        </div>
        <button type="button" class="sidebar-close" aria-label="Close menu" onclick="toggleSidebar(false)">&times;</button>
    </div>
    <nav class="sidebar-nav">
        <?php if ($role === 'student'): ?>
        <a href="/leave_management_system/student/dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            Dashboard
        </a>
        <a href="/leave_management_system/student/apply_leave.php" class="nav-link <?php echo $current_page === 'apply_leave.php' ? 'active' : ''; ?>">
            Apply Leave
        </a>
        <a href="/leave_management_system/student/view_leaves.php" class="nav-link <?php echo $current_page === 'view_leaves.php' ? 'active' : ''; ?>">
            My Leaves
        </a>
        <a href="/leave_management_system/student/upload_proof.php" class="nav-link <?php echo $current_page === 'upload_proof.php' ? 'active' : ''; ?>">
            Upload Proof
        </a>
        
        <?php elseif ($role === 'faculty'): ?>
        <a href="/leave_management_system/faculty/dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            Dashboard
        </a>
        <a href="/leave_management_system/faculty/apply_leave.php" class="nav-link <?php echo $current_page === 'apply_leave.php' ? 'active' : ''; ?>">
            Apply Leave
        </a>
        <a href="/leave_management_system/faculty/view_leaves.php" class="nav-link <?php echo $current_page === 'view_leaves.php' ? 'active' : ''; ?>">
            My Leaves
        </a>
        <a href="/leave_management_system/faculty/upload_proof.php" class="nav-link <?php echo $current_page === 'upload_proof.php' ? 'active' : ''; ?>">
            Upload Proof
        </a>
        
        <?php elseif ($role === 'hod'): ?>
        <a href="/leave_management_system/hod/dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            Dashboard
        </a>
        <a href="/leave_management_system/hod/apply_leave.php" class="nav-link <?php echo $current_page === 'apply_leave.php' ? 'active' : ''; ?>">
            Apply Leave
        </a>
        <a href="/leave_management_system/hod/view_leaves.php" class="nav-link <?php echo $current_page === 'view_leaves.php' ? 'active' : ''; ?>">
            My Leaves
        </a>
        <a href="/leave_management_system/hod/upload_proof.php" class="nav-link <?php echo $current_page === 'upload_proof.php' ? 'active' : ''; ?>">
            Upload Proof
        </a>
        <a href="/leave_management_system/hod/view_requests.php" class="nav-link <?php echo $current_page === 'view_requests.php' ? 'active' : ''; ?>">
            Leave Requests
        </a>
        
        <?php elseif ($role === 'admin'): ?>
        <a href="/leave_management_system/admin/dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            Dashboard
        </a>
        <a href="/leave_management_system/admin/requests.php" class="nav-link <?php echo $current_page === 'requests.php' ? 'active' : ''; ?>">
            Leave Requests
        </a>
        <a href="/leave_management_system/admin/absentees.php" class="nav-link <?php echo $current_page === 'absentees.php' ? 'active' : ''; ?>">
            Absentees Today
        </a>
        <a href="/leave_management_system/admin/dashboard.php#reports-overview" class="nav-link">
            College Overview
        </a>
        <a href="/leave_management_system/admin/dashboard.php#student-overview" class="nav-link">
            Student Overview
        </a>
        <a href="/leave_management_system/admin/dashboard.php#faculty-overview" class="nav-link">
            Faculty Overview
        </a>
        <a href="/leave_management_system/admin/dashboard.php#yearly-report" class="nav-link">
            Yearly Report
        </a>
        <a href="/leave_management_system/admin/dashboard.php#monthly-report" class="nav-link">
            Monthly Report
        </a>
        <a href="/leave_management_system/admin/dashboard.php#daily-report" class="nav-link">
            Daily Report
        </a>
        <a href="/leave_management_system/admin/dashboard.php#department-report" class="nav-link">
            Department Report
        </a>
        <a href="/leave_management_system/admin/dashboard.php#live-today" class="nav-link">
            Live Today
        </a>
        <a href="/leave_management_system/admin/dashboard.php#import-users" class="nav-link">
            Bulk User Import
        </a>
        
        <?php elseif ($role === 'principal'): ?>
        <a href="/leave_management_system/principal/dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            Dashboard
        </a>
        <a href="/leave_management_system/principal/dashboard.php#principal-overview" class="nav-link">
            Principal Dashboard
        </a>
        <a href="/leave_management_system/principal/dashboard.php#leaves-section" class="nav-link">
            Leaves
        </a>
        <a href="/leave_management_system/principal/dashboard.php#faculty-section" class="nav-link">
            Faculty
        </a>
        <a href="/leave_management_system/principal/dashboard.php#student-section" class="nav-link">
            Students
        </a>
        <a href="/leave_management_system/principal/requests.php" class="nav-link <?php echo $current_page === 'requests.php' ? 'active' : ''; ?>">
            HOD Leave Requests
        </a>
        <a href="/leave_management_system/principal/dashboard.php#admin-team-section" class="nav-link">
            Admin Team
        </a>
        <?php endif; ?>
    </nav>
</aside>

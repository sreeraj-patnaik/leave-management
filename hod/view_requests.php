<?php
$page_title = 'Leave Requests Report';
require_once __DIR__ . '/../includes/header.php';
checkRole('hod');

$hodDepartment = $_SESSION['department'] ?? '';
$departmentLabel = getDepartmentLabel($hodDepartment);
$departments = getDepartments($conn);
$allowedStatuses = ['pending', 'approved', 'rejected', 'open', 'closed'];
$allowedRoles = ['student', 'faculty', 'hod', 'admin'];

$date_filter = trim($_GET['date'] ?? $_GET['report_date'] ?? '');
if ($date_filter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_filter)) {
    $date_filter = '';
}
$month_filter = (int)($_GET['month'] ?? $_GET['report_month'] ?? 0);
if ($month_filter < 0 || $month_filter > 12) {
    $month_filter = 0;
}
$role_filter = strtolower(trim($_GET['role'] ?? $_GET['report_role'] ?? ''));
if ($role_filter !== '' && !in_array($role_filter, $allowedRoles, true)) {
    $role_filter = '';
}
$allowedLeaveTypes = getLeaveTypeCodes($role_filter === 'student' ? 'student' : null);
$leave_type_filter = strtolower(trim($_GET['leave_type'] ?? $_GET['report_leave_type'] ?? ''));
if ($leave_type_filter !== '' && !in_array($leave_type_filter, $allowedLeaveTypes, true)) {
    $leave_type_filter = '';
}
$status_filter = strtolower(trim($_GET['status'] ?? ''));
if ($status_filter !== '' && !in_array($status_filter, $allowedStatuses, true)) {
    $status_filter = '';
}
$student_year_filter = (int)($_GET['student_year'] ?? $_GET['report_student_year'] ?? 0);
if ($student_year_filter < 1 || $student_year_filter > 4) {
    $student_year_filter = 0;
}
$student_section_filter = strtoupper(trim($_GET['student_section'] ?? $_GET['report_student_section'] ?? ''));
if ($student_section_filter === 'ALL') {
    $student_section_filter = '';
}

$requests = getLeaveRequestsByFilters($conn, [
    'date' => $month_filter > 0 ? '' : $date_filter,
    'month' => $month_filter,
    'department' => $hodDepartment,
    'role' => $role_filter,
    'leave_type' => $leave_type_filter,
    'student_year' => $student_year_filter,
    'student_section' => $student_section_filter,
]);

if ($status_filter !== '') {
    $requests = array_values(array_filter($requests, static function (array $request) use ($status_filter) {
        return strtolower(trim((string)($request['status'] ?? ''))) === $status_filter;
    }));
}

$currentFilters = array_filter([
    'department' => $hodDepartment,
    'date' => $date_filter,
    'month' => $month_filter,
    'role' => $role_filter,
    'leave_type' => $leave_type_filter,
    'status' => $status_filter,
    'student_year' => $student_year_filter,
    'student_section' => $student_section_filter,
], static function ($value) {
    return $value !== '' && $value !== 0;
});
$currentUrl = '/leave_management_system/hod/view_requests.php' . (!empty($currentFilters) ? '?' . http_build_query($currentFilters) : '');
?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="content-header">
            <h2>Leave Requests Report</h2>
            <p class="page-subtitle"><?php echo htmlspecialchars($departmentLabel); ?> only.</p>
        </div>

        <div class="card">
            <form method="GET" class="filter-form report-filter-form">
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
                <div class="form-group">
                    <label for="month">Month</label>
                    <select id="month" name="month" class="form-control">
                        <option value="0">All Months</option>
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                        <option value="<?php echo $month; ?>" <?php echo $month_filter === $month ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(date('F', mktime(0, 0, 0, $month, 1))); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" class="form-control">
                        <option value="">All</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="faculty" <?php echo $role_filter === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                        <option value="hod" <?php echo $role_filter === 'hod' ? 'selected' : ''; ?>>HOD</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="leave_type">Leave Type</label>
                    <select id="leave_type" name="leave_type" class="form-control">
                        <option value="">All Types</option>
                        <?php foreach ($allowedLeaveTypes as $leaveTypeCode): ?>
                        <option value="<?php echo htmlspecialchars($leaveTypeCode); ?>" <?php echo $leave_type_filter === $leaveTypeCode ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(getLeaveTypeLabel($leaveTypeCode)); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">All</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="student_year">Student Year</label>
                    <select id="student_year" name="student_year" class="form-control">
                        <option value="0">All Years</option>
                        <?php for ($year = 1; $year <= 4; $year++): ?>
                        <option value="<?php echo $year; ?>" <?php echo $student_year_filter === $year ? 'selected' : ''; ?>>Year <?php echo $year; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="student_section">Student Section</label>
                    <input type="text" id="student_section" name="student_section" class="form-control" value="<?php echo htmlspecialchars($student_section_filter); ?>" placeholder="A, B, C...">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a class="btn btn-secondary" href="/leave_management_system/hod/view_requests.php">Reset</a>
                </div>
            </form>

            <div class="filter-chip-row">
                <span class="filter-chip">Rows: <?php echo count($requests); ?></span>
                <span class="filter-chip">Date: <?php echo $date_filter !== '' ? htmlspecialchars(formatDate($date_filter)) : 'All Dates'; ?></span>
                <span class="filter-chip">Month: <?php echo $month_filter > 0 ? htmlspecialchars(date('F', mktime(0, 0, 0, $month_filter, 1))) : 'All'; ?></span>
                <span class="filter-chip">Department: <?php echo htmlspecialchars($departmentLabel); ?></span>
            </div>

            <?php if (!empty($requests)): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Requested At</th>
                            <th>Applicant</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Status</th>
                            <th>Proof</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('d M Y h:i A', strtotime($request['created_at']))); ?></td>
                            <td>
                                <a class="proof-link" href="<?php echo htmlspecialchars(buildUserHistoryUrl($request['user_id'], [], $currentUrl)); ?>">
                                    <?php echo htmlspecialchars($request['name']); ?>
                                </a>
                                <br><small><?php echo htmlspecialchars(getUserMetaText($request)); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars(getRoleLabel($request['role'])); ?></td>
                            <td><?php echo htmlspecialchars(getDepartmentLabel($request['department'])); ?></td>
                            <td><?php echo htmlspecialchars(getLeaveTypeLabel($request['leave_type'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars(formatDate($request['from_date'])); ?></td>
                            <td><?php echo $request['to_date'] ? htmlspecialchars(formatDate($request['to_date'])) : '<span class="badge badge-info">Open</span>'; ?></td>
                            <td><span class="badge <?php echo getStatusBadgeClass($request['status']); ?>"><?php echo htmlspecialchars(ucfirst($request['status'])); ?></span></td>
                            <td>
                                <?php if (!empty($request['proof'])): ?>
                                <a href="/leave_management_system/uploads/<?php echo rawurlencode($request['proof']); ?>" target="_blank" rel="noopener" class="proof-link">View</a>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="<?php echo htmlspecialchars(buildLeaveReviewUrl($request, $currentUrl)); ?>" class="btn btn-primary btn-sm"><?php echo $request['status'] === 'pending' ? 'Review' : 'View'; ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state"><p>No leave requests found.</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>

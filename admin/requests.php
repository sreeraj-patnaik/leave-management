<?php
require_once __DIR__ . '/../includes/header.php';
checkRole('admin');

$page_title = 'Leave Requests';
$departments = getDepartments($conn);
$departmentCodes = getDepartmentCodes();

$status_filter = strtolower(trim($_GET['status'] ?? ''));
$role_filter = strtolower(trim($_GET['role'] ?? ''));
$department_filter = strtolower(trim($_GET['department'] ?? ''));
$date_filter = trim($_GET['date'] ?? '');

$allowedStatuses = ['pending', 'approved', 'rejected', 'open', 'closed'];
$allowedRoles = ['student', 'faculty', 'hod', 'principal', 'admin'];

if ($status_filter !== '' && !in_array($status_filter, $allowedStatuses, true)) {
    $status_filter = '';
}

if ($role_filter !== '' && !in_array($role_filter, $allowedRoles, true)) {
    $role_filter = '';
}

if ($department_filter !== '' && !in_array($department_filter, $departmentCodes, true)) {
    $department_filter = '';
}

if ($date_filter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_filter)) {
    $date_filter = '';
}

$query = "SELECT lr.*, u.name, u.role, u.department, u.regd_no, u.emp_no, u.designation, u.student_year, u.student_section, u.admin_team,
                 CASE
                     WHEN u.role = 'faculty' THEN (
                         SELECT COUNT(*)
                         FROM leave_requests cl
                         WHERE cl.user_id = u.id
                           AND cl.leave_type = 'casual'
                           AND cl.status = 'approved'
                     )
                     ELSE NULL
                 END AS casual_used
          FROM leave_requests lr
          JOIN users u ON lr.user_id = u.id
          WHERE 1=1";
$params = [];
$types = '';

if ($status_filter !== '') {
    $query .= " AND lr.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($role_filter !== '') {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if ($department_filter !== '') {
    $query .= " AND u.department = ?";
    $params[] = $department_filter;
    $types .= 's';
}

if ($date_filter !== '') {
    $query .= " AND DATE(lr.created_at) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

$query .= " ORDER BY lr.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$requests = mysqli_stmt_get_result($stmt);

$currentFilters = [
    'status' => $status_filter,
    'role' => $role_filter,
    'department' => $department_filter,
    'date' => $date_filter,
];
foreach ($currentFilters as $key => $value) {
    if ($value === '') {
        unset($currentFilters[$key]);
    }
}
$currentUrl = '/leave_management_system/admin/requests.php' . (!empty($currentFilters) ? '?' . http_build_query($currentFilters) : '');
?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h2>Leave Requests</h2>
            <p class="page-subtitle">Review college-wide requests or filter them by status, role, department, and date.</p>
        </div>

        <div class="card">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">All</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open (Medical)</option>
                        <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" class="form-control">
                        <option value="">All</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="faculty" <?php echo $role_filter === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                        <option value="hod" <?php echo $role_filter === 'hod' ? 'selected' : ''; ?>>HOD</option>
                        <option value="principal" <?php echo $role_filter === 'principal' ? 'selected' : ''; ?>>Principal</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="department">Department</label>
                    <select id="department" name="department" class="form-control">
                        <option value="">All</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['code']); ?>" <?php echo $department_filter === $dept['code'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date">Requested On</label>
                    <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="/leave_management_system/admin/requests.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header section-header">
                <h3>Request List</h3>
            </div>

            <?php if (mysqli_num_rows($requests) > 0): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Requested At</th>
                            <th>Applicant</th>
                            <th>Role</th>
                            <th>CL Taken / 15</th>
                            <th>Department</th>
                            <th>Identifier</th>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Status</th>
                            <th>Proof</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = mysqli_fetch_assoc($requests)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('d M Y h:i A', strtotime($request['created_at']))); ?></td>
                            <td>
                                <a class="proof-link" href="<?php echo htmlspecialchars(buildUserHistoryUrl($request['user_id'], [], $currentUrl)); ?>">
                                    <?php echo htmlspecialchars($request['name']); ?>
                                </a>
                                <br><small><?php echo htmlspecialchars(getUserMetaText($request)); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars(getRoleLabel($request['role'])); ?></td>
                            <td>
                                <?php echo $request['role'] === 'faculty' ? htmlspecialchars(getFacultyCasualLeaveDisplay($request)) : '-'; ?>
                            </td>
                            <td><?php echo htmlspecialchars(getDepartmentLabel($request['department'])); ?></td>
                            <td><?php echo htmlspecialchars(getIdentifierValue($request)); ?></td>
                            <td>
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $request['leave_type']))); ?>
                                <?php if (!empty($request['is_medical'])): ?>
                                <br><span class="badge badge-info">Medical</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(formatDate($request['from_date'])); ?></td>
                            <td><?php echo $request['to_date'] ? htmlspecialchars(formatDate($request['to_date'])) : '<span class="badge badge-info">Open</span>'; ?></td>
                            <td>
                                <span class="badge <?php echo getStatusBadgeClass($request['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($request['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($request['proof'])): ?>
                                <a href="/leave_management_system/uploads/<?php echo rawurlencode($request['proof']); ?>" target="_blank" rel="noopener" class="proof-link">View</a>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="<?php echo htmlspecialchars(buildLeaveReviewUrl($request, $currentUrl)); ?>" class="btn btn-primary btn-sm">
                                    <?php echo $request['status'] === 'pending' ? 'Review' : 'View'; ?>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>No leave requests found.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>

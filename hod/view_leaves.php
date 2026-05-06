<?php
$page_title = 'My Leaves';
require_once __DIR__ . '/../includes/header.php';
checkRole('hod');

$user_id = $_SESSION['user_id'];
$user = getUserById($conn, $user_id);
$status_filter = $_GET['status'] ?? '';

$query = "SELECT * FROM leave_requests WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$query .= " ORDER BY created_at DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$leaves = mysqli_stmt_get_result($stmt);

$casualUsed = (int)getUserLeaveSummary($conn, $user_id)['casual_used'];
$casualDisplay = getFacultyCasualLeaveDisplay(['casual_used' => $casualUsed]);
?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="content-header">
            <h2>My Leave Requests</h2>
            <p class="page-subtitle">These requests are reviewed by the Principal.</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Profile</h3>
            </div>
            <div class="detail-row">
                <div class="detail-label">Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($user['name']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><?php echo htmlspecialchars(getIdentifierLabel('hod')); ?>:</div>
                <div class="detail-value"><?php echo htmlspecialchars(getIdentifierValue($user)); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Department:</div>
                <div class="detail-value"><?php echo htmlspecialchars(getDepartmentLabel($user['department'])); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Designation:</div>
                <div class="detail-value"><?php echo htmlspecialchars($user['designation'] ?: 'HOD'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">CL Taken / 15</div>
                <div class="detail-value"><?php echo htmlspecialchars($casualDisplay); ?></div>
            </div>
        </div>
        
        <div class="card">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="status">Filter by Status</label>
                    <select id="status" name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open (Medical)</option>
                        <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed (Medical)</option>
                    </select>
                </div>
            </form>
            
            <?php if (mysqli_num_rows($leaves) > 0): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Proof</th>
                            <th>Principal Remarks</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($leave = mysqli_fetch_assoc($leaves)): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars(getLeaveTypeLabel($leave['leave_type'])); ?>
                                <?php if (!empty($leave['is_medical'])): ?>
                                <br><span class="badge badge-info">Medical</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDate($leave['from_date']); ?></td>
                            <td><?php echo $leave['to_date'] ? formatDate($leave['to_date']) : '-'; ?></td>
                            <td><?php echo htmlspecialchars(strlen($leave['reason']) > 50 ? substr($leave['reason'], 0, 50) . '...' : $leave['reason']); ?></td>
                            <td>
                                <span class="badge <?php echo getStatusBadgeClass($leave['status']); ?>">
                                    <?php echo ucfirst($leave['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($leave['proof']): ?>
                                <a href="/leave_management_system/uploads/<?php echo htmlspecialchars($leave['proof']); ?>" 
                                   target="_blank" class="proof-link">View</a>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($leave['hod_remarks'] ?? '-'); ?></td>
                            <td class="actions">
                                <?php if ($leave['status'] === 'pending'): ?>
                                <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars(buildLeaveReviewUrlForRole($leave['id'], 'hod', '/leave_management_system/hod/view_leaves.php')); ?>">View</a>
                                <?php else: ?>
                                <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars(buildUserHistoryUrl($user_id, ['leave_type' => $leave['leave_type']], '/leave_management_system/hod/view_leaves.php')); ?>">History</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>No leave requests found.</p>
                <a href="apply_leave.php" class="btn btn-primary">Apply for Leave</a>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>

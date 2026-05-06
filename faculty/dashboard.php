<?php
$page_title = 'Faculty Dashboard';
require_once __DIR__ . '/../includes/header.php';
checkRole('faculty');

$user_id = $_SESSION['user_id'];
$user = getUserById($conn, $user_id);
$analytics = getUserLeaveAnalytics($conn, $user_id);
$summary = $analytics['summary'];
$casualUsed = (int)($summary['casual_used'] ?? 0);
$casualDisplay = getFacultyCasualLeaveDisplay($summary);
$casualBalance = max(0, 15 - $casualUsed);
$historyUrl = buildUserHistoryUrl($user_id, [], '/leave_management_system/faculty/dashboard.php');

$leaveRowsStmt = mysqli_prepare(
    $conn,
    "SELECT leave_type, status
     FROM leave_requests
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC"
);
$leaveRows = [];
if ($leaveRowsStmt) {
    mysqli_stmt_bind_param($leaveRowsStmt, "i", $user_id);
    mysqli_stmt_execute($leaveRowsStmt);
    $leaveRowsResult = mysqli_stmt_get_result($leaveRowsStmt);
    $leaveRows = $leaveRowsResult ? mysqli_fetch_all($leaveRowsResult, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($leaveRowsStmt);
}
$leaveMatrix = buildLeaveRequestMatrix($leaveRows);
$leaveMatrixLink = static function (array $params = []) use ($user_id) {
    return buildUserHistoryUrl($user_id, $params, '/leave_management_system/faculty/dashboard.php');
};

// Get recent leave requests
$stmt = mysqli_prepare($conn, 
    "SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$recent_leaves = mysqli_stmt_get_result($stmt);
?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="content-header">
            <h2>Faculty Dashboard</h2>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Profile</h3>
                <div class="section-actions">
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($historyUrl); ?>">View Full History</a>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($user['name']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><?php echo htmlspecialchars(getIdentifierLabel('faculty')); ?>:</div>
                <div class="detail-value"><?php echo htmlspecialchars(getIdentifierValue($user)); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Department:</div>
                <div class="detail-value"><?php echo htmlspecialchars(getDepartmentLabel($user['department'])); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Designation:</div>
                <div class="detail-value"><?php echo htmlspecialchars($user['designation'] ?: 'Faculty'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">CL Taken / 15</div>
                <div class="detail-value"><?php echo htmlspecialchars($casualDisplay); ?>, <?php echo $casualBalance; ?> left</div>
            </div>
        </div>

        <div class="card metric-matrix-card">
            <div class="card-header section-header">
                <div>
                    <h3>Leave Distribution Matrix</h3>
                    <div class="section-note">Click any count to jump into the matching slice of your history.</div>
                </div>
                <div class="section-actions">
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($historyUrl); ?>">Open History</a>
                </div>
            </div>
            <div class="table-container">
                <table class="data-table matrix-table">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Total</th>
                            <th>Pending</th>
                            <th>Approved</th>
                            <th>Rejected</th>
                            <th>Open</th>
                            <th>Closed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-total-row">
                            <td>All Leaves</td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($leaveMatrixLink()); ?>"><?php echo (int)$leaveMatrix['total']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($leaveMatrixLink(['status' => 'pending'])); ?>"><?php echo (int)$leaveMatrix['status_totals']['pending']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($leaveMatrixLink(['status' => 'approved'])); ?>"><?php echo (int)$leaveMatrix['status_totals']['approved']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($leaveMatrixLink(['status' => 'rejected'])); ?>"><?php echo (int)$leaveMatrix['status_totals']['rejected']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($leaveMatrixLink(['status' => 'open'])); ?>"><?php echo (int)$leaveMatrix['status_totals']['open']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($leaveMatrixLink(['status' => 'closed'])); ?>"><?php echo (int)$leaveMatrix['status_totals']['closed']; ?></a></td>
                        </tr>
                        <?php foreach ($leaveMatrix['type_codes'] as $typeCode): ?>
                        <tr>
                            <td><a class="proof-link" href="<?php echo htmlspecialchars($leaveMatrixLink(['leave_type' => $typeCode])); ?>"><?php echo htmlspecialchars(getLeaveTypeLabel($typeCode)); ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($leaveMatrixLink(['leave_type' => $typeCode])); ?>"><?php echo (int)$leaveMatrix['type_totals'][$typeCode]; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($leaveMatrixLink(['leave_type' => $typeCode, 'status' => 'pending'])); ?>"><?php echo (int)$leaveMatrix['matrix'][$typeCode]['pending']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($leaveMatrixLink(['leave_type' => $typeCode, 'status' => 'approved'])); ?>"><?php echo (int)$leaveMatrix['matrix'][$typeCode]['approved']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($leaveMatrixLink(['leave_type' => $typeCode, 'status' => 'rejected'])); ?>"><?php echo (int)$leaveMatrix['matrix'][$typeCode]['rejected']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($leaveMatrixLink(['leave_type' => $typeCode, 'status' => 'open'])); ?>"><?php echo (int)$leaveMatrix['matrix'][$typeCode]['open']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($leaveMatrixLink(['leave_type' => $typeCode, 'status' => 'closed'])); ?>"><?php echo (int)$leaveMatrix['matrix'][$typeCode]['closed']; ?></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="stats-grid">
            <a class="stat-card" href="<?php echo htmlspecialchars($historyUrl); ?>">
                <div class="stat-number"><?php echo (int)$summary['total_requests']; ?></div>
                <div class="stat-label">Total Leaves</div>
            </a>
            <a class="stat-card pending" href="<?php echo htmlspecialchars(buildUserHistoryUrl($user_id, ['status' => 'pending'], '/leave_management_system/faculty/dashboard.php')); ?>">
                <div class="stat-number"><?php echo (int)$summary['pending_requests']; ?></div>
                <div class="stat-label">Pending</div>
            </a>
            <a class="stat-card approved" href="<?php echo htmlspecialchars(buildUserHistoryUrl($user_id, ['status' => 'approved'], '/leave_management_system/faculty/dashboard.php')); ?>">
                <div class="stat-number"><?php echo (int)$summary['approved_requests']; ?></div>
                <div class="stat-label">Approved</div>
            </a>
            <a class="stat-card rejected" href="<?php echo htmlspecialchars(buildUserHistoryUrl($user_id, ['status' => 'rejected'], '/leave_management_system/faculty/dashboard.php')); ?>">
                <div class="stat-number"><?php echo (int)$summary['rejected_requests']; ?></div>
                <div class="stat-label">Rejected</div>
            </a>
            <a class="stat-card open" href="<?php echo htmlspecialchars(buildUserHistoryUrl($user_id, ['status' => 'open'], '/leave_management_system/faculty/dashboard.php')); ?>">
                <div class="stat-number"><?php echo (int)$summary['open_requests']; ?></div>
                <div class="stat-label">Open Medical</div>
            </a>
            <a class="stat-card" href="<?php echo htmlspecialchars(buildUserHistoryUrl($user_id, ['leave_type' => 'casual'], '/leave_management_system/faculty/dashboard.php')); ?>">
                <div class="stat-number"><?php echo $casualUsed; ?></div>
                <div class="stat-label">CL Taken</div>
            </a>
            <div class="stat-card">
                <div class="stat-number"><?php echo $casualBalance; ?></div>
                <div class="stat-label">Casual Balance</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header section-header">
                <h3>Leave Analytics</h3>
                <div class="section-actions">
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($historyUrl); ?>">Open History</a>
                </div>
            </div>
            <div class="analytics-grid">
                <div class="analytics-card">
                    <div class="card-header">
                        <h3>Leave Type Mix</h3>
                    </div>
                    <?php
                    $typeCounts = $analytics['type_counts'];
                    $totalTypeCount = 0;
                    foreach ($typeCounts as $typeRow) {
                        $totalTypeCount += (int)$typeRow['count'];
                    }
                    $chartSlices = [];
                    $cursor = 0;
                    $palette = [
                        'casual' => '#1a237e',
                        'medical' => '#2196f3',
                        'on_duty' => '#4caf50',
                        'academic' => '#ff9800',
                        'vacation' => '#f44336',
                    ];
                    foreach ($typeCounts as $typeRow) {
                        $count = (int)$typeRow['count'];
                        if ($count <= 0 || $totalTypeCount <= 0) {
                            continue;
                        }
                        $start = $cursor;
                        $cursor += ($count / $totalTypeCount) * 100;
                        $chartSlices[] = ($palette[$typeRow['type']] ?? '#9e9e9e') . ' ' . $start . '% ' . $cursor . '%';
                    }
                    ?>
                    <div class="pie-chart" style="background: conic-gradient(<?php echo htmlspecialchars(!empty($chartSlices) ? implode(', ', $chartSlices) : '#d0d5dd 0% 100%'); ?>);"></div>
                    <div class="legend-list">
                        <?php foreach ($typeCounts as $typeRow): ?>
                        <div class="legend-item">
                            <span class="legend-swatch <?php echo htmlspecialchars($typeRow['class']); ?>"></span>
                            <span><?php echo htmlspecialchars($typeRow['label']); ?></span>
                            <strong><?php echo (int)$typeRow['count']; ?></strong>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="analytics-card">
                    <div class="card-header">
                        <h3>Monthly Trend - <?php echo (int)$analytics['active_year']; ?></h3>
                    </div>
                    <div class="bar-chart">
                        <?php
                        $monthlyCounts = $analytics['monthly_counts'];
                        $maxMonthly = 0;
                        foreach ($monthlyCounts as $monthRow) {
                            $maxMonthly = max($maxMonthly, (int)$monthRow['count']);
                        }
                        ?>
                        <?php foreach ($monthlyCounts as $monthRow): ?>
                        <?php $height = $maxMonthly > 0 ? max(8, (int)round(((int)$monthRow['count'] / $maxMonthly) * 100)) : 8; ?>
                        <a class="bar-item" href="<?php echo htmlspecialchars(buildUserHistoryUrl($user_id, ['year' => $analytics['active_year'], 'month' => $monthRow['month']], '/leave_management_system/faculty/dashboard.php')); ?>">
                            <span class="bar-value" style="height: <?php echo $height; ?>%;"></span>
                            <span class="bar-label"><?php echo htmlspecialchars($monthRow['label']); ?></span>
                            <span class="bar-count"><?php echo (int)$monthRow['count']; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Recent Leave Requests</h3>
            </div>
            
            <?php if (mysqli_num_rows($recent_leaves) > 0): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Status</th>
                            <th>Applied On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($leave = mysqli_fetch_assoc($recent_leaves)): ?>
                        <tr>
                            <td>
                                <a class="proof-link" href="<?php echo htmlspecialchars(buildUserHistoryUrl($user_id, ['leave_type' => $leave['leave_type']], '/leave_management_system/faculty/dashboard.php')); ?>">
                                    <?php echo htmlspecialchars(getLeaveTypeLabel($leave['leave_type'])); ?>
                                </a>
                            </td>
                            <td><?php echo formatDate($leave['from_date']); ?></td>
                            <td><?php echo $leave['to_date'] ? formatDate($leave['to_date']) : '-'; ?></td>
                            <td>
                                <span class="badge <?php echo getStatusBadgeClass($leave['status']); ?>">
                                    <?php echo ucfirst($leave['status']); ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($leave['created_at']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>No leave requests yet.</p>
                <a href="apply_leave.php" class="btn btn-primary">Apply for Leave</a>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>

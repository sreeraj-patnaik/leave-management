<?php
require_once __DIR__ . '/../includes/header.php';

$page_title = 'User Leave History';

$userId = (int)($_GET['user_id'] ?? 0);
$returnTo = trim($_GET['return_to'] ?? '');
if ($returnTo === '' || strpos($returnTo, '/leave_management_system/') !== 0) {
    $returnTo = '/leave_management_system/admin/requests.php';
}

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    setFlashMessage('error', 'User not found.');
    redirect($returnTo);
}

$currentRole = $_SESSION['role'] ?? '';
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$canViewHistory = in_array($currentRole, ['admin', 'hod', 'principal'], true) || ($currentUserId === $userId);
if (!$canViewHistory) {
    redirect('/leave_management_system/auth/login.php');
}

$analytics = getUserLeaveAnalytics($conn, $userId);
$summary = $analytics['summary'];
$showCasualUsage = in_array(strtolower($user['role'] ?? ''), ['faculty', 'hod', 'principal', 'admin'], true);
$casualUsed = (int)($summary['casual_used'] ?? 0);
$casualDisplay = getFacultyCasualLeaveDisplay($summary);
$casualBalance = max(0, 15 - $casualUsed);

$allowedStatuses = ['pending', 'approved', 'rejected', 'open', 'closed'];
$allowedTypes = getLeaveTypeCodes(strtolower(trim((string)($user['role'] ?? ''))) === 'student' ? 'student' : null);

$selectedStatus = strtolower(trim($_GET['status'] ?? ''));
if ($selectedStatus !== '' && !in_array($selectedStatus, $allowedStatuses, true)) {
    $selectedStatus = '';
}

$selectedType = strtolower(trim($_GET['leave_type'] ?? ''));
if ($selectedType !== '' && !in_array($selectedType, $allowedTypes, true)) {
    $selectedType = '';
}

$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
if ($selectedYear > 0 && $selectedYear < 2000) {
    $selectedYear = 0;
}

$selectedMonth = (int)($_GET['month'] ?? 0);
if ($selectedMonth < 0 || $selectedMonth > 12) {
    $selectedMonth = 0;
}
if ($selectedMonth > 0 && $selectedYear === 0) {
    $selectedYear = (int)($analytics['active_year'] ?? date('Y'));
}

$historyFilters = [
    'status' => $selectedStatus,
    'leave_type' => $selectedType,
    'year' => $selectedYear > 0 ? $selectedYear : null,
    'month' => $selectedMonth > 0 ? $selectedMonth : null,
];
foreach ($historyFilters as $key => $value) {
    if ($value === '' || $value === null) {
        unset($historyFilters[$key]);
    }
}

$leaveHistory = [];
$bindStatement = static function ($stmt, $types, array $params) {
    if ($stmt === false || $types === '') {
        return;
    }

    $bind = [$types];
    foreach ($params as $index => $value) {
        $bind[$index + 1] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
};

if ($selectedYear > 0 && $selectedMonth > 0) {
    $sql = "SELECT lr.*
            FROM leave_requests lr
            WHERE lr.user_id = ? AND YEAR(lr.created_at) = ? AND MONTH(lr.created_at) = ?";
    $types = 'iii';
    $params = [$userId, $selectedYear, $selectedMonth];
    if ($selectedStatus !== '') {
        $sql .= " AND lr.status = ?";
        $types .= 's';
        $params[] = $selectedStatus;
    }
    if ($selectedType !== '') {
        $sql .= " AND lr.leave_type = ?";
        $types .= 's';
        $params[] = $selectedType;
    }
    $sql .= " ORDER BY lr.created_at DESC, lr.id DESC";
    $stmt = mysqli_prepare($conn, $sql);
    $bindStatement($stmt, $types, $params);
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $leaveHistory = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
} elseif ($selectedYear > 0) {
    $sql = "SELECT lr.*
            FROM leave_requests lr
            WHERE lr.user_id = ? AND YEAR(lr.created_at) = ?";
    $types = 'ii';
    $params = [$userId, $selectedYear];
    if ($selectedStatus !== '') {
        $sql .= " AND lr.status = ?";
        $types .= 's';
        $params[] = $selectedStatus;
    }
    if ($selectedType !== '') {
        $sql .= " AND lr.leave_type = ?";
        $types .= 's';
        $params[] = $selectedType;
    }
    $sql .= " ORDER BY lr.created_at DESC, lr.id DESC";
    $stmt = mysqli_prepare($conn, $sql);
    $bindStatement($stmt, $types, $params);
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $leaveHistory = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
} elseif ($selectedStatus !== '' || $selectedType !== '') {
    $sql = "SELECT lr.*
            FROM leave_requests lr
            WHERE lr.user_id = ?";
    $types = 'i';
    $params = [$userId];

    if ($selectedType !== '') {
        $sql .= " AND lr.leave_type = ?";
        $types .= 's';
        $params[] = $selectedType;
    }
    if ($selectedStatus !== '') {
        $sql .= " AND lr.status = ?";
        $types .= 's';
        $params[] = $selectedStatus;
    }
    $sql .= " ORDER BY lr.created_at DESC, lr.id DESC";
    $stmt = mysqli_prepare($conn, $sql);
    $bindStatement($stmt, $types, $params);
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $leaveHistory = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
} else {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT lr.*
         FROM leave_requests lr
         WHERE lr.user_id = ?
         ORDER BY lr.created_at DESC, lr.id DESC"
    );
    $bindStatement($stmt, 'i', [$userId]);
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $leaveHistory = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}
?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h2><?php echo htmlspecialchars($user['name']); ?> - Leave History</h2>
            <p class="page-subtitle">
                <?php echo htmlspecialchars(getRoleLabel($user['role'])); ?>,
                <?php echo htmlspecialchars(getDepartmentLabel($user['department'])); ?>
                <?php if (!empty($user['designation'])): ?>
                | <?php echo htmlspecialchars($user['designation']); ?>
                <?php endif; ?>
                <?php if (!empty($user['student_year'])): ?>
                | Year <?php echo (int)$user['student_year']; ?>
                <?php endif; ?>
                <?php if (!empty($user['student_section'])): ?>
                | Section <?php echo htmlspecialchars(strtoupper($user['student_section'])); ?>
                <?php endif; ?>
                <?php if (!empty($user['admin_team'])): ?>
                | Admin Team
                <?php endif; ?>
                <?php if ($showCasualUsage): ?>
                | CL taken: <?php echo htmlspecialchars($casualDisplay); ?>
                | Balance: <?php echo $casualBalance; ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="card">
            <div class="stats-grid">
                <a class="stat-card" href="<?php echo htmlspecialchars(buildUserHistoryUrl($userId, [], $returnTo)); ?>">
                    <div class="stat-number"><?php echo (int)$summary['total_requests']; ?></div>
                    <div class="stat-label">Total Requests</div>
                </a>
                <a class="stat-card pending" href="<?php echo htmlspecialchars(buildUserHistoryUrl($userId, ['status' => 'pending'], $returnTo)); ?>">
                    <div class="stat-number"><?php echo (int)$summary['pending_requests']; ?></div>
                    <div class="stat-label">Pending</div>
                </a>
                <a class="stat-card approved" href="<?php echo htmlspecialchars(buildUserHistoryUrl($userId, ['status' => 'approved'], $returnTo)); ?>">
                    <div class="stat-number"><?php echo (int)$summary['approved_requests']; ?></div>
                    <div class="stat-label">Approved</div>
                </a>
                <a class="stat-card rejected" href="<?php echo htmlspecialchars(buildUserHistoryUrl($userId, ['status' => 'rejected'], $returnTo)); ?>">
                    <div class="stat-number"><?php echo (int)$summary['rejected_requests']; ?></div>
                    <div class="stat-label">Rejected</div>
                </a>
                <a class="stat-card open" href="<?php echo htmlspecialchars(buildUserHistoryUrl($userId, ['status' => 'open'], $returnTo)); ?>">
                    <div class="stat-number"><?php echo (int)$summary['open_requests']; ?></div>
                    <div class="stat-label">Open Medical</div>
                </a>
                <a class="stat-card" href="<?php echo htmlspecialchars(buildUserHistoryUrl($userId, ['status' => 'closed'], $returnTo)); ?>">
                    <div class="stat-number"><?php echo (int)$summary['closed_requests']; ?></div>
                    <div class="stat-label">Closed Medical</div>
                </a>
                <?php if ($showCasualUsage): ?>
                <a class="stat-card" href="<?php echo htmlspecialchars(buildUserHistoryUrl($userId, ['leave_type' => 'casual'], $returnTo)); ?>">
                    <div class="stat-number"><?php echo $casualUsed; ?></div>
                    <div class="stat-label">CL Taken</div>
                </a>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $casualBalance; ?></div>
                    <div class="stat-label">Casual Balance</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header section-header">
                <h3>Leave Analytics</h3>
                <div class="section-actions">
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars(buildUserHistoryUrl($userId, [], $returnTo)); ?>">Clear Filters</a>
                </div>
            </div>
            <div class="filter-chip-row">
                <span class="filter-chip">Trend Year: <?php echo (int)$analytics['active_year']; ?></span>
                <?php if ($selectedYear > 0): ?>
                <span class="filter-chip">Filter Year: <?php echo (int)$selectedYear; ?></span>
                <?php endif; ?>
                <?php if ($selectedMonth > 0): ?>
                <span class="filter-chip">Month: <?php echo htmlspecialchars(date('F', mktime(0, 0, 0, $selectedMonth, 1))); ?></span>
                <?php endif; ?>
                <?php if ($selectedStatus !== ''): ?>
                <span class="filter-chip">Status: <?php echo htmlspecialchars(ucfirst($selectedStatus)); ?></span>
                <?php endif; ?>
                <?php if ($selectedType !== ''): ?>
                <span class="filter-chip">Type: <?php echo htmlspecialchars(getLeaveTypeLabel($selectedType)); ?></span>
                <?php endif; ?>
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
                        <h3>Monthly Trend - <?php echo (int)$selectedYear; ?></h3>
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
                        <a class="bar-item" href="<?php echo htmlspecialchars(buildUserHistoryUrl($userId, ['year' => $selectedYear, 'month' => $monthRow['month']], $returnTo)); ?>">
                            <span class="bar-value" style="height: <?php echo $height; ?>%;"></span>
                            <span class="bar-label"><?php echo htmlspecialchars($monthRow['label']); ?></span>
                            <span class="bar-count"><?php echo (int)$monthRow['count']; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="yearly-summary">
                <div class="card-header">
                    <h3>Year-wise Summary</h3>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Leaves</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($analytics['yearly_counts'])): ?>
                            <?php foreach ($analytics['yearly_counts'] as $year => $count): ?>
                            <tr>
                                <td><?php echo (int)$year; ?></td>
                                <td>
                                    <a class="casual-count-link" href="<?php echo htmlspecialchars(buildUserHistoryUrl($userId, ['year' => (int)$year], $returnTo)); ?>">
                                        <?php echo (int)$count; ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="2">No leave data yet.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header section-header">
                <h3>Past Leaves</h3>
                <?php if (!empty($historyFilters)): ?>
                <div class="section-actions">
                    <?php foreach ($historyFilters as $key => $value): ?>
                    <span class="filter-chip"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $key))); ?>: <?php echo htmlspecialchars((string)$value); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($leaveHistory)): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Requested At</th>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Status</th>
                            <th>Duration</th>
                            <th>Proof</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaveHistory as $leave): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('d M Y h:i A', strtotime($leave['created_at']))); ?></td>
                            <td>
                                <?php echo htmlspecialchars(getLeaveTypeLabel($leave['leave_type'])); ?>
                                <?php if (!empty($leave['is_medical'])): ?>
                                <br><span class="badge badge-info">Medical</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(formatDate($leave['from_date'])); ?></td>
                            <td><?php echo $leave['to_date'] ? htmlspecialchars(formatDate($leave['to_date'])) : '<span class="badge badge-info">Open</span>'; ?></td>
                            <td>
                                <span class="badge <?php echo getStatusBadgeClass($leave['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($leave['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($leave['expected_duration'] ?: '-'); ?></td>
                            <td>
                                <?php if (!empty($leave['proof'])): ?>
                                <a class="proof-link" href="/leave_management_system/uploads/<?php echo rawurlencode($leave['proof']); ?>" target="_blank" rel="noopener">View</a>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="<?php echo htmlspecialchars(buildLeaveReviewUrlForRole($leave['id'], $user['role'], $returnTo)); ?>" class="btn btn-secondary btn-sm">
                                    <?php echo $leave['status'] === 'pending' ? 'Review' : 'View'; ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>No leave history found.</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="action-group">
            <a href="<?php echo htmlspecialchars($returnTo); ?>" class="btn btn-secondary">Back to Absentees</a>
        </div>
    </main>
</div>

</body>
</html>

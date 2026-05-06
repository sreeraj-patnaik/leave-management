<?php
$page_title = 'HOD Dashboard';
require_once __DIR__ . '/../includes/header.php';
checkRole('hod');

$hodDepartment = $_SESSION['department'] ?? '';
$todayDate = date('Y-m-d');
$departmentLabel = getDepartmentLabel($hodDepartment);
$presenceStats = getPresenceStats($conn, $todayDate, $hodDepartment);
$user = getUserById($conn, (int)$_SESSION['user_id']);
$analytics = getUserLeaveAnalytics($conn, (int)$_SESSION['user_id']);
$summary = $analytics['summary'];
$casualUsed = (int)($summary['casual_used'] ?? 0);
$casualDisplay = getFacultyCasualLeaveDisplay($summary);
$casualBalance = max(0, 15 - $casualUsed);

$currentUserHistoryUrl = buildUserHistoryUrl((int)$_SESSION['user_id'], [], '/leave_management_system/hod/dashboard.php');

$fetchAll = function ($sql, $types = '', $params = []) use ($conn) {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '' && !empty($params)) {
        $bind = [$types];
        foreach ($params as $index => $value) {
            $bind[$index + 1] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
};

$buildUrl = function (array $overrides = []) {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    return '/leave_management_system/hod/dashboard.php' . (!empty($params) ? '?' . http_build_query($params) : '');
};

$buildHistoryUrl = function ($userId, array $params = []) use ($buildUrl) {
    return buildUserHistoryUrl($userId, $params, $buildUrl());
};

$renderTable = static function (array $headers, array $rows, string $emptyMessage, ?array $footerRow = null) {
    ob_start();
    ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                    <th><?php echo htmlspecialchars($header); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                        <td>
                            <?php if (is_array($cell) && array_key_exists('html', $cell)): ?>
                                <?php echo $cell['html']; ?>
                            <?php else: ?>
                                <?php echo htmlspecialchars((string)$cell); ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?php echo count($headers); ?>" class="empty-state"><?php echo htmlspecialchars($emptyMessage); ?></td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($footerRow !== null): ?>
            <tfoot>
                <tr class="table-total-row">
                    <?php foreach (array_pad($footerRow, count($headers), '') as $cell): ?>
                    <td>
                        <?php if (is_array($cell) && array_key_exists('html', $cell)): ?>
                            <?php echo $cell['html']; ?>
                        <?php else: ?>
                            <?php echo htmlspecialchars((string)$cell); ?>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php
    return ob_get_clean();
};

$renderHubCard = static function (array $card) {
    ob_start();
    ?>
    <a class="hub-card <?php echo htmlspecialchars($card['class'] ?? ''); ?><?php echo !empty($card['active']) ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($card['href'] ?? '#'); ?>">
        <div class="hub-card-top">
            <span class="hub-card-kicker"><?php echo htmlspecialchars($card['kicker'] ?? ''); ?></span>
            <span class="hub-card-arrow">Open</span>
        </div>
        <div class="hub-card-count"><?php echo (int)($card['value'] ?? 0); ?></div>
        <div class="hub-card-label"><?php echo htmlspecialchars($card['label'] ?? ''); ?></div>
        <div class="hub-card-copy"><?php echo htmlspecialchars($card['copy'] ?? ''); ?></div>
    </a>
    <?php
    return ob_get_clean();
};

$selectedView = strtolower(trim($_GET['view'] ?? ''));
if (!in_array($selectedView, ['faculty', 'student'], true)) {
    $selectedView = '';
}

$facultyAbsentees = getAbsentUsersOnDate($conn, $todayDate, 'faculty', $hodDepartment);
$studentAbsentees = getAbsentUsersOnDate($conn, $todayDate, 'student', $hodDepartment);
$facultyAbsenteeCount = count($facultyAbsentees);
$studentAbsenteeCount = count($studentAbsentees);

$reportYears = [];
foreach ($fetchAll("SELECT DISTINCT YEAR(created_at) AS report_year FROM leave_requests ORDER BY report_year DESC") as $row) {
    $year = (int)($row['report_year'] ?? 0);
    if ($year > 0) {
        $reportYears[] = $year;
    }
}
if (empty($reportYears)) {
    $reportYears = [(int)date('Y')];
}

$requestReportYear = (int)($_GET['report_year'] ?? date('Y'));
if ($requestReportYear < 2000) {
    $requestReportYear = 0;
}
$requestReportMonth = (int)($_GET['report_month'] ?? 0);
if ($requestReportMonth < 0 || $requestReportMonth > 12) {
    $requestReportMonth = 0;
}
$requestReportRole = strtolower(trim($_GET['report_role'] ?? ''));
if ($requestReportRole === 'all') {
    $requestReportRole = '';
}
if (!in_array($requestReportRole, ['student', 'faculty', 'hod', 'admin', ''], true)) {
    $requestReportRole = '';
}
$requestReportLeaveType = strtolower(trim($_GET['report_leave_type'] ?? ''));
if ($requestReportLeaveType === 'all') {
    $requestReportLeaveType = '';
}
$requestReportAllowedLeaveTypes = getLeaveTypeCodes($requestReportRole === 'student' ? 'student' : null);
if (!in_array($requestReportLeaveType, array_merge($requestReportAllowedLeaveTypes, ['']), true)) {
    $requestReportLeaveType = '';
}
$requestReportDate = trim($_GET['report_date'] ?? $todayDate);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestReportDate)) {
    $requestReportDate = $todayDate;
}
$requestReportStudentYear = (int)($_GET['report_student_year'] ?? 0);
if ($requestReportStudentYear < 1 || $requestReportStudentYear > 4) {
    $requestReportStudentYear = 0;
}
$requestReportStudentSection = strtoupper(trim($_GET['report_student_section'] ?? ''));
if ($requestReportStudentSection === 'ALL') {
    $requestReportStudentSection = '';
}

$absenceDate = trim($_GET['absence_date'] ?? $todayDate);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $absenceDate)) {
    $absenceDate = $todayDate;
}
$absenceRole = strtolower(trim($_GET['absence_role'] ?? ''));
if ($absenceRole === 'all') {
    $absenceRole = '';
}
if (!in_array($absenceRole, ['student', 'faculty', 'hod', 'admin', ''], true)) {
    $absenceRole = '';
}
$absenceLeaveType = strtolower(trim($_GET['absence_leave_type'] ?? ''));
if ($absenceLeaveType === 'all') {
    $absenceLeaveType = '';
}
$absenceAllowedLeaveTypes = getLeaveTypeCodes($absenceRole === 'student' ? 'student' : null);
if (!in_array($absenceLeaveType, array_merge($absenceAllowedLeaveTypes, ['']), true)) {
    $absenceLeaveType = '';
}

$requestReportLeaveTypes = $requestReportAllowedLeaveTypes;
$absenceLeaveTypes = $absenceAllowedLeaveTypes;

$explorerMode = strtolower(trim($_GET['explorer_mode'] ?? ''));
if (!in_array($explorerMode, ['requests', 'absentees'], true)) {
    $explorerMode = ($absenceDate !== $todayDate || $absenceRole !== '' || $absenceLeaveType !== '') ? 'absentees' : 'requests';
}
if ($explorerMode !== 'absentees' && (
    $requestReportYear > 0 ||
    $requestReportMonth > 0 ||
    $requestReportRole !== '' ||
    $requestReportLeaveType !== '' ||
    $requestReportStudentYear > 0 ||
    $requestReportStudentSection !== ''
)) {
    $explorerMode = 'requests';
}

$leaveRequestRows = getLeaveRequestsByFilters($conn, [
    'year' => $requestReportYear,
    'month' => $requestReportMonth,
    'role' => $requestReportRole,
    'leave_type' => $requestReportLeaveType,
    'department' => $hodDepartment,
    'student_year' => $requestReportStudentYear,
    'student_section' => $requestReportStudentSection,
]);

$leaveRequestTableRows = [];
foreach ($leaveRequestRows as $request) {
    $leaveRequestTableRows[] = [
        [
            'html' => '<a href="' . htmlspecialchars($buildHistoryUrl($request['user_id'])) . '">' . htmlspecialchars($request['name']) . '</a>',
        ],
        getRoleLabel($request['role']),
        getDepartmentLabel($request['department']),
        getLeaveTypeLabel($request['leave_type'] ?? ''),
        formatDate($request['from_date']),
        [
            'html' => $request['to_date'] ? htmlspecialchars(formatDate($request['to_date'])) : '<span class="badge badge-info">Open</span>',
        ],
        ucfirst((string)$request['status']),
        [
            'html' => !empty($request['proof'])
                ? '<a class="proof-link" href="/leave_management_system/uploads/' . rawurlencode($request['proof']) . '" target="_blank" rel="noopener">View</a>'
                : '-',
        ],
        [
            'html' => '<a class="btn btn-secondary btn-sm" href="' . htmlspecialchars(buildLeaveReviewUrl($request, $buildUrl())) . '">Open</a>',
        ],
    ];
}

$requestStatusTotals = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'open' => 0,
    'closed' => 0,
];
$requestTypeTotals = [
    'casual' => 0,
    'medical' => 0,
    'on_duty' => 0,
    'academic' => 0,
    'vacation' => 0,
];
$requestTypeStatusTotals = [
    'casual' => ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'open' => 0, 'closed' => 0],
    'medical' => ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'open' => 0, 'closed' => 0],
    'on_duty' => ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'open' => 0, 'closed' => 0],
    'academic' => ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'open' => 0, 'closed' => 0],
    'vacation' => ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'open' => 0, 'closed' => 0],
];
foreach ($leaveRequestRows as $row) {
    $status = strtolower(trim((string)($row['status'] ?? '')));
    if (isset($requestStatusTotals[$status])) {
        $requestStatusTotals[$status]++;
    }
    $type = strtolower(trim((string)($row['leave_type'] ?? '')));
    if (isset($requestTypeTotals[$type])) {
        $requestTypeTotals[$type]++;
        if (isset($requestTypeStatusTotals[$type][$status])) {
            $requestTypeStatusTotals[$type][$status]++;
        }
    }
}
$requestDistributionRows = [];
$requestDistributionRows[] = [
    ['html' => '<strong>Total Leaves</strong>'],
    (string)count($leaveRequestRows),
    (string)$requestStatusTotals['pending'],
    (string)$requestStatusTotals['approved'],
    (string)$requestStatusTotals['rejected'],
    (string)$requestStatusTotals['open'],
    (string)$requestStatusTotals['closed'],
];
foreach ($requestReportLeaveTypes as $type) {
    $statusCounts = $requestTypeStatusTotals[$type];
    $requestDistributionRows[] = [
        getLeaveTypeLabel($type),
        (string)$requestTypeTotals[$type],
        (string)$statusCounts['pending'],
        (string)$statusCounts['approved'],
        (string)$statusCounts['rejected'],
        (string)$statusCounts['open'],
        (string)$statusCounts['closed'],
    ];
}

$absenceRoles = $absenceRole !== '' ? [$absenceRole] : ['student', 'faculty', 'hod', 'admin'];
$absenceRows = [];
foreach ($absenceRoles as $role) {
    $absenceRows = array_merge($absenceRows, getAbsentUsersOnDate($conn, $absenceDate, $role, $hodDepartment));
}
$absenceRows = array_values(array_filter($absenceRows, static function (array $row) use ($absenceLeaveType) {
    if ($absenceLeaveType !== '' && strtolower(trim((string)($row['leave_type'] ?? ''))) !== $absenceLeaveType) {
        return false;
    }
    return true;
}));

$absenceTableRows = [];
foreach ($absenceRows as $row) {
    $absenceTableRows[] = [
        [
            'html' => '<a href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">' . htmlspecialchars($row['name']) . '</a>',
        ],
        getRoleLabel($row['role']),
        getDepartmentLabel($row['department']),
        getLeaveTypeLabel($row['leave_type'] ?? ''),
        formatDate($row['from_date']),
        [
            'html' => $row['to_date'] ? htmlspecialchars(formatDate($row['to_date'])) : '<span class="badge badge-info">Open</span>',
        ],
        [
            'html' => '<a class="btn btn-secondary btn-sm" href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">History</a>',
        ],
    ];
}

$selectedStudentYear = (int)($_GET['student_year'] ?? 0);
if ($selectedStudentYear < 1 || $selectedStudentYear > 4) {
    $selectedStudentYear = 0;
}

$selectedStudentSection = strtoupper(trim($_GET['student_section'] ?? ''));
if ($selectedStudentYear === 0) {
    $selectedStudentSection = '';
}

$studentYearCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
foreach ($studentAbsentees as $row) {
    $year = (int)($row['student_year'] ?? 0);
    if (isset($studentYearCounts[$year])) {
        $studentYearCounts[$year]++;
    }
}

$studentSectionCounts = [];
foreach ($studentAbsentees as $row) {
    if ((int)($row['student_year'] ?? 0) !== $selectedStudentYear) {
        continue;
    }
    $section = strtoupper(trim((string)($row['student_section'] ?? '')));
    if ($section === '') {
        continue;
    }
    $studentSectionCounts[$section] = ($studentSectionCounts[$section] ?? 0) + 1;
}
ksort($studentSectionCounts);

$distributionDate = trim($_GET['distribution_date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $distributionDate)) {
    $distributionDate = '';
}
$distributionMonth = (int)($_GET['distribution_month'] ?? 0);
if ($distributionMonth < 0 || $distributionMonth > 12) {
    $distributionMonth = 0;
}
$distributionRows = getLeaveRequestsByFilters($conn, [
    'date' => $distributionMonth > 0 ? '' : $distributionDate,
    'month' => $distributionMonth,
    'department' => $hodDepartment,
]);

$requestStatusTotals = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'open' => 0,
    'closed' => 0,
];
$requestTypeTotals = [
    'casual' => 0,
    'medical' => 0,
    'on_duty' => 0,
    'academic' => 0,
    'vacation' => 0,
];
$requestTypeStatusTotals = [
    'casual' => ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'open' => 0, 'closed' => 0],
    'medical' => ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'open' => 0, 'closed' => 0],
    'on_duty' => ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'open' => 0, 'closed' => 0],
    'academic' => ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'open' => 0, 'closed' => 0],
    'vacation' => ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'open' => 0, 'closed' => 0],
];
foreach ($distributionRows as $row) {
    $status = strtolower(trim((string)($row['status'] ?? '')));
    if (isset($requestStatusTotals[$status])) {
        $requestStatusTotals[$status]++;
    }
    $type = strtolower(trim((string)($row['leave_type'] ?? '')));
    if (isset($requestTypeTotals[$type])) {
        $requestTypeTotals[$type]++;
        if (isset($requestTypeStatusTotals[$type][$status])) {
            $requestTypeStatusTotals[$type][$status]++;
        }
    }
}

$requestDistributionRows = [];
foreach ($requestTypeStatusTotals as $type => $statusCounts) {
    $reportLink = '/leave_management_system/hod/view_requests.php?' . http_build_query(array_filter([
        'department' => $hodDepartment,
        'date' => $distributionMonth > 0 ? '' : $distributionDate,
        'month' => $distributionMonth,
        'leave_type' => $type,
    ], static fn ($value) => $value !== '' && $value !== 0));
    $requestDistributionRows[] = [
        ['html' => '<a class="proof-link" href="' . htmlspecialchars($reportLink) . '">' . htmlspecialchars(getLeaveTypeLabel($type)) . '</a>'],
        ['html' => '<a class="proof-link" href="' . htmlspecialchars($reportLink) . '">' . (string)$requestTypeTotals[$type] . '</a>'],
        ['html' => '<a class="proof-link" href="' . htmlspecialchars($reportLink . '&status=pending') . '">' . (string)$statusCounts['pending'] . '</a>'],
        ['html' => '<a class="proof-link" href="' . htmlspecialchars($reportLink . '&status=approved') . '">' . (string)$statusCounts['approved'] . '</a>'],
        ['html' => '<a class="proof-link" href="' . htmlspecialchars($reportLink . '&status=rejected') . '">' . (string)$statusCounts['rejected'] . '</a>'],
        ['html' => '<a class="proof-link" href="' . htmlspecialchars($reportLink . '&status=open') . '">' . (string)$statusCounts['open'] . '</a>'],
        ['html' => '<a class="proof-link" href="' . htmlspecialchars($reportLink . '&status=closed') . '">' . (string)$statusCounts['closed'] . '</a>'],
    ];
}

$facultyMatrixRows = getLeaveRequestsByFilters($conn, [
    'date' => $distributionMonth > 0 ? '' : $distributionDate,
    'month' => $distributionMonth,
    'role' => 'faculty',
    'department' => $hodDepartment,
]);
$facultyMatrix = buildLeaveRequestMatrix($facultyMatrixRows);
$facultyMatrixLink = static function (array $params = []) use ($hodDepartment, $distributionDate, $distributionMonth) {
    $query = array_filter([
        'department' => $hodDepartment,
        'date' => $distributionMonth > 0 ? '' : $distributionDate,
        'month' => $distributionMonth,
        'role' => 'faculty',
        'leave_type' => $params['leave_type'] ?? null,
        'status' => $params['status'] ?? null,
    ], static function ($value) {
        return $value !== '' && $value !== null && $value !== 0;
    });

    return '/leave_management_system/hod/view_requests.php' . (!empty($query) ? '?' . http_build_query($query) : '');
};

$distributionBaseParams = array_filter([
    'department' => $hodDepartment,
    'date' => $distributionMonth > 0 ? '' : $distributionDate,
    'month' => $distributionMonth,
], static function ($value) {
    return $value !== '' && $value !== 0;
});
$distributionReportUrl = '/leave_management_system/hod/view_requests.php' . (!empty($distributionBaseParams) ? '?' . http_build_query($distributionBaseParams) : '');

$currentViewLabel = 'Students';
if ($selectedStudentYear > 0) {
    $currentViewLabel .= ' - Year ' . $selectedStudentYear;
}
if ($selectedStudentSection !== '') {
    $currentViewLabel .= ' - Section ' . $selectedStudentSection;
}
?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content principal-main">
        <div class="content-header principal-header" id="principal-overview">
            <div>
                <h2>HOD Dashboard</h2>
                <p class="page-subtitle">Focused only on <?php echo htmlspecialchars($departmentLabel); ?> so the list stays practical and quick to scan.</p>
            </div>
            <div class="principal-meta">
                <span class="filter-chip"><?php echo htmlspecialchars($departmentLabel); ?></span>
                <span class="filter-chip">Today: <?php echo htmlspecialchars(date('d M Y')); ?></span>
                <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($currentUserHistoryUrl); ?>">My Profile</a>
            </div>
        </div>

        <div class="card page-search-panel">
            <div class="card-header section-header">
                <div>
                    <h3>Quick People Search</h3>
                    <div class="section-note">Search people in your department without leaving the dashboard.</div>
                </div>
            </div>
            <form method="GET" action="/leave_management_system/admin/people_search.php" class="filter-form report-filter-form page-search-form">
                <div class="form-group page-search-input">
                    <label for="people-search-q">Search</label>
                    <input type="text" id="people-search-q" name="q" class="form-control" placeholder="Name, roll number, or employee id">
                </div>
                <div class="form-group">
                    <label for="people-search-scope">Scope</label>
                    <select id="people-search-scope" name="scope" class="form-control">
                        <option value="all">All People</option>
                        <option value="student">Students</option>
                        <option value="faculty">Faculty</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>
        </div>

        <div class="card leave-management-card">
            <div class="card-header section-header">
                <div>
                    <h3>Leave Management</h3>
                    <div class="section-note">Submit HOD leave requests and track what the Principal has approved.</div>
                </div>
                <div class="section-actions">
                    <a class="btn btn-secondary btn-sm" href="/leave_management_system/hod/apply_leave.php">Apply Leave</a>
                    <a class="btn btn-secondary btn-sm" href="/leave_management_system/hod/view_leaves.php">My Leaves</a>
                    <a class="btn btn-secondary btn-sm" href="/leave_management_system/hod/upload_proof.php">Upload Proof</a>
                </div>
            </div>
            <div class="stats-grid report-mini-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo (int)$summary['total_requests']; ?></div>
                    <div class="stat-label">Total Leaves</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo (int)$summary['pending_requests']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-number"><?php echo (int)$summary['approved_requests']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card open">
                    <div class="stat-number"><?php echo $casualUsed; ?></div>
                    <div class="stat-label">CL Taken</div>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">CL Taken / 15</div>
                <div class="detail-value"><?php echo htmlspecialchars($casualDisplay); ?>, <?php echo $casualBalance; ?> left</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Designation</div>
                <div class="detail-value"><?php echo htmlspecialchars($user['designation'] ?: 'HOD'); ?></div>
        </div>
        </div>

        <section class="card metric-matrix-card">
            <div class="card-header section-header">
                <div>
                    <h3>Faculty Leave Matrix</h3>
                    <div class="section-note">Faculty-only summary for <?php echo htmlspecialchars($departmentLabel); ?>. Use the report filters below to change the slice.</div>
                </div>
                <div class="section-actions">
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars('/leave_management_system/hod/view_requests.php?' . http_build_query(array_filter([
                        'department' => $hodDepartment,
                        'date' => $distributionMonth > 0 ? '' : $distributionDate,
                        'month' => $distributionMonth,
                        'role' => 'faculty',
                    ], static fn ($value) => $value !== '' && $value !== 0))); ?>">Open Requests</a>
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
                            <td>All Faculty Leaves</td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($facultyMatrixLink()); ?>"><?php echo (int)$facultyMatrix['total']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($facultyMatrixLink(['status' => 'pending'])); ?>"><?php echo (int)$facultyMatrix['status_totals']['pending']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($facultyMatrixLink(['status' => 'approved'])); ?>"><?php echo (int)$facultyMatrix['status_totals']['approved']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($facultyMatrixLink(['status' => 'rejected'])); ?>"><?php echo (int)$facultyMatrix['status_totals']['rejected']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($facultyMatrixLink(['status' => 'open'])); ?>"><?php echo (int)$facultyMatrix['status_totals']['open']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($facultyMatrixLink(['status' => 'closed'])); ?>"><?php echo (int)$facultyMatrix['status_totals']['closed']; ?></a></td>
                        </tr>
                        <?php foreach ($facultyMatrix['type_codes'] as $typeCode): ?>
                        <tr>
                            <td><a class="proof-link" href="<?php echo htmlspecialchars($facultyMatrixLink(['leave_type' => $typeCode])); ?>"><?php echo htmlspecialchars(getLeaveTypeLabel($typeCode)); ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($facultyMatrixLink(['leave_type' => $typeCode])); ?>"><?php echo (int)$facultyMatrix['type_totals'][$typeCode]; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($facultyMatrixLink(['leave_type' => $typeCode, 'status' => 'pending'])); ?>"><?php echo (int)$facultyMatrix['matrix'][$typeCode]['pending']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($facultyMatrixLink(['leave_type' => $typeCode, 'status' => 'approved'])); ?>"><?php echo (int)$facultyMatrix['matrix'][$typeCode]['approved']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($facultyMatrixLink(['leave_type' => $typeCode, 'status' => 'rejected'])); ?>"><?php echo (int)$facultyMatrix['matrix'][$typeCode]['rejected']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($facultyMatrixLink(['leave_type' => $typeCode, 'status' => 'open'])); ?>"><?php echo (int)$facultyMatrix['matrix'][$typeCode]['open']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($facultyMatrixLink(['leave_type' => $typeCode, 'status' => 'closed'])); ?>"><?php echo (int)$facultyMatrix['matrix'][$typeCode]['closed']; ?></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="stats-grid principal-hub-grid">
            <?php echo $renderHubCard([
                'kicker' => 'Today',
                'label' => 'Students',
                'value' => $presenceStats['total_students'],
                'copy' => 'Open the year and section drill-down.',
                'class' => 'faculty',
                'active' => $selectedView === 'student',
                'href' => '/leave_management_system/hod/focus.php?view=student',
            ]); ?>
            <?php echo $renderHubCard([
                'kicker' => 'Today',
                'label' => 'Student Absentees',
                'value' => $studentAbsenteeCount,
                'copy' => 'Jump straight to the student absentee list.',
                'class' => 'student',
                'active' => $selectedView === 'student',
                'href' => '/leave_management_system/hod/focus.php?view=student',
            ]); ?>
            <?php echo $renderHubCard([
                'kicker' => 'Today',
                'label' => 'Faculty',
                'value' => $presenceStats['total_staff'],
                'copy' => 'Open the faculty absentee list.',
                'class' => 'admin',
                'active' => $selectedView === 'faculty',
                'href' => '/leave_management_system/hod/focus.php?view=faculty',
            ]); ?>
        </div>

        <section class="card distribution-panel" id="leaves-section">
            <div class="card-header section-header">
                <div>
                    <h3>Leave Distribution</h3>
                    <div class="section-note">Pick a date or a month, then open the full HOD report in a new page.</div>
                </div>
                <div class="section-actions">
                    <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($distributionReportUrl); ?>" target="_blank" rel="noopener">Open Full Report</a>
                </div>
            </div>
            <form method="GET" action="/leave_management_system/hod/dashboard.php#leaves-section" class="filter-form report-filter-form">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($selectedView); ?>">
                <div class="form-group">
                    <label for="distribution_date">Date</label>
                    <input type="date" id="distribution_date" name="distribution_date" class="form-control" value="<?php echo htmlspecialchars($distributionDate); ?>">
                </div>
                <div class="form-group">
                    <label for="distribution_month">Month</label>
                    <select id="distribution_month" name="distribution_month" class="form-control">
                        <option value="0">All Months</option>
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                        <option value="<?php echo $month; ?>" <?php echo $distributionMonth === $month ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(date('F', mktime(0, 0, 0, $month, 1))); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a class="btn btn-secondary" href="<?php echo htmlspecialchars($buildUrl(['distribution_date' => '', 'distribution_month' => ''])); ?>">Reset</a>
                </div>
            </form>
            <div class="filter-chip-row">
                <span class="filter-chip">Date: <?php echo $distributionDate !== '' ? htmlspecialchars(formatDate($distributionDate)) : 'All Dates'; ?></span>
                <span class="filter-chip">Month: <?php echo $distributionMonth > 0 ? htmlspecialchars(date('F', mktime(0, 0, 0, $distributionMonth, 1))) : 'All'; ?></span>
                <span class="filter-chip">Rows: <?php echo count($distributionRows); ?></span>
            </div>
            <?php echo $renderTable(['Type', 'Total', 'Pending', 'Approved', 'Rejected', 'Open', 'Closed'], $requestDistributionRows, 'No leave requests found for the selected distribution filters.'); ?>
        </section>

        <section class="card merged-report-panel">
            <div class="card-header section-header">
                <div>
                    <h3>Dashboard Explorer</h3>
                    <div class="section-note">Keep the requests summary and the absentee search in one place.</div>
                </div>
                <div class="section-actions explorer-switches">
                    <a class="btn btn-secondary btn-sm<?php echo $explorerMode === 'requests' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(['explorer_mode' => 'requests'])); ?>">Leave Requests</a>
                    <a class="btn btn-secondary btn-sm<?php echo $explorerMode === 'absentees' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(['explorer_mode' => 'absentees'])); ?>">Absentees By Date</a>
                </div>
            </div>
            <div class="merged-report-block">
            <?php if ($explorerMode === 'requests'): ?>
        <section class="card report-panel">
                <div class="card-header section-header">
                    <div>
                        <h3>Leave Requests Report</h3>
                    <div class="section-note">Keep the department fixed and filter by date, year, month, role, leave type, or student section.</div>
                </div>
            </div>
            <form method="GET" action="/leave_management_system/hod/dashboard.php" class="filter-form report-filter-form">
                <input type="hidden" name="explorer_mode" value="requests">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($selectedView); ?>">
                <input type="hidden" name="student_year" value="<?php echo $selectedStudentYear > 0 ? (int)$selectedStudentYear : ''; ?>">
                <input type="hidden" name="student_section" value="<?php echo htmlspecialchars($selectedStudentSection); ?>">
                <div class="form-group">
                    <label for="report_date">Date</label>
                    <input type="date" id="report_date" name="report_date" class="form-control" value="<?php echo htmlspecialchars($requestReportDate); ?>">
                </div>
                <div class="form-group">
                    <label for="report_year">Year</label>
                    <select id="report_year" name="report_year" class="form-control">
                        <option value="0">All Years</option>
                        <?php foreach ($reportYears as $year): ?>
                        <option value="<?php echo (int)$year; ?>" <?php echo $requestReportYear === (int)$year ? 'selected' : ''; ?>>
                            <?php echo (int)$year; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="report_month">Month</label>
                    <select id="report_month" name="report_month" class="form-control">
                        <option value="0">All Months</option>
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                        <option value="<?php echo $month; ?>" <?php echo $requestReportMonth === $month ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(date('F', mktime(0, 0, 0, $month, 1))); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="report_role">Role</label>
                    <select id="report_role" name="report_role" class="form-control">
                        <option value="all">Everybody</option>
                        <option value="student" <?php echo $requestReportRole === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="faculty" <?php echo $requestReportRole === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                        <option value="hod" <?php echo $requestReportRole === 'hod' ? 'selected' : ''; ?>>HOD</option>
                        <option value="admin" <?php echo $requestReportRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="report_leave_type">Leave Type</label>
                    <select id="report_leave_type" name="report_leave_type" class="form-control">
                        <option value="all">All Types</option>
                        <?php foreach ($requestReportLeaveTypes as $leaveTypeCode): ?>
                        <option value="<?php echo htmlspecialchars($leaveTypeCode); ?>" <?php echo $requestReportLeaveType === $leaveTypeCode ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(getLeaveTypeLabel($leaveTypeCode)); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="report_student_year">Student Year</label>
                    <select id="report_student_year" name="report_student_year" class="form-control">
                        <option value="0">All Years</option>
                        <?php for ($studentYear = 1; $studentYear <= 4; $studentYear++): ?>
                        <option value="<?php echo $studentYear; ?>" <?php echo $requestReportStudentYear === $studentYear ? 'selected' : ''; ?>>
                            Year <?php echo $studentYear; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="report_student_section">Student Section</label>
                    <input type="text" id="report_student_section" name="report_student_section" class="form-control" value="<?php echo htmlspecialchars($requestReportStudentSection); ?>" placeholder="A, B, C...">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a class="btn btn-secondary" href="<?php echo htmlspecialchars($buildUrl(['explorer_mode' => 'requests', 'report_date' => '', 'report_year' => '', 'report_month' => '', 'report_role' => '', 'report_leave_type' => '', 'report_student_year' => '', 'report_student_section' => ''])); ?>">Reset</a>
                </div>
            </form>
            <div class="filter-chip-row">
                <span class="filter-chip">Date: <?php echo htmlspecialchars(formatDate($requestReportDate)); ?></span>
                <span class="filter-chip">Rows: <?php echo count($leaveRequestRows); ?></span>
                <span class="filter-chip">Year: <?php echo $requestReportYear > 0 ? (int)$requestReportYear : 'All'; ?></span>
                <span class="filter-chip">Month: <?php echo $requestReportMonth > 0 ? htmlspecialchars(date('F', mktime(0, 0, 0, $requestReportMonth, 1))) : 'All'; ?></span>
                <span class="filter-chip">Role: <?php echo htmlspecialchars($requestReportRole === '' ? 'Everybody' : getRoleLabel($requestReportRole)); ?></span>
                <span class="filter-chip">Type: <?php echo htmlspecialchars($requestReportLeaveType === '' ? 'All Types' : getLeaveTypeLabel($requestReportLeaveType)); ?></span>
            </div>
            <?php echo $renderTable(['Type', 'Total', 'Pending', 'Approved', 'Rejected', 'Open', 'Closed'], $requestDistributionRows, 'No leave requests found for the selected date and filters.'); ?>
            <?php echo $renderTable(
                ['Applicant', 'Role', 'Department', 'Type', 'From', 'To', 'Status', 'Proof', 'Action'],
                $leaveRequestTableRows,
                'No leave requests match the selected filters.'
            ); ?>
        </section>

            <div class="merged-report-divider"></div>
            <?php else: ?>
        <section class="card report-panel">
            <div class="card-header section-header">
                <div>
                    <h3>Absentees By Date</h3>
                    <div class="section-note">Search a specific date and narrow the list with role and leave-type filters.</div>
                </div>
            </div>
            <form method="GET" action="/leave_management_system/hod/dashboard.php" class="filter-form report-filter-form">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($selectedView); ?>">
                <input type="hidden" name="student_year" value="<?php echo $selectedStudentYear > 0 ? (int)$selectedStudentYear : ''; ?>">
                <input type="hidden" name="student_section" value="<?php echo htmlspecialchars($selectedStudentSection); ?>">
                <div class="form-group">
                    <label for="absence_date">Date</label>
                    <input type="date" id="absence_date" name="absence_date" class="form-control" value="<?php echo htmlspecialchars($absenceDate); ?>">
                </div>
                <div class="form-group">
                    <label for="absence_role">Role</label>
                    <select id="absence_role" name="absence_role" class="form-control">
                        <option value="all">Everybody</option>
                        <option value="student" <?php echo $absenceRole === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="faculty" <?php echo $absenceRole === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                        <option value="hod" <?php echo $absenceRole === 'hod' ? 'selected' : ''; ?>>HOD</option>
                        <option value="admin" <?php echo $absenceRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="absence_leave_type">Leave Type</label>
                    <select id="absence_leave_type" name="absence_leave_type" class="form-control">
                        <option value="all">All Types</option>
                        <?php foreach ($absenceLeaveTypes as $leaveTypeCode): ?>
                        <option value="<?php echo htmlspecialchars($leaveTypeCode); ?>" <?php echo $absenceLeaveType === $leaveTypeCode ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(getLeaveTypeLabel($leaveTypeCode)); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Search Absentees</button>
                    <a class="btn btn-secondary" href="<?php echo htmlspecialchars($buildUrl(['absence_date' => '', 'absence_role' => '', 'absence_leave_type' => ''])); ?>">Reset</a>
                </div>
            </form>
            <div class="filter-chip-row">
                <span class="filter-chip">Date: <?php echo htmlspecialchars(formatDate($absenceDate)); ?></span>
                <span class="filter-chip">Rows: <?php echo count($absenceRows); ?></span>
                <span class="filter-chip">Role: <?php echo htmlspecialchars($absenceRole === '' ? 'Everybody' : getRoleLabel($absenceRole)); ?></span>
                <span class="filter-chip">Type: <?php echo htmlspecialchars($absenceLeaveType === '' ? 'All Types' : getLeaveTypeLabel($absenceLeaveType)); ?></span>
            </div>
            <?php echo $renderTable(
                ['Name', 'Role', 'Department', 'Leave Type', 'From', 'To', 'History'],
                $absenceTableRows,
                'No absentees found for the selected date and filters.'
            ); ?>
        </section>

            </div>
        </section>
            <?php endif; ?>

        </div>
        </section>

        <section class="card principal-detail-card">
            <div class="card-header section-header">
                <div>
                    <h3><?php echo htmlspecialchars($selectedView === 'faculty' ? 'Faculty View' : ($selectedView === 'student' ? 'Student View' : 'Overview')); ?></h3>
                    <div class="section-note">
                        <?php echo htmlspecialchars($selectedView === 'faculty' ? 'Faculty absentees for the chosen department.' : ($selectedView === 'student' ? 'Students by year and section.' : 'Choose a card to focus the detail panel.')); ?>
                    </div>
                </div>
                <div class="section-actions">
                    <?php if ($selectedView !== ''): ?>
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($buildUrl(['view' => '', 'student_year' => '', 'student_section' => ''])); ?>">Clear</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="principal-crumbs">
                <span class="crumb"><?php echo htmlspecialchars($departmentLabel); ?></span>
                <?php if ($selectedView === 'faculty'): ?>
                <span class="crumb">Faculty</span>
                <?php elseif ($selectedView === 'student'): ?>
                <span class="crumb">Students</span>
                <?php if ($selectedStudentYear > 0): ?>
                <span class="crumb">Year <?php echo (int)$selectedStudentYear; ?></span>
                <?php endif; ?>
                <?php if ($selectedStudentSection !== ''): ?>
                <span class="crumb">Section <?php echo htmlspecialchars($selectedStudentSection); ?></span>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($selectedView === 'faculty'): ?>
                <?php
                $facultyRows = [];
                foreach ($facultyAbsentees as $row) {
                    $facultyRows[] = [
                        [
                            'html' => '<a class="proof-link" href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">' . htmlspecialchars($row['name']) . '</a><br><small>' . htmlspecialchars(getUserMetaText($row)) . '</small>',
                        ],
                        $row['designation'] ?: 'Faculty',
                        getFacultyCasualLeaveDisplay($row),
                        [
                            'html' => '<a class="btn btn-secondary btn-sm" href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">History</a>',
                        ],
                    ];
                }
                echo $renderTable(
                    ['Name', 'Designation', 'CL Taken / 15', 'History'],
                    $facultyRows,
                    'No faculty absentees found today.',
                    ['Total', count($facultyAbsentees), '', '']
                );
                ?>
            <?php elseif ($selectedView === 'student'): ?>
                <?php if ($selectedStudentYear === 0): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Absent Students</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentYearCounts as $year => $count): ?>
                            <tr>
                                <td>
                                    <a class="proof-link" href="<?php echo htmlspecialchars($buildUrl(['view' => 'student', 'student_year' => $year, 'student_section' => ''])); ?>">
                                        Year <?php echo (int)$year; ?>
                                    </a>
                                </td>
                                <td><?php echo (int)$count; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php elseif ($selectedStudentSection === ''): ?>
                <?php
                $sectionRows = [];
                foreach ($studentSectionCounts as $section => $count) {
                    $sectionRows[] = [
                        [
                            'html' => '<a class="proof-link" href="' . htmlspecialchars($buildUrl(['view' => 'student', 'student_year' => $selectedStudentYear, 'student_section' => $section])) . '">' . htmlspecialchars($section) . '</a>',
                        ],
                        $count,
                    ];
                }
                echo $renderTable(
                    ['Section', 'Absent Students'],
                    $sectionRows,
                    'No section-wise student absentees found for this year.',
                    ['Total', array_sum($studentSectionCounts)]
                );
                ?>
                <?php else: ?>
                <?php
                $studentRows = [];
                foreach ($studentAbsentees as $row) {
                    if ((int)($row['student_year'] ?? 0) !== $selectedStudentYear) {
                        continue;
                    }
                    if (strtoupper(trim((string)($row['student_section'] ?? ''))) !== $selectedStudentSection) {
                        continue;
                    }
                    $studentRows[] = [
                        [
                            'html' => '<a class="proof-link" href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">' . htmlspecialchars($row['name']) . '</a><br><small>' . htmlspecialchars(getUserMetaText($row)) . '</small>',
                        ],
                        (string)($row['regd_no'] ?? ''),
                        getLeaveTypeLabel($row['leave_type'] ?? ''),
                        [
                            'html' => '<a class="btn btn-secondary btn-sm" href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">History</a>',
                        ],
                    ];
                }
                echo $renderTable(
                    ['Name', 'Regd No', 'Active Leave', 'History'],
                    $studentRows,
                    'No students found in this section.',
                    ['Total', count($studentRows), '', '']
                );
                ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="principal-empty-state">
                    <p>Select a card above to focus on faculty or student details.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-header section-header">
                <div>
                    <h3>Presence Summary</h3>
                    <div class="section-note">Quick totals for the current department.</div>
                </div>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Students Present Today</td><td><?php echo (int)$presenceStats['students_present']; ?></td></tr>
                        <tr><td>Students Absent Today</td><td><?php echo (int)$presenceStats['students_absent']; ?></td></tr>
                        <tr><td>Staff Present Today</td><td><?php echo (int)$presenceStats['staff_present']; ?></td></tr>
                        <tr><td>Staff Absent Today</td><td><?php echo (int)$presenceStats['staff_absent']; ?></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

</body>
</html>

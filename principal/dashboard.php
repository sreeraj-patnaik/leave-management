<?php
require_once __DIR__ . '/../includes/header.php';

checkRole('principal');

$page_title = 'Principal Dashboard';
$currentDate = date('Y-m-d');
$departments = getDepartments($conn);
$departmentLabels = [];
foreach ($departments as $department) {
    $departmentLabels[$department['code']] = $department['label'];
}

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

    return '/leave_management_system/principal/dashboard.php' . (!empty($params) ? '?' . http_build_query($params) : '');
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
$selectedFacultyDepartment = strtolower(trim($_GET['faculty_department'] ?? ''));
$selectedStudentDepartment = strtolower(trim($_GET['student_department'] ?? ''));
$selectedStudentYear = (int)($_GET['student_year'] ?? 0);
$selectedStudentSection = strtoupper(trim($_GET['student_section'] ?? ''));

if ($selectedFacultyDepartment !== '' && !array_key_exists($selectedFacultyDepartment, $departmentLabels)) {
    $selectedFacultyDepartment = '';
}
if ($selectedStudentDepartment !== '' && !array_key_exists($selectedStudentDepartment, $departmentLabels)) {
    $selectedStudentDepartment = '';
}
if ($selectedStudentYear < 1 || $selectedStudentYear > 4) {
    $selectedStudentYear = 0;
}
if (!in_array($selectedView, ['faculty', 'student', 'admin'], true)) {
    if ($selectedFacultyDepartment !== '') {
        $selectedView = 'faculty';
    } elseif ($selectedStudentDepartment !== '' || $selectedStudentYear > 0 || $selectedStudentSection !== '') {
        $selectedView = 'student';
    } else {
        $selectedView = '';
    }
}

if ($selectedStudentDepartment === '') {
    $selectedStudentYear = 0;
    $selectedStudentSection = '';
} elseif ($selectedStudentYear === 0) {
    $selectedStudentSection = '';
}

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
$requestReportDate = trim($_GET['report_date'] ?? $currentDate);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestReportDate)) {
    $requestReportDate = $currentDate;
}
$requestReportDepartment = strtolower(trim($_GET['report_department'] ?? ''));
if ($requestReportDepartment !== '' && !array_key_exists($requestReportDepartment, $departmentLabels)) {
    $requestReportDepartment = '';
}
$requestReportStudentYear = (int)($_GET['report_student_year'] ?? 0);
if ($requestReportStudentYear < 1 || $requestReportStudentYear > 4) {
    $requestReportStudentYear = 0;
}
$requestReportStudentSection = strtoupper(trim($_GET['report_student_section'] ?? ''));
if ($requestReportStudentSection === 'ALL') {
    $requestReportStudentSection = '';
}

$absenceDate = trim($_GET['absence_date'] ?? $currentDate);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $absenceDate)) {
    $absenceDate = $currentDate;
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
$absenceDepartment = strtolower(trim($_GET['absence_department'] ?? ''));
if ($absenceDepartment !== '' && !array_key_exists($absenceDepartment, $departmentLabels)) {
    $absenceDepartment = '';
}

$requestReportLeaveTypes = $requestReportAllowedLeaveTypes;
$absenceLeaveTypes = $absenceAllowedLeaveTypes;

$explorerMode = strtolower(trim($_GET['explorer_mode'] ?? ''));
if (!in_array($explorerMode, ['requests', 'absentees'], true)) {
    $explorerMode = ($absenceDate !== $currentDate || $absenceRole !== '' || $absenceLeaveType !== '' || $absenceDepartment !== '') ? 'absentees' : 'requests';
}
if ($explorerMode !== 'absentees' && (
    $requestReportYear > 0 ||
    $requestReportMonth > 0 ||
    $requestReportRole !== '' ||
    $requestReportLeaveType !== '' ||
    $requestReportDepartment !== '' ||
    $requestReportStudentYear > 0 ||
    $requestReportStudentSection !== ''
)) {
    $explorerMode = 'requests';
}
if (!in_array($explorerMode, ['requests', 'absentees'], true)) {
    $explorerMode = 'requests';
}

$leaveRequestRows = getLeaveRequestsByFilters($conn, [
    'year' => $requestReportYear,
    'month' => $requestReportMonth,
    'role' => $requestReportRole,
    'leave_type' => $requestReportLeaveType,
    'department' => $requestReportDepartment,
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
    $absenceRows = array_merge($absenceRows, getAbsentUsersOnDate($conn, $absenceDate, $role, $absenceDepartment !== '' ? $absenceDepartment : null));
}
$absenceRows = array_values(array_filter($absenceRows, static function (array $row) use ($absenceLeaveType, $absenceDepartment) {
    if ($absenceLeaveType !== '' && strtolower(trim((string)($row['leave_type'] ?? ''))) !== $absenceLeaveType) {
        return false;
    }
    if ($absenceDepartment !== '' && strtolower(trim((string)($row['department'] ?? ''))) !== $absenceDepartment) {
        return false;
    }
    return true;
}));

$distributionDate = trim($_GET['distribution_date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $distributionDate)) {
    $distributionDate = '';
}
$distributionMonth = (int)($_GET['distribution_month'] ?? 0);
if ($distributionMonth < 0 || $distributionMonth > 12) {
    $distributionMonth = 0;
}
$distributionDepartment = strtolower(trim($_GET['distribution_department'] ?? ''));
if ($distributionDepartment !== '' && !array_key_exists($distributionDepartment, $departmentLabels)) {
    $distributionDepartment = '';
}

$distributionRows = getLeaveRequestsByFilters($conn, [
    'date' => $distributionMonth > 0 ? '' : $distributionDate,
    'month' => $distributionMonth,
    'department' => $distributionDepartment,
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
$requestDistributionRows[] = [
    ['html' => '<a class="proof-link" href="' . htmlspecialchars('/leave_management_system/principal/requests.php' . (!empty(array_filter([
        'date' => $distributionMonth > 0 ? '' : $distributionDate,
        'month' => $distributionMonth,
        'department' => $distributionDepartment,
    ])) ? '?' . http_build_query(array_filter([
        'date' => $distributionMonth > 0 ? '' : $distributionDate,
        'month' => $distributionMonth,
        'department' => $distributionDepartment,
    ], static fn ($value) => $value !== '' && $value !== 0)) : '')) . '">Total Leaves</a>'],
    ['html' => '<a class="proof-link" href="' . htmlspecialchars('/leave_management_system/principal/requests.php' . (!empty(array_filter([
        'date' => $distributionMonth > 0 ? '' : $distributionDate,
        'month' => $distributionMonth,
        'department' => $distributionDepartment,
    ])) ? '?' . http_build_query(array_filter([
        'date' => $distributionMonth > 0 ? '' : $distributionDate,
        'month' => $distributionMonth,
        'department' => $distributionDepartment,
    ], static fn ($value) => $value !== '' && $value !== 0)) : '')) . '">' . count($distributionRows) . '</a>'],
    (string)$requestStatusTotals['pending'],
    (string)$requestStatusTotals['approved'],
    (string)$requestStatusTotals['rejected'],
    (string)$requestStatusTotals['open'],
    (string)$requestStatusTotals['closed'],
];
foreach ($requestTypeStatusTotals as $type => $statusCounts) {
    $reportLink = '/leave_management_system/principal/requests.php?' . http_build_query(array_filter([
        'date' => $distributionMonth > 0 ? '' : $distributionDate,
        'month' => $distributionMonth,
        'department' => $distributionDepartment,
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

$matrixData = buildLeaveRequestMatrix($distributionRows);
$matrixLink = static function (array $params = []) use ($distributionDate, $distributionMonth, $distributionDepartment) {
    $query = array_filter([
        'date' => $distributionMonth > 0 ? '' : $distributionDate,
        'month' => $distributionMonth,
        'department' => $distributionDepartment,
        'leave_type' => $params['leave_type'] ?? null,
        'status' => $params['status'] ?? null,
    ], static function ($value) {
        return $value !== '' && $value !== null && $value !== 0;
    });

    return '/leave_management_system/principal/requests.php' . (!empty($query) ? '?' . http_build_query($query) : '');
};

$distributionBaseParams = array_filter([
    'date' => $distributionMonth > 0 ? '' : $distributionDate,
    'month' => $distributionMonth,
    'department' => $distributionDepartment,
], static function ($value) {
    return $value !== '' && $value !== 0;
});
$distributionReportUrl = '/leave_management_system/principal/requests.php' . (!empty($distributionBaseParams) ? '?' . http_build_query($distributionBaseParams) : '');

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

$facultyAbsentees = getAbsentUsersOnDate($conn, $currentDate, 'faculty');
$studentAbsentees = getAbsentUsersOnDate($conn, $currentDate, 'student');
$adminAbsentees = array_values(array_filter(
    getAbsentUsersOnDate($conn, $currentDate, 'admin'),
    static function ($row) {
        return (int)($row['admin_team'] ?? 0) === 1;
    }
));

$groupedFacultyAbsentees = [];
foreach ($facultyAbsentees as $row) {
    $departmentCode = strtolower((string)($row['department'] ?? ''));
    if ($departmentCode === '') {
        continue;
    }
    $groupedFacultyAbsentees[$departmentCode][] = $row;
}

$groupedStudentAbsentees = [];
foreach ($studentAbsentees as $row) {
    $departmentCode = strtolower((string)($row['department'] ?? ''));
    if ($departmentCode === '') {
        continue;
    }
    $groupedStudentAbsentees[$departmentCode][] = $row;
}

$facultyDepartmentRows = [];
$studentDepartmentRows = [];
foreach ($departments as $department) {
    $code = $department['code'];
    $facultyDepartmentRows[] = [
        [
            'html' => '<a href="' . htmlspecialchars($buildUrl(['view' => 'faculty', 'faculty_department' => $code, 'student_department' => '', 'student_year' => '', 'student_section' => '', 'admin_department' => ''])) . '">' . htmlspecialchars($department['label']) . '</a>',
        ],
        count($groupedFacultyAbsentees[$code] ?? []),
    ];
    $studentDepartmentRows[] = [
        [
            'html' => '<a href="' . htmlspecialchars($buildUrl(['view' => 'student', 'student_department' => $code, 'student_year' => '', 'student_section' => '', 'faculty_department' => '', 'admin_department' => ''])) . '">' . htmlspecialchars($department['label']) . '</a>',
        ],
        count($groupedStudentAbsentees[$code] ?? []),
    ];
}

$selectedFacultyRows = $selectedFacultyDepartment !== '' ? ($groupedFacultyAbsentees[$selectedFacultyDepartment] ?? []) : [];
$selectedStudentRows = $selectedStudentDepartment !== '' ? ($groupedStudentAbsentees[$selectedStudentDepartment] ?? []) : [];

$facultyTitle = 'Faculty';
$facultySubtitle = 'Department first, then names and history.';
if ($selectedFacultyDepartment !== '') {
    $facultyTitle = 'Faculty - ' . ($departmentLabels[$selectedFacultyDepartment] ?? strtoupper($selectedFacultyDepartment));
    $facultySubtitle = 'Absent faculty in this department.';
}

$studentTitle = 'Students';
$studentSubtitle = 'Department -> year -> section -> individual.';
if ($selectedStudentDepartment !== '') {
    $studentTitle = 'Students - ' . ($departmentLabels[$selectedStudentDepartment] ?? strtoupper($selectedStudentDepartment));
    $studentSubtitle = 'Now drill into year, section, and then each student.';
    if ($selectedStudentYear > 0) {
        $studentTitle .= ' - Year ' . $selectedStudentYear;
    }
    if ($selectedStudentSection !== '') {
        $studentTitle .= ' - Section ' . $selectedStudentSection;
    }
}

$adminTitle = 'Admin Team';
$adminSubtitle = 'Direct admin-team absentee list.';

$cardView = $selectedView;
$detailHtml = '';

if ($cardView === 'faculty') {
    if ($selectedFacultyDepartment === '') {
        $detailHtml = $renderTable(
            ['Department', 'Absent Faculty'],
            $facultyDepartmentRows,
            'No faculty absentees found today.',
            ['Total', count($facultyAbsentees)]
        );
    } else {
        $rows = [];
        foreach ($selectedFacultyRows as $row) {
            $rows[] = [
                [
                    'html' => '<a href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">' . htmlspecialchars($row['name']) . '</a>',
                ],
                $row['designation'] ?: 'Faculty',
                getFacultyCasualLeaveDisplay($row),
                [
                    'html' => '<a class="btn btn-secondary btn-sm" href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">History</a>',
                ],
            ];
        }
        $detailHtml = $renderTable(
            ['Name', 'Designation', 'CL Taken / 15', 'History'],
            $rows,
            'No faculty absentees found in this department.',
            ['Total', count($selectedFacultyRows), '', '']
        );
    }
} elseif ($cardView === 'student') {
    if ($selectedStudentDepartment === '') {
        $detailHtml = $renderTable(
            ['Department', 'Absent Students'],
            $studentDepartmentRows,
            'No student absentees found today.',
            ['Total', count($studentAbsentees)]
        );
    } elseif ($selectedStudentYear === 0) {
        $yearRows = [];
        $yearCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        foreach ($selectedStudentRows as $row) {
            $year = (int)($row['student_year'] ?? 0);
            if (isset($yearCounts[$year])) {
                $yearCounts[$year]++;
            }
        }
        foreach ($yearCounts as $year => $count) {
            $yearRows[] = [
                [
                    'html' => '<a href="' . htmlspecialchars($buildUrl(['view' => 'student', 'student_department' => $selectedStudentDepartment, 'student_year' => $year, 'student_section' => '', 'faculty_department' => '', 'admin_department' => ''])) . '">' . $year . '</a>',
                ],
                $count,
            ];
        }
        $detailHtml = $renderTable(
            ['Year', 'Absent Students'],
            $yearRows,
            'No year-wise student absentees found.',
            ['Total', count($selectedStudentRows)]
        );
    } elseif ($selectedStudentSection === '') {
        $sectionCounts = [];
        foreach ($selectedStudentRows as $row) {
            if ((int)($row['student_year'] ?? 0) !== $selectedStudentYear) {
                continue;
            }
            $section = strtoupper(trim((string)($row['student_section'] ?? '')));
            if ($section === '') {
                continue;
            }
            $sectionCounts[$section] = ($sectionCounts[$section] ?? 0) + 1;
        }
        ksort($sectionCounts);
        $sectionRows = [];
        foreach ($sectionCounts as $section => $count) {
            $sectionRows[] = [
                [
                    'html' => '<a href="' . htmlspecialchars($buildUrl(['view' => 'student', 'student_department' => $selectedStudentDepartment, 'student_year' => $selectedStudentYear, 'student_section' => $section, 'faculty_department' => '', 'admin_department' => ''])) . '">' . htmlspecialchars($section) . '</a>',
                ],
                $count,
            ];
        }
        $detailHtml = $renderTable(
            ['Section', 'Absent Students'],
            $sectionRows,
            'No section-wise student absentees found.',
            ['Total', array_sum($sectionCounts)]
        );
    } else {
        $studentListRows = [];
        foreach ($selectedStudentRows as $row) {
            if ((int)($row['student_year'] ?? 0) !== $selectedStudentYear) {
                continue;
            }
            if (strtoupper(trim((string)($row['student_section'] ?? ''))) !== $selectedStudentSection) {
                continue;
            }
            $studentListRows[] = [
                [
                    'html' => '<a href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">' . htmlspecialchars($row['name']) . '</a>',
                ],
                (string)($row['regd_no'] ?? ''),
                htmlspecialchars(getLeaveTypeLabel($row['leave_type'] ?? '')),
                [
                    'html' => '<a class="btn btn-secondary btn-sm" href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">History</a>',
                ],
            ];
        }
        $detailHtml = $renderTable(
            ['Name', 'Regd No', 'Active Leave', 'History'],
            $studentListRows,
            'No students found in this section.',
            ['Total', count($studentListRows), '', '']
        );
    }
} elseif ($cardView === 'admin') {
    $adminRows = [];
    foreach ($adminAbsentees as $row) {
        $quota = (int)($row['casual_leave_quota'] ?? 15);
        if ($quota <= 0) {
            $quota = 15;
        }
        $casualUsed = (int)($row['casual_used'] ?? 0);
        $adminRows[] = [
            [
                'html' => '<a href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">' . htmlspecialchars($row['name']) . '</a>',
            ],
            $row['designation'] ?: 'Admin',
            max(0, $quota - $casualUsed) . ' / ' . $quota,
            [
                'html' => '<a class="btn btn-secondary btn-sm" href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">History</a>',
            ],
        ];
    }
    $detailHtml = $renderTable(
        ['Name', 'Designation', 'Leaves Left / Total', 'History'],
        $adminRows,
        'No admin team absentees found today.',
        ['Total', count($adminRows), '', '']
    );
}

$hubCards = [
    [
        'kicker' => 'Today',
        'label' => 'Faculty',
        'value' => count($facultyAbsentees),
        'copy' => 'Open department-wise faculty absentees.',
        'class' => 'faculty',
        'active' => $cardView === 'faculty',
        'href' => '/leave_management_system/principal/focus.php?view=faculty',
    ],
    [
        'kicker' => 'Today',
        'label' => 'Students',
        'value' => count($studentAbsentees),
        'copy' => 'Step through department, year, and section.',
        'class' => 'student',
        'active' => $cardView === 'student',
        'href' => '/leave_management_system/principal/focus.php?view=student',
    ],
    [
        'kicker' => 'Today',
        'label' => 'Admin Team',
        'value' => count($adminAbsentees),
        'copy' => 'Open the direct admin absentee list.',
        'class' => 'admin',
        'active' => $cardView === 'admin',
        'href' => '/leave_management_system/principal/focus.php?view=admin',
    ],
];
?>
<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content principal-main">
        <div class="content-header principal-header" id="principal-overview">
            <div>
                <h2>Principal Hub</h2>
                <p class="page-subtitle">Faculty, students, and admin team.</p>
            </div>
            <div class="principal-meta">
                <span class="filter-chip">Today: <?php echo htmlspecialchars(date('d M Y')); ?></span>
                <span class="filter-chip">One panel at a time</span>
                <a class="btn btn-secondary btn-sm" href="/leave_management_system/principal/requests.php">HOD Leave Requests</a>
            </div>
        </div>

        <div class="card page-search-panel">
            <div class="card-header section-header">
                <div>
                    <h3>Quick People Search</h3>
                    <div class="section-note">Keep the search control at the top, where it is easy to reach.</div>
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
                        <option value="hod">HOD</option>
                        <option value="principal">Principals</option>
                        <option value="admin">Admins</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>
        </div>

        <section class="principal-hub-grid" id="hub-cards-section">
            <?php foreach ($hubCards as $card): ?>
                <?php
                $cardId = '';
                if (($card['label'] ?? '') === 'Faculty') {
                    $cardId = 'faculty-section';
                } elseif (($card['label'] ?? '') === 'Students') {
                    $cardId = 'student-section';
                } elseif (($card['label'] ?? '') === 'Admin Team') {
                    $cardId = 'admin-team-section';
                }
                ?>
                <div id="<?php echo htmlspecialchars($cardId); ?>">
                    <?php echo $renderHubCard($card); ?>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="card metric-matrix-card" id="matrix-overview">
            <div class="card-header section-header">
                <div>
                    <h3>Leave Matrix</h3>
                    <div class="section-note">A compact cross-tab of all leave requests for the current date, month, and department filters.</div>
                </div>
                <div class="section-actions">
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($matrixLink()); ?>">Open Requests</a>
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
                            <td>All Requests</td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($matrixLink()); ?>"><?php echo (int)$matrixData['total']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($matrixLink(['status' => 'pending'])); ?>"><?php echo (int)$matrixData['status_totals']['pending']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($matrixLink(['status' => 'approved'])); ?>"><?php echo (int)$matrixData['status_totals']['approved']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($matrixLink(['status' => 'rejected'])); ?>"><?php echo (int)$matrixData['status_totals']['rejected']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($matrixLink(['status' => 'open'])); ?>"><?php echo (int)$matrixData['status_totals']['open']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($matrixLink(['status' => 'closed'])); ?>"><?php echo (int)$matrixData['status_totals']['closed']; ?></a></td>
                        </tr>
                        <?php foreach ($matrixData['type_codes'] as $typeCode): ?>
                        <tr>
                            <td><a class="proof-link" href="<?php echo htmlspecialchars($matrixLink(['leave_type' => $typeCode])); ?>"><?php echo htmlspecialchars(getLeaveTypeLabel($typeCode)); ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($matrixLink(['leave_type' => $typeCode])); ?>"><?php echo (int)$matrixData['type_totals'][$typeCode]; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($matrixLink(['leave_type' => $typeCode, 'status' => 'pending'])); ?>"><?php echo (int)$matrixData['matrix'][$typeCode]['pending']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($matrixLink(['leave_type' => $typeCode, 'status' => 'approved'])); ?>"><?php echo (int)$matrixData['matrix'][$typeCode]['approved']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($matrixLink(['leave_type' => $typeCode, 'status' => 'rejected'])); ?>"><?php echo (int)$matrixData['matrix'][$typeCode]['rejected']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($matrixLink(['leave_type' => $typeCode, 'status' => 'open'])); ?>"><?php echo (int)$matrixData['matrix'][$typeCode]['open']; ?></a></td>
                            <td><a class="matrix-link" href="<?php echo htmlspecialchars($matrixLink(['leave_type' => $typeCode, 'status' => 'closed'])); ?>"><?php echo (int)$matrixData['matrix'][$typeCode]['closed']; ?></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card distribution-panel" id="leaves-section">
            <div class="card-header section-header">
                <div>
                    <h3>Leave Distribution</h3>
                    <div class="section-note">Pick a date, month, or department. Month overrides the date when both are selected.</div>
                </div>
                <div class="section-actions">
                    <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($distributionReportUrl); ?>" target="_blank" rel="noopener">Open Full Report</a>
                </div>
            </div>
            <form method="GET" action="/leave_management_system/principal/dashboard.php#leaves-section" class="filter-form report-filter-form">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($selectedView); ?>">
                <input type="hidden" name="faculty_department" value="<?php echo htmlspecialchars($selectedFacultyDepartment); ?>">
                <input type="hidden" name="student_department" value="<?php echo htmlspecialchars($selectedStudentDepartment); ?>">
                <input type="hidden" name="student_year" value="<?php echo $selectedStudentYear > 0 ? (int)$selectedStudentYear : ''; ?>">
                <input type="hidden" name="student_section" value="<?php echo htmlspecialchars($selectedStudentSection); ?>">
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
                <div class="form-group">
                    <label for="distribution_department">Department</label>
                    <select id="distribution_department" name="distribution_department" class="form-control">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $department): ?>
                        <option value="<?php echo htmlspecialchars($department['code']); ?>" <?php echo $distributionDepartment === $department['code'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($department['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a class="btn btn-secondary" href="<?php echo htmlspecialchars($buildUrl(['distribution_date' => '', 'distribution_month' => '', 'distribution_department' => ''])); ?>">Reset</a>
                </div>
            </form>
            <div class="filter-chip-row">
                <span class="filter-chip">Date: <?php echo $distributionDate !== '' ? htmlspecialchars(formatDate($distributionDate)) : 'All Dates'; ?></span>
                <span class="filter-chip">Month: <?php echo $distributionMonth > 0 ? htmlspecialchars(date('F', mktime(0, 0, 0, $distributionMonth, 1))) : 'All'; ?></span>
                <span class="filter-chip">Department: <?php echo htmlspecialchars($distributionDepartment === '' ? 'All Departments' : ($departmentLabels[$distributionDepartment] ?? strtoupper($distributionDepartment))); ?></span>
                <span class="filter-chip">Rows: <?php echo count($distributionRows); ?></span>
            </div>
            <?php echo $renderTable(['Type', 'Total', 'Pending', 'Approved', 'Rejected', 'Open', 'Closed'], $requestDistributionRows, 'No leave requests found for the selected distribution filters.'); ?>
        </section>

        <section class="card report-panel merged-report-panel">
            <div class="card-header section-header">
                <div>
                    <h3>Dashboard Explorer</h3>
                    <div class="section-note">Leave requests and absentees now live in one place so you can filter without bouncing between panels.</div>
                </div>
                <div class="section-actions explorer-switches">
                    <a class="btn btn-secondary btn-sm<?php echo $explorerMode === 'requests' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(['explorer_mode' => 'requests'])); ?>">Leave Requests</a>
                    <a class="btn btn-secondary btn-sm<?php echo $explorerMode === 'absentees' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(['explorer_mode' => 'absentees'])); ?>">Absentees By Date</a>
                </div>
            </div>

            <?php if ($explorerMode === 'requests'): ?>
            <div class="merged-report-block">
                <div class="card-header section-header">
                    <div>
                        <h3>Leave Requests Report</h3>
                        <div class="section-note">Filter by date, year, month, role, leave type, department, or student section.</div>
                    </div>
                </div>
                <form method="GET" action="/leave_management_system/principal/dashboard.php" class="filter-form report-filter-form">
                    <input type="hidden" name="explorer_mode" value="requests">
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($selectedView); ?>">
                    <input type="hidden" name="faculty_department" value="<?php echo htmlspecialchars($selectedFacultyDepartment); ?>">
                    <input type="hidden" name="student_department" value="<?php echo htmlspecialchars($selectedStudentDepartment); ?>">
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
                        <label for="report_department">Department</label>
                        <select id="report_department" name="report_department" class="form-control">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $department): ?>
                            <option value="<?php echo htmlspecialchars($department['code']); ?>" <?php echo $requestReportDepartment === $department['code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($department['label']); ?>
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
                        <a class="btn btn-secondary" href="<?php echo htmlspecialchars($buildUrl(['explorer_mode' => 'requests', 'report_date' => '', 'report_year' => '', 'report_month' => '', 'report_role' => '', 'report_leave_type' => '', 'report_department' => '', 'report_student_year' => '', 'report_student_section' => ''])); ?>">Reset</a>
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
                <?php echo $renderTable(
                    ['Type', 'Total', 'Pending', 'Approved', 'Rejected', 'Open', 'Closed'],
                    $requestDistributionRows,
                    'No leave requests found for the selected date and filters.'
                ); ?>
                <?php echo $renderTable(
                    ['Applicant', 'Role', 'Department', 'Type', 'From', 'To', 'Status', 'Proof', 'Action'],
                    $leaveRequestTableRows,
                    'No leave requests match the selected filters.'
                ); ?>
            </div>

            </div>
            <?php else: ?>
            <div class="merged-report-block">
                <div class="card-header section-header">
                    <div>
                        <h3>Absentees By Date</h3>
                        <div class="section-note">Search a specific date and narrow the absentee list by role, leave type, or department.</div>
                    </div>
                </div>
                <form method="GET" action="/leave_management_system/principal/dashboard.php" class="filter-form report-filter-form">
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($selectedView); ?>">
                    <input type="hidden" name="faculty_department" value="<?php echo htmlspecialchars($selectedFacultyDepartment); ?>">
                    <input type="hidden" name="student_department" value="<?php echo htmlspecialchars($selectedStudentDepartment); ?>">
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
                    <div class="form-group">
                        <label for="absence_department">Department</label>
                        <select id="absence_department" name="absence_department" class="form-control">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $department): ?>
                            <option value="<?php echo htmlspecialchars($department['code']); ?>" <?php echo $absenceDepartment === $department['code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($department['label']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Search Absentees</button>
                        <a class="btn btn-secondary" href="<?php echo htmlspecialchars($buildUrl(['absence_date' => '', 'absence_role' => '', 'absence_leave_type' => '', 'absence_department' => ''])); ?>">Reset</a>
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
            </div>
            <?php endif; ?>
        </section>
    </main>
</div>

</body>
</html>

<?php
require_once __DIR__ . '/../includes/header.php';

$currentRole = $_SESSION['role'] ?? '';
if (!in_array($currentRole, ['admin', 'principal', 'hod'], true)) {
    redirect('/leave_management_system/auth/login.php');
}

$page_title = 'People Search';
$searchTerm = trim($_GET['q'] ?? '');
$scope = strtolower(trim($_GET['scope'] ?? 'all'));
$hodDepartment = $_SESSION['department'] ?? '';
$searchPageUrl = '/leave_management_system/admin/people_search.php';

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

$renderTable = static function (array $headers, array $rows, string $emptyMessage) {
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
        </table>
    </div>
    <?php
    return ob_get_clean();
};

$scopeMap = [
    'all' => ['student', 'faculty'],
    'student' => ['student'],
    'faculty' => ['faculty'],
];

if ($currentRole === 'admin') {
    $scopeMap = [
        'all' => ['student', 'faculty', 'hod', 'principal', 'admin'],
        'student' => ['student'],
        'faculty' => ['faculty'],
        'principal' => ['principal'],
        'hod' => ['hod'],
        'admin' => ['admin'],
    ];
} elseif ($currentRole === 'principal') {
    $scopeMap = [
        'all' => ['student', 'faculty', 'hod', 'principal', 'admin'],
        'student' => ['student'],
        'faculty' => ['faculty'],
        'principal' => ['principal'],
        'hod' => ['hod'],
        'admin' => ['admin'],
    ];
} elseif ($currentRole === 'hod') {
    $scopeMap = [
        'all' => ['student', 'faculty'],
        'student' => ['student'],
        'faculty' => ['faculty'],
    ];
}

if (!array_key_exists($scope, $scopeMap)) {
    $scope = 'all';
}

$allowedRoles = $scopeMap[$scope];
$results = $searchTerm !== '' ? searchUsersByIdentifier($conn, $searchTerm, $allowedRoles, 25) : [];
$results = $currentRole === 'hod' && $hodDepartment !== ''
    ? array_values(array_filter($results, static function (array $row) use ($hodDepartment) {
        return ($row['department'] ?? '') === $hodDepartment;
    }))
    : $results;
$returnTo = $searchPageUrl . '?' . http_build_query(array_filter([
    'q' => $searchTerm !== '' ? $searchTerm : null,
    'scope' => $scope,
]));

$roleSummary = [
    'student' => 0,
    'faculty' => 0,
    'hod' => 0,
    'principal' => 0,
    'admin' => 0,
];
foreach ($results as $row) {
    if (isset($roleSummary[$row['role']])) {
        $roleSummary[$row['role']]++;
    }
}

$principalProfiles = [];
if ($currentRole === 'admin') {
    $principalProfiles = $fetchAll("SELECT * FROM users WHERE role = 'principal' ORDER BY name");
}
?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content principal-main">
        <div class="content-header principal-header">
            <div>
                <h2>People Search</h2>
                <p class="page-subtitle">Search by roll number, employee id, or name. HOD results stay within your department.</p>
            </div>
            <div class="principal-meta">
                <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars(getDashboardPathForRole($currentRole)); ?>">Back to Dashboard</a>
            </div>
        </div>

        <div class="card page-search-panel">
            <form method="GET" class="filter-form report-filter-form people-search-form page-search-form">
                <div class="form-group">
                    <label for="q">Search</label>
                    <input type="text" id="q" name="q" class="form-control" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Roll number or employee id">
                </div>
                <div class="form-group">
                    <label for="scope">Scope</label>
                    <select id="scope" name="scope" class="form-control">
                        <?php foreach (array_keys($scopeMap) as $scopeKey): ?>
                        <option value="<?php echo htmlspecialchars($scopeKey); ?>" <?php echo $scope === $scopeKey ? 'selected' : ''; ?>>
                            <?php
                            if ($scopeKey === 'all') {
                                echo in_array($currentRole, ['admin', 'principal'], true) ? 'All People' : 'Students + Faculty';
                            } else {
                                echo htmlspecialchars(ucfirst($scopeKey));
                            }
                            ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="<?php echo htmlspecialchars($searchPageUrl); ?>" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="stats-grid report-mini-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($results); ?></div>
                <div class="stat-label">Matches</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-number"><?php echo (int)$roleSummary['student']; ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-card approved">
                <div class="stat-number"><?php echo (int)$roleSummary['faculty']; ?></div>
                <div class="stat-label">Faculty</div>
            </div>
            <?php if ($currentRole === 'admin'): ?>
            <div class="stat-card open">
                <div class="stat-number"><?php echo (int)$roleSummary['principal']; ?></div>
                <div class="stat-label">Principals</div>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header section-header">
                <div>
                    <h3>Search Results</h3>
                    <div class="section-note">Click a profile to open the full leave history.</div>
                </div>
            </div>

            <?php if ($searchTerm === ''): ?>
                <div class="principal-empty-state">
                    <p>Type a roll number or employee id to begin.</p>
                </div>
            <?php else: ?>
                <?php
                $resultRows = [];
                foreach ($results as $row) {
                    $summary = getUserLeaveSummary($conn, (int)$row['id']);
                    $identifier = getIdentifierValue($row);
                    $quota = getCasualLeaveQuota($row);
                    $balance = max(0, $quota - (int)($summary['casual_used'] ?? 0));
                    $statusText = 'Pending ' . (int)($summary['pending_requests'] ?? 0)
                        . ' | Approved ' . (int)($summary['approved_requests'] ?? 0)
                        . ' | Rejected ' . (int)($summary['rejected_requests'] ?? 0)
                        . ' | Open ' . (int)($summary['open_requests'] ?? 0)
                        . ' | Closed ' . (int)($summary['closed_requests'] ?? 0);
                    if ($row['role'] === 'faculty') {
                        $statusText .= ' | CL Taken ' . getFacultyCasualLeaveDisplay($summary);
                    } elseif ($row['role'] !== 'student') {
                        $statusText .= ' | Casual ' . (int)($summary['casual_used'] ?? 0) . ' / ' . $quota
                            . ' | Left ' . $balance;
                    }
                    $resultRows[] = [
                        [
                            'html' => '<a class="proof-link" href="' . htmlspecialchars(buildUserHistoryUrl($row['id'], [], $returnTo)) . '">' . htmlspecialchars($row['name']) . '</a><br><small>' . htmlspecialchars(getUserMetaText($row)) . '</small>',
                        ],
                        getRoleLabel($row['role']),
                        $identifier !== '' ? $identifier : '-',
                        getDepartmentLabel($row['department'] ?? ''),
                        $statusText,
                        [
                            'html' => '<a class="btn btn-secondary btn-sm" href="' . htmlspecialchars(buildUserHistoryUrl($row['id'], [], $returnTo)) . '">Open Profile</a>',
                        ],
                    ];
                }
                    echo $renderTable(
                    ['Name', 'Role', 'Identifier', 'Department', 'Leave Status', 'Profile'],
                    $resultRows,
                    'No matching users found.'
                );
                ?>
            <?php endif; ?>
        </div>

        <?php if ($currentRole === 'admin'): ?>
        <div class="card">
            <div class="card-header section-header">
                <div>
                    <h3>Principal Profiles</h3>
                    <div class="section-note">Quick access to principal leave histories.</div>
                </div>
            </div>
            <?php
            $principalRows = [];
            foreach ($principalProfiles as $row) {
                $summary = getUserLeaveSummary($conn, (int)$row['id']);
                $quota = getCasualLeaveQuota($row);
                $principalRows[] = [
                    [
                        'html' => '<a class="proof-link" href="' . htmlspecialchars(buildUserHistoryUrl($row['id'], [], $returnTo)) . '">' . htmlspecialchars($row['name']) . '</a>',
                    ],
                    $row['emp_no'] ?? '-',
                    getDepartmentLabel($row['department'] ?? ''),
                    'Pending ' . (int)($summary['pending_requests'] ?? 0)
                        . ' | Approved ' . (int)($summary['approved_requests'] ?? 0)
                        . ' | Rejected ' . (int)($summary['rejected_requests'] ?? 0)
                        . ' | Open ' . (int)($summary['open_requests'] ?? 0)
                        . ' | Closed ' . (int)($summary['closed_requests'] ?? 0)
                        . ' | Casual ' . (int)($summary['casual_used'] ?? 0) . ' / ' . $quota,
                    [
                        'html' => '<a class="btn btn-secondary btn-sm" href="' . htmlspecialchars(buildUserHistoryUrl($row['id'], [], $returnTo)) . '">Profile</a>',
                    ],
                ];
            }
            echo $renderTable(
                ['Name', 'Employee Id', 'Department', 'Casual Status', 'Profile'],
                $principalRows,
                'No principal profiles found.'
            );
            ?>
        </div>
        <?php endif; ?>
    </main>
</div>

</body>
</html>

<?php
require_once __DIR__ . '/../includes/header.php';

checkRole('principal');

$page_title = 'Principal Focus';
$today = date('Y-m-d');
$departments = getDepartments($conn);
$departmentLabels = [];
foreach ($departments as $department) {
    $departmentLabels[$department['code']] = $department['label'];
}

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
                    <td><?php echo is_array($cell) && array_key_exists('html', $cell) ? $cell['html'] : htmlspecialchars((string)$cell); ?></td>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php
    return ob_get_clean();
};

$renderTileGrid = static function (array $tiles) {
    ob_start();
    ?>
    <div class="focus-grid">
        <?php foreach ($tiles as $tile): ?>
        <a class="focus-tile" href="<?php echo htmlspecialchars($tile['href'] ?? '#'); ?>">
            <div class="focus-tile-count"><?php echo htmlspecialchars((string)($tile['count'] ?? '')); ?></div>
            <div>
                <div class="focus-tile-title"><?php echo htmlspecialchars($tile['title'] ?? ''); ?></div>
                <div class="focus-tile-meta"><?php echo htmlspecialchars($tile['meta'] ?? ''); ?></div>
            </div>
            <?php if (!empty($tile['chips']) && is_array($tile['chips'])): ?>
            <div class="focus-quick-links">
                <?php foreach ($tile['chips'] as $chip): ?>
                <span class="focus-chip"><?php echo htmlspecialchars($chip); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <span class="focus-tile-arrow">Open</span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
};

$buildUrl = function (array $overrides = []) {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return '/leave_management_system/principal/focus.php' . (!empty($params) ? '?' . http_build_query($params) : '');
};

$dashboardUrl = '/leave_management_system/principal/dashboard.php';
$buildHistoryUrl = function ($userId, array $params = []) use ($dashboardUrl) {
    return buildUserHistoryUrl($userId, $params, $dashboardUrl);
};

$view = strtolower(trim($_GET['view'] ?? 'faculty'));
if (!in_array($view, ['faculty', 'student', 'admin'], true)) {
    $view = 'faculty';
}

$selectedFacultyDepartment = strtolower(trim($_GET['faculty_department'] ?? ''));
$selectedStudentDepartment = strtolower(trim($_GET['student_department'] ?? ''));
$selectedStudentYear = (int)($_GET['student_year'] ?? 0);
$selectedStudentSection = strtoupper(trim($_GET['student_section'] ?? ''));
$selectedStudentSearch = trim($_GET['student_search'] ?? '');

if ($selectedFacultyDepartment !== '' && !array_key_exists($selectedFacultyDepartment, $departmentLabels)) {
    $selectedFacultyDepartment = '';
}
if ($selectedStudentDepartment !== '' && !array_key_exists($selectedStudentDepartment, $departmentLabels)) {
    $selectedStudentDepartment = '';
}
if ($selectedStudentYear < 1 || $selectedStudentYear > 4) {
    $selectedStudentYear = 0;
}
if ($selectedStudentDepartment === '') {
    $selectedStudentYear = 0;
    $selectedStudentSection = '';
} elseif ($selectedStudentYear === 0) {
    $selectedStudentSection = '';
}

$filterStudentRow = static function (array $row) use ($selectedStudentDepartment, $selectedStudentYear, $selectedStudentSection, $selectedStudentSearch) {
    if ($selectedStudentDepartment === '') {
        return false;
    }
    if ($selectedStudentYear > 0 && (int)($row['student_year'] ?? 0) !== $selectedStudentYear) {
        return false;
    }
    if ($selectedStudentSection !== '' && strtoupper(trim((string)($row['student_section'] ?? ''))) !== $selectedStudentSection) {
        return false;
    }
    if ($selectedStudentSearch !== '') {
        $haystack = strtolower(trim(implode(' ', [
            (string)($row['name'] ?? ''),
            (string)($row['regd_no'] ?? ''),
            (string)($row['student_section'] ?? ''),
            (string)($row['student_year'] ?? ''),
        ])));
        if (strpos($haystack, strtolower($selectedStudentSearch)) === false) {
            return false;
        }
    }
    return true;
};

$facultyAbsentees = getAbsentUsersOnDate($conn, $today, 'faculty');
$studentAbsentees = getAbsentUsersOnDate($conn, $today, 'student');
$adminAbsentees = array_values(array_filter(getAbsentUsersOnDate($conn, $today, 'admin'), static function ($row) {
    return (int)($row['admin_team'] ?? 0) === 1;
}));

$groupedFacultyAbsentees = [];
foreach ($facultyAbsentees as $row) {
    $code = strtolower((string)($row['department'] ?? ''));
    if ($code !== '') {
        $groupedFacultyAbsentees[$code][] = $row;
    }
}

$groupedStudentAbsentees = [];
foreach ($studentAbsentees as $row) {
    $code = strtolower((string)($row['department'] ?? ''));
    if ($code !== '') {
        $groupedStudentAbsentees[$code][] = $row;
    }
}

$facultyRows = [];
foreach ($departments as $department) {
    $code = $department['code'];
    $facultyRows[] = [
        [
            'html' => '<a class="proof-link" href="' . htmlspecialchars($buildUrl(['view' => 'faculty', 'faculty_department' => $code, 'student_department' => '', 'student_year' => '', 'student_section' => ''])) . '">' . htmlspecialchars($department['label']) . '</a>',
        ],
        count($groupedFacultyAbsentees[$code] ?? []),
    ];
}

$studentDepartmentRows = [];
foreach ($departments as $department) {
    $code = $department['code'];
    $studentDepartmentRows[] = [
        [
            'html' => '<a class="proof-link" href="' . htmlspecialchars($buildUrl(['view' => 'student', 'student_department' => $code, 'student_year' => '', 'student_section' => '', 'faculty_department' => ''])) . '">' . htmlspecialchars($department['label']) . '</a>',
        ],
        count($groupedStudentAbsentees[$code] ?? []),
    ];
}

$studentYearCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
if ($selectedStudentDepartment !== '') {
    foreach (($groupedStudentAbsentees[$selectedStudentDepartment] ?? []) as $row) {
        $year = (int)($row['student_year'] ?? 0);
        if (isset($studentYearCounts[$year])) {
            $studentYearCounts[$year]++;
        }
    }
}

$studentSectionCounts = [];
if ($selectedStudentDepartment !== '' && $selectedStudentYear > 0) {
    foreach (($groupedStudentAbsentees[$selectedStudentDepartment] ?? []) as $row) {
        if ((int)($row['student_year'] ?? 0) !== $selectedStudentYear) {
            continue;
        }
        $section = strtoupper(trim((string)($row['student_section'] ?? '')));
        if ($section !== '') {
            $studentSectionCounts[$section] = ($studentSectionCounts[$section] ?? 0) + 1;
        }
    }
    ksort($studentSectionCounts);
}

$departmentStudentRows = [];
if ($selectedStudentDepartment !== '') {
    foreach (($groupedStudentAbsentees[$selectedStudentDepartment] ?? []) as $row) {
        if (!$filterStudentRow($row)) {
            continue;
        }
        $departmentStudentRows[] = $row;
    }
}

$currentTitle = 'Faculty Focus';
$currentSubtitle = 'Open department-wise faculty absentees without the long dashboard scroll.';
if ($view === 'student') {
    $currentTitle = 'Student Focus';
    $currentSubtitle = 'Department, year, section, then the student list.';
} elseif ($view === 'admin') {
    $currentTitle = 'Admin Focus';
    $currentSubtitle = 'Direct admin-team absentee list.';
}
?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content principal-main">
        <div class="content-header principal-header">
            <div>
                <h2><?php echo htmlspecialchars($currentTitle); ?></h2>
                <p class="page-subtitle"><?php echo htmlspecialchars($currentSubtitle); ?></p>
            </div>
            <div class="principal-meta">
                <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($dashboardUrl); ?>">Back to Dashboard</a>
            </div>
        </div>

        <div class="principal-crumbs">
            <span class="crumb"><?php echo htmlspecialchars($view === 'faculty' ? 'Faculty' : ($view === 'student' ? 'Students' : 'Admin Team')); ?></span>
            <?php if ($view === 'faculty' && $selectedFacultyDepartment !== ''): ?>
            <span class="crumb"><?php echo htmlspecialchars($departmentLabels[$selectedFacultyDepartment] ?? strtoupper($selectedFacultyDepartment)); ?></span>
            <?php endif; ?>
            <?php if ($view === 'student' && $selectedStudentDepartment !== ''): ?>
            <span class="crumb"><?php echo htmlspecialchars($departmentLabels[$selectedStudentDepartment] ?? strtoupper($selectedStudentDepartment)); ?></span>
            <?php endif; ?>
            <?php if ($view === 'student' && $selectedStudentYear > 0): ?>
            <span class="crumb">Year <?php echo (int)$selectedStudentYear; ?></span>
            <?php endif; ?>
            <?php if ($view === 'student' && $selectedStudentSection !== ''): ?>
            <span class="crumb">Section <?php echo htmlspecialchars($selectedStudentSection); ?></span>
            <?php endif; ?>
        </div>

        <section class="card principal-focus-card">
            <?php if ($view === 'faculty'): ?>
                <?php if ($selectedFacultyDepartment === ''): ?>
                    <?php
                    $tiles = [];
                    foreach ($departments as $department) {
                        $code = $department['code'];
                        $count = count($groupedFacultyAbsentees[$code] ?? []);
                        $tiles[] = [
                            'title' => $department['label'],
                            'count' => $count,
                            'meta' => 'Tap to open faculty absentees in this department.',
                            'href' => $buildUrl(['view' => 'faculty', 'faculty_department' => $code, 'student_department' => '', 'student_year' => '', 'student_section' => '']),
                        ];
                    }
                    echo $renderTileGrid($tiles);
                    ?>
                <?php else: ?>
                    <?php
                    $rows = [];
                    foreach (($groupedFacultyAbsentees[$selectedFacultyDepartment] ?? []) as $row) {
                        $rows[] = [
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
                    echo $renderTable(['Name', 'Designation', 'CL Taken / 15', 'History'], $rows, 'No faculty absentees found in this department.', ['Total', count($rows), '', '']);
                    ?>
                <?php endif; ?>
            <?php elseif ($view === 'student'): ?>
                <?php if ($selectedStudentDepartment === ''): ?>
                    <?php
                    $tiles = [];
                    foreach ($departments as $department) {
                        $code = $department['code'];
                        $count = count($groupedStudentAbsentees[$code] ?? []);
                        $tiles[] = [
                            'title' => $department['label'],
                            'count' => $count,
                            'meta' => 'Tap once to open the department snapshot.',
                            'chips' => ['Searchable view'],
                            'href' => $buildUrl(['view' => 'student', 'student_department' => $code, 'student_year' => '', 'student_section' => '', 'student_search' => '', 'faculty_department' => '']),
                        ];
                    }
                    echo $renderTileGrid($tiles);
                    ?>
                <?php else: ?>
                    <div class="focus-search-bar">
                        <form method="GET" action="<?php echo htmlspecialchars($buildUrl(['student_search' => ''])); ?>" class="focus-search-form">
                            <input type="hidden" name="view" value="student">
                            <input type="hidden" name="student_department" value="<?php echo htmlspecialchars($selectedStudentDepartment); ?>">
                            <input type="hidden" name="student_year" value="<?php echo $selectedStudentYear > 0 ? (int)$selectedStudentYear : ''; ?>">
                            <input type="hidden" name="student_section" value="<?php echo htmlspecialchars($selectedStudentSection); ?>">
                            <div class="form-group">
                                <label for="student_search">Search students</label>
                                <input type="text" id="student_search" name="student_search" class="form-control" value="<?php echo htmlspecialchars($selectedStudentSearch); ?>" placeholder="Name, regd no, section, or year">
                            </div>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <a class="btn btn-secondary" href="<?php echo htmlspecialchars($buildUrl(['view' => 'student', 'student_department' => $selectedStudentDepartment, 'student_year' => '', 'student_section' => '', 'student_search' => ''])); ?>">Clear</a>
                            </div>
                        </form>
                    </div>
                    <?php
                    $yearTiles = [];
                    foreach ($studentYearCounts as $year => $count) {
                        $yearTiles[] = [
                            'title' => 'Year ' . (int)$year,
                            'count' => $count,
                            'meta' => 'Open the students for this year.',
                            'chips' => ['1 click to narrow'],
                            'href' => $buildUrl(['view' => 'student', 'student_department' => $selectedStudentDepartment, 'student_year' => $year, 'student_section' => '', 'student_search' => '']),
                        ];
                    }
                    echo $renderTileGrid($yearTiles);

                    $sectionChips = [];
                    if ($selectedStudentYear > 0) {
                        foreach ($studentSectionCounts as $section => $count) {
                            $sectionChips[] = '<a class="focus-chip" href="' . htmlspecialchars($buildUrl(['view' => 'student', 'student_department' => $selectedStudentDepartment, 'student_year' => $selectedStudentYear, 'student_section' => $section, 'student_search' => $selectedStudentSearch])) . '">' . htmlspecialchars($section) . ' (' . (int)$count . ')</a>';
                        }
                    }
                    if (!empty($sectionChips)) {
                        echo '<div class="focus-quick-links">' . implode('', $sectionChips) . '</div>';
                    }

                    $rows = [];
                    foreach ($departmentStudentRows as $row) {
                        $rows[] = [
                            [
                                'html' => '<a class="proof-link" href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">' . htmlspecialchars($row['name']) . '</a><br><small>' . htmlspecialchars(getUserMetaText($row)) . '</small>',
                            ],
                            (string)($row['regd_no'] ?? ''),
                            (int)($row['student_year'] ?? 0) ?: '-',
                            strtoupper(trim((string)($row['student_section'] ?? ''))) ?: '-',
                            getLeaveTypeLabel($row['leave_type'] ?? ''),
                            [
                                'html' => '<a class="btn btn-secondary btn-sm" href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">History</a>',
                            ],
                        ];
                    }
                    echo $renderTable(
                        ['Name', 'Regd No', 'Year', 'Section', 'Active Leave', 'History'],
                        $rows,
                        $selectedStudentYear > 0 ? 'No students found for this year.' : 'No students found in this department today.',
                        ['Total', count($rows), '', '', '', '']
                    );
                    ?>
                <?php endif; ?>
            <?php else: ?>
                <?php
                $rows = [];
                foreach ($adminAbsentees as $row) {
                    $quota = (int)($row['casual_leave_quota'] ?? 15);
                    if ($quota <= 0) {
                        $quota = 15;
                    }
                    $used = (int)($row['casual_used'] ?? 0);
                    $rows[] = [
                        [
                            'html' => '<a class="proof-link" href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">' . htmlspecialchars($row['name']) . '</a>',
                        ],
                        $row['designation'] ?: 'Admin',
                        max(0, $quota - $used) . ' / ' . $quota,
                        [
                            'html' => '<a class="btn btn-secondary btn-sm" href="' . htmlspecialchars($buildHistoryUrl($row['id'])) . '">History</a>',
                        ],
                    ];
                }
                echo $renderTable(['Name', 'Designation', 'Leaves Left / Total', 'History'], $rows, 'No admin team absentees found today.', ['Total', count($rows), '', '']);
                ?>
            <?php endif; ?>
        </section>
    </main>
</div>

</body>
</html>

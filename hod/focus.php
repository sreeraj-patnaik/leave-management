<?php
require_once __DIR__ . '/../includes/header.php';

checkRole('hod');

$page_title = 'HOD Focus';
$hodDepartment = $_SESSION['department'] ?? '';
$departmentLabel = getDepartmentLabel($hodDepartment);
$today = date('Y-m-d');

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
    return '/leave_management_system/hod/focus.php' . (!empty($params) ? '?' . http_build_query($params) : '');
};

$dashboardUrl = '/leave_management_system/hod/dashboard.php';
$buildHistoryUrl = function ($userId, array $params = []) use ($dashboardUrl) {
    return buildUserHistoryUrl($userId, $params, $dashboardUrl);
};

$view = strtolower(trim($_GET['view'] ?? 'student'));
if (!in_array($view, ['student', 'faculty'], true)) {
    $view = 'student';
}

$selectedStudentYear = (int)($_GET['student_year'] ?? 0);
if ($selectedStudentYear < 1 || $selectedStudentYear > 4) {
    $selectedStudentYear = 0;
}
$selectedStudentSection = strtoupper(trim($_GET['student_section'] ?? ''));
if ($selectedStudentYear === 0) {
    $selectedStudentSection = '';
}
$selectedStudentSearch = trim($_GET['student_search'] ?? '');

$facultyAbsentees = getAbsentUsersOnDate($conn, $today, 'faculty', $hodDepartment);
$studentAbsentees = getAbsentUsersOnDate($conn, $today, 'student', $hodDepartment);

$studentYearCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
foreach ($studentAbsentees as $row) {
    $year = (int)($row['student_year'] ?? 0);
    if (isset($studentYearCounts[$year])) {
        $studentYearCounts[$year]++;
    }
}

$studentSectionCounts = [];
if ($selectedStudentYear > 0) {
    foreach ($studentAbsentees as $row) {
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

$studentRowsForTable = [];
foreach ($studentAbsentees as $row) {
    if ($selectedStudentYear > 0 && (int)($row['student_year'] ?? 0) !== $selectedStudentYear) {
        continue;
    }
    if ($selectedStudentSection !== '' && strtoupper(trim((string)($row['student_section'] ?? ''))) !== $selectedStudentSection) {
        continue;
    }
    if ($selectedStudentSearch !== '') {
        $haystack = strtolower(trim(implode(' ', [
            (string)($row['name'] ?? ''),
            (string)($row['regd_no'] ?? ''),
            (string)($row['student_section'] ?? ''),
            (string)($row['student_year'] ?? ''),
        ])));
        if (strpos($haystack, strtolower($selectedStudentSearch)) === false) {
            continue;
        }
    }
    $studentRowsForTable[] = $row;
}

$pageTitle = $view === 'faculty' ? 'Faculty Focus' : 'Student Focus';
$pageSubtitle = $view === 'faculty'
    ? 'Open the faculty absentee list for your department.'
    : 'Open the student absentee drill-down for your department.';
?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content principal-main">
        <div class="content-header principal-header">
            <div>
                <h2><?php echo htmlspecialchars($pageTitle); ?></h2>
                <p class="page-subtitle"><?php echo htmlspecialchars($pageSubtitle); ?></p>
            </div>
            <div class="principal-meta">
                <span class="filter-chip"><?php echo htmlspecialchars($departmentLabel); ?></span>
                <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($dashboardUrl); ?>">Back to Dashboard</a>
            </div>
        </div>

        <div class="principal-crumbs">
            <span class="crumb"><?php echo htmlspecialchars($departmentLabel); ?></span>
            <span class="crumb"><?php echo htmlspecialchars($view === 'faculty' ? 'Faculty' : 'Students'); ?></span>
            <?php if ($view === 'student' && $selectedStudentYear > 0): ?>
            <span class="crumb">Year <?php echo (int)$selectedStudentYear; ?></span>
            <?php endif; ?>
            <?php if ($view === 'student' && $selectedStudentSection !== ''): ?>
            <span class="crumb">Section <?php echo htmlspecialchars($selectedStudentSection); ?></span>
            <?php endif; ?>
        </div>

        <section class="card principal-focus-card">
            <?php if ($view === 'faculty'): ?>
                <?php
                $rows = [];
                foreach ($facultyAbsentees as $row) {
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
                echo $renderTable(['Name', 'Designation', 'CL Taken / 15', 'History'], $rows, 'No faculty absentees found today.', ['Total', count($rows), '', '']);
                ?>
            <?php elseif ($selectedStudentYear === 0): ?>
                <div class="focus-search-bar">
                    <form method="GET" action="<?php echo htmlspecialchars($buildUrl(['student_search' => ''])); ?>" class="focus-search-form">
                        <input type="hidden" name="view" value="student">
                        <div class="form-group">
                            <label for="student_search">Search students</label>
                            <input type="text" id="student_search" name="student_search" class="form-control" value="<?php echo htmlspecialchars($selectedStudentSearch); ?>" placeholder="Name, regd no, section, or year">
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a class="btn btn-secondary" href="<?php echo htmlspecialchars($buildUrl(['view' => 'student', 'student_year' => '', 'student_section' => '', 'student_search' => ''])); ?>">Clear</a>
                        </div>
                    </form>
                </div>
                <?php
                $tiles = [];
                foreach ($studentYearCounts as $year => $count) {
                    $tiles[] = [
                        'title' => 'Year ' . (int)$year,
                        'count' => $count,
                        'meta' => 'Open students for this year.',
                        'chips' => ['1 click to narrow'],
                        'href' => $buildUrl(['view' => 'student', 'student_year' => $year, 'student_section' => '', 'student_search' => '']),
                    ];
                }
                echo $renderTileGrid($tiles);

                $rows = [];
                foreach ($studentRowsForTable as $row) {
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
                    'No students found for the selected department today.',
                    ['Total', count($rows), '', '', '', '']
                );
                ?>
            <?php else: ?>
                <div class="focus-search-bar">
                    <form method="GET" action="<?php echo htmlspecialchars($buildUrl(['student_search' => ''])); ?>" class="focus-search-form">
                        <input type="hidden" name="view" value="student">
                        <input type="hidden" name="student_year" value="<?php echo (int)$selectedStudentYear; ?>">
                        <input type="hidden" name="student_section" value="<?php echo htmlspecialchars($selectedStudentSection); ?>">
                        <div class="form-group">
                            <label for="student_search">Search students</label>
                            <input type="text" id="student_search" name="student_search" class="form-control" value="<?php echo htmlspecialchars($selectedStudentSearch); ?>" placeholder="Name, regd no, section, or year">
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a class="btn btn-secondary" href="<?php echo htmlspecialchars($buildUrl(['view' => 'student', 'student_year' => $selectedStudentYear, 'student_section' => '', 'student_search' => ''])); ?>">Clear</a>
                        </div>
                    </form>
                </div>
                <?php
                $sectionChips = [];
                foreach ($studentSectionCounts as $section => $count) {
                    $sectionChips[] = '<a class="focus-chip" href="' . htmlspecialchars($buildUrl(['view' => 'student', 'student_year' => $selectedStudentYear, 'student_section' => $section, 'student_search' => $selectedStudentSearch])) . '">' . htmlspecialchars($section) . ' (' . (int)$count . ')</a>';
                }
                if (!empty($sectionChips)) {
                    echo '<div class="focus-quick-links">' . implode('', $sectionChips) . '</div>';
                }

                $rows = [];
                foreach ($studentRowsForTable as $row) {
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
                    'No students found for this year.',
                    ['Total', count($rows), '', '', '', '']
                );
                ?>
            <?php endif; ?>
        </section>
    </main>
</div>

</body>
</html>

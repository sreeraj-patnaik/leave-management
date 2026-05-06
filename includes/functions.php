<?php
// Helper Functions

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check user role
 */
function checkRole($required_role) {
    if (!isLoggedIn()) {
        redirect('/leave_management_system/auth/login.php');
    }
    if ($_SESSION['role'] !== $required_role) {
        redirect('/leave_management_system/auth/login.php');
    }
}

/**
 * Set flash message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Sanitize input
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'approved':
            return 'badge-success';
        case 'rejected':
            return 'badge-danger';
        case 'pending':
            return 'badge-warning';
        case 'open':
            return 'badge-info';
        case 'closed':
            return 'badge-secondary';
        default:
            return 'badge-secondary';
    }
}

/**
 * Format date
 */
function formatDate($date) {
    return date('d M Y', strtotime($date));
}

/**
 * Get user by ID
 */
function getUserById($conn, $id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

/**
 * Search users by roll number, employee id, or name.
 */
function searchUsersByIdentifier($conn, $term, array $roles = [], $limit = 25) {
    $term = trim((string)$term);
    if ($term === '') {
        return [];
    }

    $allowedRoles = ['student', 'faculty', 'hod', 'principal', 'admin'];
    $roles = array_values(array_intersect($roles, $allowedRoles));
    $likeTerm = '%' . $term . '%';

    $sql = "SELECT u.*
            FROM users u
            WHERE (u.regd_no LIKE ? OR u.emp_no LIKE ? OR u.name LIKE ?)";
    $types = 'sss';
    $params = [$likeTerm, $likeTerm, $likeTerm];

    if (!empty($roles)) {
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $sql .= " AND u.role IN ($placeholders)";
        $types .= str_repeat('s', count($roles));
        foreach ($roles as $role) {
            $params[] = $role;
        }
    }

    $sql .= " ORDER BY
                CASE
                    WHEN u.regd_no = ? OR u.emp_no = ? THEN 0
                    WHEN u.regd_no LIKE ? OR u.emp_no LIKE ? THEN 1
                    ELSE 2
                END,
                u.name
              LIMIT ?";
    $types .= 'ssssi';
    array_push($params, $term, $term, $likeTerm, $likeTerm, (int)$limit);

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }

    $bind = [$types];
    foreach ($params as $index => $value) {
        $bind[$index + 1] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Get all departments
 */
function getDepartments($conn) {
    return [
        ['code' => 'cse', 'label' => 'CSE'],
        ['code' => 'ece', 'label' => 'ECE'],
        ['code' => 'eee', 'label' => 'EEE'],
        ['code' => 'mec', 'label' => 'MEC'],
        ['code' => 'css', 'label' => 'CSS'],
        ['code' => 'cit', 'label' => 'CIT'],
        ['code' => 'csm', 'label' => 'CSM'],
    ];
}

/**
 * Get department label from code
 */
function getDepartmentLabel($code) {
    foreach (getDepartments(null) as $department) {
        if ($department['code'] === $code) {
            return $department['label'];
        }
    }

    return ucfirst($code);
}

/**
 * Get user identifier label by role
 */
function getIdentifierLabel($role) {
    return $role === 'faculty' ? 'Employee No' : 'Registration No';
}

/**
 * Get the active identifier value from a user row or session row
 */
function getIdentifierValue($user) {
    if (!empty($user['regd_no'])) {
        return $user['regd_no'];
    }

    if (!empty($user['emp_no'])) {
        return $user['emp_no'];
    }

    if (!empty($user['roll_number'])) {
        return $user['roll_number'];
    }

    return '';
}

/**
 * Get the dashboard path for a role.
 */
function getDashboardPathForRole($role) {
    switch ($role) {
        case 'student':
            return '/leave_management_system/student/dashboard.php';
        case 'faculty':
            return '/leave_management_system/faculty/dashboard.php';
        case 'hod':
            return '/leave_management_system/hod/dashboard.php';
        case 'principal':
            return '/leave_management_system/principal/dashboard.php';
        case 'admin':
        default:
            return '/leave_management_system/admin/dashboard.php';
    }
}

/**
 * Get a readable label for a role.
 */
function getRoleLabel($role) {
    switch ($role) {
        case 'hod':
            return 'HOD';
        case 'admin':
            return 'Admin';
        case 'principal':
            return 'Principal';
        case 'faculty':
            return 'Faculty';
        case 'student':
            return 'Student';
        default:
            return ucfirst((string)$role);
    }
}

/**
 * Get user meta text for profile cards and tables.
 */
function getUserMetaText($user) {
    $parts = [];
    $role = strtolower(trim($user['role'] ?? ''));

    if ($role === 'student') {
        if (!empty($user['student_year'])) {
            $parts[] = 'Year ' . (int)$user['student_year'];
        }
        if (!empty($user['student_section'])) {
            $parts[] = 'Section ' . strtoupper(trim($user['student_section']));
        }
    }

    if (!empty($user['designation'])) {
        $parts[] = $user['designation'];
    } elseif (in_array($role, ['faculty', 'hod', 'principal'], true)) {
        $parts[] = getRoleLabel($role);
    }

    if (!empty($user['admin_team'])) {
        $parts[] = 'Admin Team';
    }

    if (empty($parts)) {
        $parts[] = getRoleLabel($role);
    }

    return implode(' | ', $parts);
}

/**
 * Get a compact HTML snippet for user meta.
 */
function getUserMetaHtml($user) {
    return htmlspecialchars(getUserMetaText($user));
}

/**
 * Get the casual leave quota for a user row.
 */
function getCasualLeaveQuota($user) {
    $quota = (int)($user['casual_leave_quota'] ?? 15);
    return $quota > 0 ? $quota : 15;
}

/**
 * Build the standard faculty casual leave display.
 */
function getFacultyCasualLeaveDisplay($user) {
    $taken = (int)($user['casual_used'] ?? 0);
    return $taken . ' / 15';
}

/**
 * Get the reviewer label for a leave request based on the applicant role.
 */
function getLeaveReviewerLabel($role) {
    $role = strtolower(trim((string)$role));
    return $role === 'hod' ? 'Principal' : 'HOD';
}

/**
 * Build a display label for leave remarks.
 */
function getLeaveRemarksLabel($role) {
    return getLeaveReviewerLabel($role) . ' Remarks';
}

/**
 * Build the review URL for a leave request.
 */
function buildLeaveReviewUrl(array $leave, $returnTo = null) {
    $role = strtolower(trim((string)($leave['role'] ?? '')));
    return buildLeaveReviewUrlForRole((int)($leave['id'] ?? 0), $role, $returnTo);
}

/**
 * Build the review URL for a leave request when the role is already known.
 */
function buildLeaveReviewUrlForRole($leaveId, $role, $returnTo = null) {
    $role = strtolower(trim((string)$role));
    $path = $role === 'hod'
        ? '/leave_management_system/principal/process_leave.php'
        : '/leave_management_system/hod/process_leave.php';
    $query = ['id' => (int)$leaveId];
    if ($returnTo !== null && $returnTo !== '') {
        $query['return_to'] = $returnTo;
    }
    return $path . (!empty($query) ? '?' . http_build_query($query) : '');
}

/**
 * Check whether a row belongs to a faculty user.
 */
function isFacultyUser($user) {
    return strtolower(trim($user['role'] ?? '')) === 'faculty';
}

/**
 * Get a display label for a leave type.
 */
function getLeaveTypeLabel($type) {
    switch ($type) {
        case 'casual':
            return 'Casual';
        case 'medical':
            return 'Medical';
        case 'on_duty':
            return 'On Duty';
        case 'academic':
            return 'Academic';
        case 'vacation':
            return 'Vacation';
        default:
            return ucfirst(str_replace('_', ' ', (string)$type));
    }
}

/**
 * Get a stable color class for a leave type.
 */
function getLeaveTypeColorClass($type) {
    switch ($type) {
        case 'casual':
            return 'type-casual';
        case 'medical':
            return 'type-medical';
        case 'on_duty':
            return 'type-on-duty';
        case 'academic':
            return 'type-academic';
        case 'vacation':
            return 'type-vacation';
        default:
            return 'type-default';
    }
}

/**
 * Get the supported leave status codes.
 */
function getLeaveStatusCodes() {
    return ['pending', 'approved', 'rejected', 'open', 'closed'];
}

/**
 * Get the supported leave type codes for a role.
 */
function getLeaveTypeCodes($role = null) {
    $role = strtolower(trim((string)$role));
    if ($role === 'student') {
        return ['casual', 'medical'];
    }

    return ['casual', 'medical', 'on_duty', 'academic', 'vacation'];
}

/**
 * Get leave type option rows for a role.
 */
function getLeaveTypeOptionsForRole($role = null) {
    $options = [];
    foreach (getLeaveTypeCodes($role) as $code) {
        $options[] = [
            'code' => $code,
            'label' => getLeaveTypeLabel($code),
        ];
    }

    return $options;
}

/**
 * Build a leave request distribution matrix from leave rows.
 */
function buildLeaveRequestMatrix(array $rows, $role = null) {
    $statusCodes = getLeaveStatusCodes();
    $typeCodes = getLeaveTypeCodes($role);

    $statusTotals = array_fill_keys($statusCodes, 0);
    $typeTotals = array_fill_keys($typeCodes, 0);
    $matrix = [];

    foreach ($typeCodes as $typeCode) {
        $matrix[$typeCode] = array_fill_keys($statusCodes, 0);
    }

    foreach ($rows as $row) {
        $status = strtolower(trim((string)($row['status'] ?? '')));
        $type = strtolower(trim((string)($row['leave_type'] ?? '')));

        if (isset($statusTotals[$status])) {
            $statusTotals[$status]++;
        }

        if (isset($typeTotals[$type])) {
            $typeTotals[$type]++;
            if (isset($matrix[$type][$status])) {
                $matrix[$type][$status]++;
            }
        }
    }

    return [
        'total' => count($rows),
        'status_codes' => $statusCodes,
        'type_codes' => $typeCodes,
        'status_totals' => $statusTotals,
        'type_totals' => $typeTotals,
        'matrix' => $matrix,
    ];
}

/**
 * Build a user history URL with optional filters.
 */
function buildUserHistoryUrl($userId, array $params = [], $returnTo = null) {
    $query = array_merge(['user_id' => (int)$userId], $params);
    if ($returnTo !== null && $returnTo !== '') {
        $query['return_to'] = $returnTo;
    }

    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }

    return '/leave_management_system/admin/user_history.php' . (!empty($query) ? '?' . http_build_query($query) : '');
}

/**
 * Get leave analytics for a single user.
 */
function getUserLeaveAnalytics($conn, $userId) {
    $summary = getUserLeaveSummary($conn, $userId);
    $isStudent = strtolower(trim((string)($summary['role'] ?? ''))) === 'student';
    $typeCounts = array_fill_keys(getLeaveTypeCodes($isStudent ? 'student' : null), 0);
    $statusCounts = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'open' => 0,
        'closed' => 0,
    ];
    $yearlyCounts = [];
    $monthlyCounts = [];
    $latestYear = (int)date('Y');

    $stmt = mysqli_prepare(
        $conn,
        "SELECT leave_type, COUNT(*) AS total
         FROM leave_requests
         WHERE user_id = ?
         GROUP BY leave_type"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $type = $row['leave_type'] ?? '';
            if (isset($typeCounts[$type])) {
                $typeCounts[$type] = (int)$row['total'];
            }
        }
        mysqli_stmt_close($stmt);
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT status, COUNT(*) AS total
         FROM leave_requests
         WHERE user_id = ?
         GROUP BY status"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $status = $row['status'] ?? '';
            if (isset($statusCounts[$status])) {
                $statusCounts[$status] = (int)$row['total'];
            }
        }
        mysqli_stmt_close($stmt);
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT YEAR(created_at) AS report_year, COUNT(*) AS total
         FROM leave_requests
         WHERE user_id = ?
         GROUP BY YEAR(created_at)
         ORDER BY report_year DESC"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $year = (int)($row['report_year'] ?? 0);
            if ($year > 0) {
                $yearlyCounts[$year] = (int)$row['total'];
                if ($latestYear === (int)date('Y')) {
                    $latestYear = $year;
                }
            }
        }
        mysqli_stmt_close($stmt);
    }

    if (!empty($yearlyCounts)) {
        $latestYear = array_key_first($yearlyCounts);
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT MONTH(created_at) AS report_month, COUNT(*) AS total
         FROM leave_requests
         WHERE user_id = ? AND YEAR(created_at) = ?
         GROUP BY MONTH(created_at)
         ORDER BY report_month"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $userId, $latestYear);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $month = (int)($row['report_month'] ?? 0);
            if ($month >= 1 && $month <= 12) {
                $monthlyCounts[$month] = (int)$row['total'];
            }
        }
        mysqli_stmt_close($stmt);
    }

    $monthlySeries = [];
    for ($month = 1; $month <= 12; $month++) {
        $monthlySeries[] = [
            'month' => $month,
            'label' => date('M', mktime(0, 0, 0, $month, 1)),
            'count' => (int)($monthlyCounts[$month] ?? 0),
        ];
    }

    $typeSeries = [];
    foreach ($typeCounts as $type => $count) {
        $typeSeries[] = [
            'type' => $type,
            'label' => getLeaveTypeLabel($type),
            'count' => (int)$count,
            'class' => getLeaveTypeColorClass($type),
        ];
    }

    return [
        'summary' => $summary,
        'type_counts' => $typeSeries,
        'status_counts' => $statusCounts,
        'yearly_counts' => $yearlyCounts,
        'monthly_counts' => $monthlySeries,
        'active_year' => $latestYear,
    ];
}

/**
 * Check whether the current session must change password.
 */
function mustChangePassword() {
    return !empty($_SESSION['must_change_password']);
}

/**
 * Password strength validator.
 */
function validatePasswordStrength($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must include at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must include at least one lowercase letter.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must include at least one number.';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must include at least one special character.';
    }

    return $errors;
}

/**
 * Supported import departments
 */
function getDepartmentCodes() {
    return ['cse', 'ece', 'eee', 'mec', 'css', 'cit', 'csm'];
}

/**
 * Normalize a CSV/XLSX header value
 */
function normalizeHeader($header) {
    $header = strtolower(trim((string)$header));
    $header = preg_replace('/[^a-z0-9]+/', '_', $header);
    return trim($header, '_');
}

/**
 * Convert Excel column letters to a 1-based index.
 */
function columnLetterToIndex($letters) {
    $letters = strtoupper($letters);
    $index = 0;
    $length = strlen($letters);

    for ($i = 0; $i < $length; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }

    return $index;
}

/**
 * Read an uploaded CSV or XLSX file into row arrays.
 */
function readUserImportFile($file) {
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($extension === 'csv') {
        return readUserImportCsv($file['tmp_name']);
    }

    if ($extension === 'xlsx') {
        return readUserImportXlsx($file['tmp_name']);
    }

    return [
        'ok' => false,
        'error' => 'Only CSV and XLSX files are supported.',
        'rows' => [],
    ];
}

/**
 * Read CSV import file.
 */
function readUserImportCsv($path) {
    $handle = fopen($path, 'r');
    if (!$handle) {
        return ['ok' => false, 'error' => 'Unable to read CSV file.', 'rows' => []];
    }

    $headers = [];
    $rows = [];
    $rowIndex = 0;

    while (($data = fgetcsv($handle)) !== false) {
        if ($rowIndex === 0) {
            $headers = array_map('normalizeHeader', $data);
            $rowIndex++;
            continue;
        }

        $row = [];
        foreach ($headers as $index => $header) {
            $row[$header] = isset($data[$index]) ? trim($data[$index]) : '';
        }
        $rows[] = $row;
        $rowIndex++;
    }

    fclose($handle);

    return ['ok' => true, 'error' => null, 'rows' => $rows];
}

/**
 * Read XLSX import file using native ZipArchive + XML parsing.
 */
function readUserImportXlsx($path) {
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'XLSX import requires ZipArchive support on this server.', 'rows' => []];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return ['ok' => false, 'error' => 'Unable to open XLSX file.', 'rows' => []];
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sharedDoc = new DOMDocument();
        if ($sharedDoc->loadXML($sharedXml)) {
            $sharedXPath = new DOMXPath($sharedDoc);
            $sharedXPath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($sharedXPath->query('//main:si') as $item) {
                $text = '';
                foreach ($sharedXPath->query('.//main:t', $item) as $textNode) {
                    $text .= $textNode->nodeValue;
                }
                $sharedStrings[] = trim($text);
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if ($sheetXml === false) {
        return ['ok' => false, 'error' => 'XLSX sheet data not found.', 'rows' => []];
    }

    $sheetDoc = new DOMDocument();
    if (!$sheetDoc->loadXML($sheetXml)) {
        return ['ok' => false, 'error' => 'Unable to parse XLSX file.', 'rows' => []];
    }

    $sheetXPath = new DOMXPath($sheetDoc);
    $sheetXPath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $rows = [];
    $headers = [];

    foreach ($sheetXPath->query('//main:sheetData/main:row') as $index => $xmlRow) {
        $rowValues = [];

        foreach ($sheetXPath->query('./main:c', $xmlRow) as $cell) {
            $cellRef = $cell->getAttribute('r');
            preg_match('/^[A-Z]+/', $cellRef, $matches);
            $column = $matches[0] ?? '';
            $columnIndex = $column ? columnLetterToIndex($column) : 0;

            $value = '';
            $type = $cell->getAttribute('t');
            if ($type === 's') {
                $sharedIndex = intval($sheetXPath->evaluate('string(main:v)', $cell));
                $value = $sharedStrings[$sharedIndex] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = trim($sheetXPath->evaluate('string(main:is/main:t)', $cell));
            } else {
                $value = trim($sheetXPath->evaluate('string(main:v)', $cell));
            }

            $rowValues[$columnIndex] = $value;
        }

        if ($index === 0) {
            ksort($rowValues);
            $headers = [];
            $maxHeaderIndex = empty($rowValues) ? 0 : max(array_keys($rowValues));
            for ($i = 1; $i <= $maxHeaderIndex; $i++) {
                $headers[] = normalizeHeader($rowValues[$i] ?? '');
            }
            continue;
        }

        ksort($rowValues);
        $orderedValues = [];
        $maxIndex = count($headers);
        for ($i = 1; $i <= $maxIndex; $i++) {
            $orderedValues[] = $rowValues[$i] ?? '';
        }
        $row = [];
        foreach ($headers as $colIndex => $header) {
            $row[$header] = isset($orderedValues[$colIndex]) ? trim($orderedValues[$colIndex]) : '';
        }

        $rows[] = $row;
    }

    return ['ok' => true, 'error' => null, 'rows' => $rows];
}

/**
 * Validate one imported user row.
 */
function validateImportedUserRow($row, $existingEmails, $existingRegdNos, $existingEmpNos) {
    $errors = [];

    $name = trim($row['name'] ?? '');
    $email = trim($row['email'] ?? '');
    $role = strtolower(trim($row['role'] ?? ''));
    $department = strtolower(trim($row['department'] ?? ''));
    $regd_no = trim($row['regd_no'] ?? '');
    $emp_no = trim($row['emp_no'] ?? '');
    $designation = trim($row['designation'] ?? '');
    $student_year = trim($row['student_year'] ?? '');
    $student_section = trim($row['student_section'] ?? '');
    $admin_team = trim($row['admin_team'] ?? '');
    $password = (string)($row['password'] ?? '');

    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    } elseif (isset($existingEmails[$email])) {
        $errors[] = 'Email already exists.';
    }

    if (!in_array($role, ['student', 'faculty', 'hod', 'principal', 'admin'], true)) {
        $errors[] = 'Role must be student, faculty, hod, principal, or admin.';
    }

    if (!in_array($department, getDepartmentCodes(), true)) {
        $errors[] = 'Department must be one of the allowed codes.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($role === 'student') {
        if ($regd_no === '') {
            $errors[] = 'Registration number is required for students.';
        } elseif (isset($existingRegdNos[$regd_no])) {
            $errors[] = 'Registration number already exists.';
        }

        if ($student_year === '' || !in_array((int)$student_year, [1, 2, 3, 4], true)) {
            $errors[] = 'Student year must be 1, 2, 3, or 4.';
        }

        if ($student_section === '') {
            $errors[] = 'Student section is required for students.';
        }
    }

    if (in_array($role, ['faculty', 'hod', 'principal', 'admin'], true)) {
        if ($emp_no === '') {
            $errors[] = 'Employee number is required for faculty, HOD, principal, and admin users.';
        } elseif (isset($existingEmpNos[$emp_no])) {
            $errors[] = 'Employee number already exists.';
        }
    }

    if ($role === 'faculty' && $designation === '') {
        $errors[] = 'Designation is required for faculty users.';
    }

    if ($admin_team !== '' && !in_array(strtolower($admin_team), ['0', '1', 'yes', 'no', 'true', 'false', 'on', 'off'], true)) {
        $errors[] = 'Admin team must be a checkbox-like value.';
    }

    $adminTeamValue = in_array(strtolower($admin_team), ['1', 'yes', 'true', 'on'], true) ? 1 : 0;

    return [
        'ok' => empty($errors),
        'errors' => $errors,
        'data' => [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'department' => $department,
            'regd_no' => $regd_no,
            'emp_no' => $emp_no,
            'designation' => $designation,
            'student_year' => $student_year !== '' ? (int)$student_year : null,
            'student_section' => $student_section,
            'admin_team' => $adminTeamValue,
            'password' => $password,
        ],
    ];
}

/**
 * Get faculty by department
 */
function getFacultyByDepartment($conn, $department) {
    $stmt = mysqli_prepare($conn, "SELECT id, name FROM users WHERE role = 'faculty' AND department = ? ORDER BY name");
    mysqli_stmt_bind_param($stmt, "s", $department);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/**
 * Determine whether a leave request is active on a given date.
 * Approved, open, and closed medical leaves count as absence windows.
 */
function getActiveLeaveCondition() {
    return "lr.status IN ('approved', 'open', 'closed')
            AND lr.from_date <= ?
            AND (lr.to_date IS NULL OR lr.to_date >= ?)";
}

/**
 * Get presence/absence stats for a department or whole college.
 */
function getPresenceStats($conn, $date, $department = null) {
    $department = $department !== null ? strtolower(trim($department)) : null;
    $dateEscaped = mysqli_real_escape_string($conn, $date);
    $departmentClause = '';
    if ($department !== null && $department !== '') {
        $departmentClause = " AND department = '" . mysqli_real_escape_string($conn, $department) . "'";
    }

    $totalStudentsResult = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM users WHERE role = 'student'{$departmentClause}"
    );
    $totalStudents = (int)(mysqli_fetch_assoc($totalStudentsResult)['total'] ?? 0);

    $totalStaffResult = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM users WHERE role IN ('faculty', 'hod'){$departmentClause}"
    );
    $totalStaff = (int)(mysqli_fetch_assoc($totalStaffResult)['total'] ?? 0);

    $studentAbsenceResult = mysqli_query(
        $conn,
        "SELECT COUNT(DISTINCT u.id) AS total
         FROM users u
         INNER JOIN leave_requests lr ON lr.user_id = u.id
         WHERE u.role = 'student'
           {$departmentClause}
           AND lr.status IN ('approved', 'open', 'closed')
           AND lr.from_date <= '{$dateEscaped}'
           AND (lr.to_date IS NULL OR lr.to_date >= '{$dateEscaped}')"
    );
    $studentAbsent = (int)(mysqli_fetch_assoc($studentAbsenceResult)['total'] ?? 0);

    $staffAbsenceResult = mysqli_query(
        $conn,
        "SELECT COUNT(DISTINCT u.id) AS total
         FROM users u
         INNER JOIN leave_requests lr ON lr.user_id = u.id
         WHERE u.role IN ('faculty', 'hod')
           {$departmentClause}
           AND lr.status IN ('approved', 'open', 'closed')
           AND lr.from_date <= '{$dateEscaped}'
           AND (lr.to_date IS NULL OR lr.to_date >= '{$dateEscaped}')"
    );
    $staffAbsent = (int)(mysqli_fetch_assoc($staffAbsenceResult)['total'] ?? 0);

    return [
        'total_students' => $totalStudents,
        'total_staff' => $totalStaff,
        'students_present' => max(0, $totalStudents - $studentAbsent),
        'students_absent' => $studentAbsent,
        'staff_present' => max(0, $totalStaff - $staffAbsent),
        'staff_absent' => $staffAbsent,
    ];
}

/**
 * Get presence stats for all departments.
 */
function getDepartmentPresenceStats($conn, $date) {
    $rows = [];

    foreach (getDepartments($conn) as $department) {
        $rows[] = array_merge(
            ['department' => $department['code'], 'label' => $department['label']],
            getPresenceStats($conn, $date, $department['code'])
        );
    }

    return $rows;
}

/**
 * Get users who are absent on a given date for a role, with their active leave details.
 */
function getAbsentUsersOnDate($conn, $date, $role, $department = null) {
    $role = strtolower(trim($role));
    $allowedRoles = ['student', 'faculty', 'hod', 'admin'];
    if (!in_array($role, $allowedRoles, true)) {
        return [];
    }

    $query = "SELECT u.id,
                     u.name,
                     u.role,
                     u.department,
                     u.regd_no,
                     u.emp_no,
                     u.designation,
                     u.student_year,
                     u.student_section,
                     u.admin_team,
                     u.casual_leave_quota,
                     lr.id AS leave_id,
                     lr.leave_type,
                     lr.from_date,
                     lr.to_date,
                     lr.expected_duration,
                     lr.status,
                     lr.created_at,
                     CASE
                         WHEN u.role = 'student' THEN 0
                         ELSE (
                             SELECT COUNT(*)
                             FROM leave_requests cl
                             WHERE cl.user_id = u.id
                               AND cl.leave_type = 'casual'
                               AND cl.status = 'approved'
                         )
                     END AS casual_used
              FROM users u
              INNER JOIN leave_requests lr ON lr.user_id = u.id
              WHERE u.role = ?
                AND lr.status IN ('approved', 'open', 'closed')
                AND lr.from_date <= ?
                AND (lr.to_date IS NULL OR lr.to_date >= ?)
                AND lr.id = (
                    SELECT lr2.id
                    FROM leave_requests lr2
                    WHERE lr2.user_id = u.id
                      AND lr2.status IN ('approved', 'open', 'closed')
                      AND lr2.from_date <= ?
                      AND (lr2.to_date IS NULL OR lr2.to_date >= ?)
                    ORDER BY lr2.created_at DESC, lr2.id DESC
                    LIMIT 1
                )";

    $types = 'sssss';
    $params = [$role, $date, $date, $date, $date];

    if ($department !== null && $department !== '') {
        $query .= " AND u.department = ?";
        $types .= 's';
        $params[] = strtolower(trim($department));
    }

    $query .= " ORDER BY u.name";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Get leave requests filtered by report controls.
 */
function getLeaveRequestsByFilters($conn, array $filters = []) {
    $year = (int)($filters['year'] ?? 0);
    $month = (int)($filters['month'] ?? 0);
    $date = trim((string)($filters['date'] ?? ''));
    $role = strtolower(trim((string)($filters['role'] ?? '')));
    $leaveType = strtolower(trim((string)($filters['leave_type'] ?? '')));
    $department = strtolower(trim((string)($filters['department'] ?? '')));
    $studentYear = (int)($filters['student_year'] ?? 0);
    $studentSection = strtoupper(trim((string)($filters['student_section'] ?? '')));

    $allowedRoles = ['student', 'faculty', 'hod', 'principal', 'admin'];
    $allowedLeaveTypes = getLeaveTypeCodes($role);

    if ($role === 'all') {
        $role = '';
    }
    if ($leaveType === 'all') {
        $leaveType = '';
    }

    if ($role !== '' && !in_array($role, $allowedRoles, true)) {
        $role = '';
    }
    if ($leaveType !== '' && !in_array($leaveType, $allowedLeaveTypes, true)) {
        $leaveType = '';
    }
    if ($year < 0) {
        $year = 0;
    }
    if ($month < 0 || $month > 12) {
        $month = 0;
    }
    if ($studentYear < 0 || $studentYear > 4) {
        $studentYear = 0;
    }
    if ($studentSection === 'ALL') {
        $studentSection = '';
    }

    $query = "SELECT lr.*,
                     u.name,
                     u.role,
                     u.department,
                     u.regd_no,
                     u.emp_no,
                     u.designation,
                     u.student_year,
                     u.student_section,
                     u.admin_team,
                     u.casual_leave_quota,
                     CASE
                         WHEN u.role IN ('faculty', 'hod', 'principal', 'admin') THEN (
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

    if ($date !== '') {
        $query .= " AND DATE(lr.created_at) = ?";
        $types .= 's';
        $params[] = $date;
    }
    if ($year > 0) {
        $query .= " AND YEAR(lr.created_at) = ?";
        $types .= 'i';
        $params[] = $year;
    }
    if ($month > 0) {
        $query .= " AND MONTH(lr.created_at) = ?";
        $types .= 'i';
        $params[] = $month;
    }
    if ($role !== '') {
        $query .= " AND u.role = ?";
        $types .= 's';
        $params[] = $role;
    }
    if ($leaveType !== '') {
        $query .= " AND lr.leave_type = ?";
        $types .= 's';
        $params[] = $leaveType;
    }
    if ($department !== '') {
        $query .= " AND u.department = ?";
        $types .= 's';
        $params[] = $department;
    }
    if ($studentYear > 0) {
        $query .= " AND u.student_year = ?";
        $types .= 'i';
        $params[] = $studentYear;
    }
    if ($studentSection !== '') {
        $query .= " AND UPPER(TRIM(u.student_section)) = ?";
        $types .= 's';
        $params[] = $studentSection;
    }

    $query .= " ORDER BY lr.created_at DESC, lr.id DESC";

    $stmt = mysqli_prepare($conn, $query);
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
}

/**
 * Summarize all leave requests for a single user.
 */
function getUserLeaveSummary($conn, $userId) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT u.role,
                u.casual_leave_quota,
                COUNT(lr.id) AS total_requests,
                COALESCE(SUM(status = 'pending'), 0) AS pending_requests,
                COALESCE(SUM(status = 'approved'), 0) AS approved_requests,
                COALESCE(SUM(status = 'rejected'), 0) AS rejected_requests,
                COALESCE(SUM(status = 'open'), 0) AS open_requests,
                COALESCE(SUM(status = 'closed'), 0) AS closed_requests,
                COALESCE(SUM(leave_type = 'casual' AND status = 'approved'), 0) AS casual_used
         FROM users u
         LEFT JOIN leave_requests lr ON lr.user_id = u.id
         WHERE u.id = ?
         GROUP BY u.id, u.casual_leave_quota"
    );
    if (!$stmt) {
        return [
            'casual_leave_quota' => 15,
            'total_requests' => 0,
            'pending_requests' => 0,
            'approved_requests' => 0,
            'rejected_requests' => 0,
            'open_requests' => 0,
            'closed_requests' => 0,
            'casual_used' => 0,
        ];
    }

    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result) ?: [];
    mysqli_stmt_close($stmt);
    $isStudent = strtolower(trim($row['role'] ?? '')) === 'student';

    return [
        'role' => $row['role'] ?? '',
        'casual_leave_quota' => $isStudent ? 0 : getCasualLeaveQuota($row),
        'total_requests' => (int)($row['total_requests'] ?? 0),
        'pending_requests' => (int)($row['pending_requests'] ?? 0),
        'approved_requests' => (int)($row['approved_requests'] ?? 0),
        'rejected_requests' => (int)($row['rejected_requests'] ?? 0),
        'open_requests' => (int)($row['open_requests'] ?? 0),
        'closed_requests' => (int)($row['closed_requests'] ?? 0),
        'casual_used' => $isStudent ? 0 : (int)($row['casual_used'] ?? 0),
    ];
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowed_types = ['image/jpeg', 'image/png', 'application/pdf']) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error.";
        return $errors;
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = "File size must be less than 5MB.";
    }
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        $errors[] = "Invalid file type. Allowed: JPG, PNG, PDF.";
    }
    
    return $errors;
}

/**
 * Upload file
 */
function uploadFile($file, $upload_dir = '../uploads/') {
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return false;
}
?>

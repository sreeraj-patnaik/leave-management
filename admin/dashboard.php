<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn() && mustChangePassword()) {
    redirect('/leave_management_system/auth/change_password.php');
}

checkRole('admin');

$page_title = 'Admin Dashboard';
$errors = [];
$results = null;
$xlsx_supported = class_exists('ZipArchive');

$departmentOptions = getDepartments($conn);
$departmentLabels = [];
foreach ($departmentOptions as $departmentOption) {
    $departmentLabels[$departmentOption['code']] = $departmentOption['label'];
}

$statusKeys = ['pending', 'approved', 'rejected', 'open', 'closed'];
$roleKeys = ['student', 'faculty', 'hod', 'principal', 'admin'];
$monthNames = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December',
];

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
    $rows = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
    return $rows;
};

$fetchOne = function ($sql, $types = '', $params = []) use ($fetchAll) {
    $rows = $fetchAll($sql, $types, $params);
    return $rows[0] ?? [];
};

$exportCsv = function ($filename, array $headers, array $rows) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
};

$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
$currentDate = date('Y-m-d');
$todayPresence = getPresenceStats($conn, $currentDate);
$todayDepartmentPresence = getDepartmentPresenceStats($conn, $currentDate);

$selectedYear = (int)($_GET['year'] ?? $currentYear);
if ($selectedYear < 2000) {
    $selectedYear = $currentYear;
}

$selectedMonth = (int)($_GET['month'] ?? $currentMonth);
if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = $currentMonth;
}

$selectedDate = $_GET['date'] ?? $currentDate;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = $currentDate;
}

$selectedDepartment = strtolower(trim($_GET['department'] ?? ''));
if ($selectedDepartment !== '' && !in_array($selectedDepartment, getDepartmentCodes(), true)) {
    $selectedDepartment = '';
}

$baseFilters = [
    'year' => $selectedYear,
    'month' => $selectedMonth,
    'date' => $selectedDate,
    'department' => $selectedDepartment,
];

$buildUrl = function (array $overrides = []) use ($baseFilters) {
    $params = array_merge($baseFilters, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    return '/leave_management_system/admin/dashboard.php' . (!empty($params) ? '?' . http_build_query($params) : '');
};

$buildAbsenteesUrl = function (string $role, array $overrides = []) use ($currentDate, $selectedDepartment) {
    $params = array_merge([
        'role' => $role,
        'date' => $currentDate,
        'department' => $selectedDepartment !== '' ? $selectedDepartment : null,
    ], $overrides);

    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    return '/leave_management_system/admin/absentees.php' . (!empty($params) ? '?' . http_build_query($params) : '');
};

$dashboardUrl = $buildUrl();
$buildRequestsUrl = function (array $overrides = []) use ($dashboardUrl) {
    $params = array_merge($overrides, ['return_to' => $dashboardUrl]);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    return '/leave_management_system/admin/requests.php' . (!empty($params) ? '?' . http_build_query($params) : '');
};

$buildExportRows = function ($type) use (
    $fetchAll,
    $fetchOne,
    $statusKeys,
    $roleKeys,
    $departmentOptions,
    $departmentLabels,
    $monthNames,
    $selectedYear,
    $selectedMonth,
    $selectedDate,
    $selectedDepartment,
    $currentYear,
    $currentMonth,
    $todayPresence,
    $todayDepartmentPresence
) {
    $summary = [
        'total_users' => (int)($fetchOne("SELECT COUNT(*) AS total FROM users")['total'] ?? 0),
        'total_requests' => (int)($fetchOne("SELECT COUNT(*) AS total FROM leave_requests")['total'] ?? 0),
        'status_counts' => array_fill_keys($statusKeys, 0),
        'role_counts' => array_fill_keys($roleKeys, 0),
    ];

    foreach ($fetchAll("SELECT role, COUNT(*) AS total FROM users GROUP BY role") as $row) {
        $summary['role_counts'][$row['role']] = (int)$row['total'];
    }

    foreach ($fetchAll("SELECT status, COUNT(*) AS total FROM leave_requests GROUP BY status") as $row) {
        if (isset($summary['status_counts'][$row['status']])) {
            $summary['status_counts'][$row['status']] = (int)$row['total'];
        }
    }

    if ($type === 'college') {
        return [
            'filename' => 'college_summary.csv',
            'headers' => ['Metric', 'Value'],
            'rows' => [
                ['Total Users', $summary['total_users']],
                ['Students', $summary['role_counts']['student']],
                ['Faculty', $summary['role_counts']['faculty']],
                ['HODs', $summary['role_counts']['hod']],
                ['Principals', $summary['role_counts']['principal']],
                ['Admins', $summary['role_counts']['admin']],
                ['Total Leave Requests', $summary['total_requests']],
                ['Pending Requests', $summary['status_counts']['pending']],
                ['Approved Requests', $summary['status_counts']['approved']],
                ['Rejected Requests', $summary['status_counts']['rejected']],
                ['Open Medical Requests', $summary['status_counts']['open']],
                ['Closed Medical Requests', $summary['status_counts']['closed']],
            ],
        ];
    }

    if ($type === 'presence_overall') {
        $presence = $todayPresence;
        return [
            'filename' => 'college_presence_today.csv',
            'headers' => ['Metric', 'Value'],
            'rows' => [
                ['Total Students', $presence['total_students']],
                ['Total Staff', $presence['total_staff']],
                ['Students Present Today', $presence['students_present']],
                ['Students Absent Today', $presence['students_absent']],
                ['Staff Present Today', $presence['staff_present']],
                ['Staff Absent Today', $presence['staff_absent']],
            ],
        ];
    }

    if ($type === 'presence_department') {
        $rows = [];
        foreach ($todayDepartmentPresence as $departmentRow) {
            $rows[] = [
                'Department' => $departmentRow['label'],
                'Total Students' => $departmentRow['total_students'],
                'Students Present Today' => $departmentRow['students_present'],
                'Students Absent Today' => $departmentRow['students_absent'],
                'Total Staff' => $departmentRow['total_staff'],
                'Staff Present Today' => $departmentRow['staff_present'],
                'Staff Absent Today' => $departmentRow['staff_absent'],
            ];
        }

        return [
            'filename' => 'department_presence_today.csv',
            'headers' => ['Department', 'Total Students', 'Students Present Today', 'Students Absent Today', 'Total Staff', 'Staff Present Today', 'Staff Absent Today'],
            'rows' => array_map(static function ($row) {
                return array_values($row);
            }, $rows),
        ];
    }

    if ($type === 'yearly') {
        $rows = [];
        foreach ($fetchAll(
            "SELECT YEAR(created_at) AS report_year,
                    COUNT(*) AS total_requests,
                    SUM(status = 'pending') AS pending_requests,
                    SUM(status = 'approved') AS approved_requests,
                    SUM(status = 'rejected') AS rejected_requests,
                    SUM(status = 'open') AS open_requests,
                    SUM(status = 'closed') AS closed_requests
             FROM leave_requests
             GROUP BY YEAR(created_at)
             ORDER BY report_year DESC"
        ) as $row) {
            $rows[] = [
                'Year' => $row['report_year'],
                'Total Requests' => (int)$row['total_requests'],
                'Pending' => (int)$row['pending_requests'],
                'Approved' => (int)$row['approved_requests'],
                'Rejected' => (int)$row['rejected_requests'],
                'Open' => (int)$row['open_requests'],
                'Closed' => (int)$row['closed_requests'],
            ];
        }

        return [
            'filename' => 'yearly_leave_report.csv',
            'headers' => ['Year', 'Total Requests', 'Pending', 'Approved', 'Rejected', 'Open', 'Closed'],
            'rows' => array_map(static function ($row) {
                return array_values($row);
            }, $rows),
        ];
    }

    if ($type === 'monthly') {
        $monthRows = [];
        foreach ($fetchAll(
            "SELECT MONTH(created_at) AS report_month,
                    COUNT(*) AS total_requests,
                    SUM(status = 'pending') AS pending_requests,
                    SUM(status = 'approved') AS approved_requests,
                    SUM(status = 'rejected') AS rejected_requests,
                    SUM(status = 'open') AS open_requests,
                    SUM(status = 'closed') AS closed_requests
             FROM leave_requests
             WHERE YEAR(created_at) = ?
             GROUP BY MONTH(created_at)
             ORDER BY report_month",
            'i',
            [$selectedYear]
        ) as $row) {
            $monthRows[(int)$row['report_month']] = $row;
        }

        $rows = [];
        for ($month = 1; $month <= 12; $month++) {
            $row = $monthRows[$month] ?? [
                'total_requests' => 0,
                'pending_requests' => 0,
                'approved_requests' => 0,
                'rejected_requests' => 0,
                'open_requests' => 0,
                'closed_requests' => 0,
            ];

            $rows[] = [
                'Year' => $selectedYear,
                'Month' => $monthNames[$month],
                'Total Requests' => (int)$row['total_requests'],
                'Pending' => (int)$row['pending_requests'],
                'Approved' => (int)$row['approved_requests'],
                'Rejected' => (int)$row['rejected_requests'],
                'Open' => (int)$row['open_requests'],
                'Closed' => (int)$row['closed_requests'],
            ];
        }

        return [
            'filename' => 'monthly_leave_report_' . $selectedYear . '.csv',
            'headers' => ['Year', 'Month', 'Total Requests', 'Pending', 'Approved', 'Rejected', 'Open', 'Closed'],
            'rows' => array_map(static function ($row) {
                return array_values($row);
            }, $rows),
        ];
    }

    if ($type === 'daily') {
        $dailyRows = [];
        foreach ($fetchAll(
            "SELECT DATE(created_at) AS report_date,
                    COUNT(*) AS total_requests,
                    SUM(status = 'pending') AS pending_requests,
                    SUM(status = 'approved') AS approved_requests,
                    SUM(status = 'rejected') AS rejected_requests,
                    SUM(status = 'open') AS open_requests,
                    SUM(status = 'closed') AS closed_requests
             FROM leave_requests
             WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?
             GROUP BY DATE(created_at)
             ORDER BY report_date",
            'ii',
            [$selectedYear, $selectedMonth]
        ) as $row) {
            $dailyRows[$row['report_date']] = $row;
        }

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
        $rows = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $day);
            $row = $dailyRows[$date] ?? [
                'total_requests' => 0,
                'pending_requests' => 0,
                'approved_requests' => 0,
                'rejected_requests' => 0,
                'open_requests' => 0,
                'closed_requests' => 0,
            ];

            $rows[] = [
                'Date' => $date,
                'Day' => $day,
                'Total Requests' => (int)$row['total_requests'],
                'Pending' => (int)$row['pending_requests'],
                'Approved' => (int)$row['approved_requests'],
                'Rejected' => (int)$row['rejected_requests'],
                'Open' => (int)$row['open_requests'],
                'Closed' => (int)$row['closed_requests'],
            ];
        }

        return [
            'filename' => 'daily_leave_report_' . $selectedYear . '_' . $selectedMonth . '.csv',
            'headers' => ['Date', 'Day', 'Total Requests', 'Pending', 'Approved', 'Rejected', 'Open', 'Closed'],
            'rows' => array_map(static function ($row) {
                return array_values($row);
            }, $rows),
        ];
    }

    if ($type === 'department') {
        $rowsByDepartment = [];
        foreach ($fetchAll(
            "SELECT u.department,
                    COUNT(DISTINCT u.id) AS total_users,
                    COUNT(DISTINCT IF(u.role = 'student', u.id, NULL)) AS students,
                    COUNT(DISTINCT IF(u.role = 'faculty', u.id, NULL)) AS faculty,
                    COUNT(DISTINCT IF(u.role = 'hod', u.id, NULL)) AS hod,
                    COUNT(DISTINCT IF(u.role = 'admin', u.id, NULL)) AS admins,
                    COUNT(lr.id) AS total_requests,
                    SUM(lr.status = 'pending') AS pending_requests,
                    SUM(lr.status = 'approved') AS approved_requests,
                    SUM(lr.status = 'rejected') AS rejected_requests,
                    SUM(lr.status = 'open') AS open_requests,
                    SUM(lr.status = 'closed') AS closed_requests
             FROM users u
             LEFT JOIN leave_requests lr ON lr.user_id = u.id
             GROUP BY u.department"
        ) as $row) {
            $rowsByDepartment[$row['department']] = $row;
        }

        $rows = [];
        foreach ($departmentOptions as $department) {
            $code = $department['code'];
            $row = $rowsByDepartment[$code] ?? [
                'total_users' => 0,
                'students' => 0,
                'faculty' => 0,
                'hod' => 0,
                'admins' => 0,
                'total_requests' => 0,
                'pending_requests' => 0,
                'approved_requests' => 0,
                'rejected_requests' => 0,
                'open_requests' => 0,
                'closed_requests' => 0,
            ];

            $rows[] = [
                'Department' => $departmentLabels[$code] ?? strtoupper($code),
                'Total Users' => (int)$row['total_users'],
                'Students' => (int)$row['students'],
                'Faculty' => (int)$row['faculty'],
                'HOD' => (int)$row['hod'],
                'Admins' => (int)$row['admins'],
                'Total Requests' => (int)$row['total_requests'],
                'Pending' => (int)$row['pending_requests'],
                'Approved' => (int)$row['approved_requests'],
                'Rejected' => (int)$row['rejected_requests'],
                'Open' => (int)$row['open_requests'],
                'Closed' => (int)$row['closed_requests'],
            ];
        }

        return [
            'filename' => 'department_leave_report.csv',
            'headers' => ['Department', 'Total Users', 'Students', 'Faculty', 'HOD', 'Admins', 'Total Requests', 'Pending', 'Approved', 'Rejected', 'Open', 'Closed'],
            'rows' => array_map(static function ($row) {
                return array_values($row);
            }, $rows),
        ];
    }

    if ($type === 'live') {
        $whereSql = "WHERE DATE(lr.created_at) = ?";
        $params = [$selectedDate];
        $types = 's';

        if ($selectedDepartment !== '') {
            $whereSql .= " AND u.department = ?";
            $params[] = $selectedDepartment;
            $types .= 's';
        }

        $rows = [];
        foreach ($fetchAll(
            "SELECT lr.created_at,
                    lr.leave_type,
                    lr.from_date,
                    lr.to_date,
                    lr.expected_duration,
                    lr.reason,
                    lr.proof,
                    lr.is_medical,
                    lr.status,
                    lr.hod_remarks,
                    u.name,
                    u.role,
                    u.department,
                    u.regd_no,
                    u.emp_no
             FROM leave_requests lr
             INNER JOIN users u ON u.id = lr.user_id
             $whereSql
             ORDER BY lr.created_at DESC",
            $types,
            $params
        ) as $row) {
            $rows[] = [
                'Requested At' => date('d M Y h:i A', strtotime($row['created_at'])),
                'Applicant' => $row['name'],
                'Role' => ucfirst($row['role']),
                'Department' => $departmentLabels[$row['department']] ?? strtoupper($row['department']),
                'Identifier' => !empty($row['regd_no']) ? $row['regd_no'] : ($row['emp_no'] ?? ''),
                'Leave Type' => ucfirst(str_replace('_', ' ', $row['leave_type'])),
                'From Date' => $row['from_date'],
                'To Date' => $row['to_date'] ?: '',
                'Expected Duration' => $row['expected_duration'] ?: '',
                'Status' => ucfirst($row['status']),
                'Proof' => $row['proof'] ?: '',
                'HOD Remarks' => $row['hod_remarks'] ?: '',
            ];
        }

        return [
            'filename' => 'live_day_report_' . $selectedDate . '.csv',
            'headers' => ['Requested At', 'Applicant', 'Role', 'Department', 'Identifier', 'Leave Type', 'From Date', 'To Date', 'Expected Duration', 'Status', 'Proof', 'HOD Remarks'],
            'rows' => array_map(static function ($row) {
                return array_values($row);
            }, $rows),
        ];
    }

    return null;
};

if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    $exportPayload = $buildExportRows($exportType);
    if ($exportPayload) {
        $exportCsv($exportPayload['filename'], $exportPayload['headers'], $exportPayload['rows']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please choose a CSV or XLSX file to upload.';
    } elseif ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed.';
    } else {
        $extension = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            $errors[] = 'Only CSV and XLSX files are allowed.';
        } elseif ($extension === 'xlsx' && !$xlsx_supported) {
            $errors[] = 'XLSX upload is unavailable on this server because ZipArchive is not enabled. Please enable the PHP zip extension or use CSV.';
        }
    }

    if (empty($errors)) {
        $import = readUserImportFile($_FILES['import_file']);
        if (!$import['ok']) {
            $errors[] = $import['error'];
        } else {
            $existingEmails = [];
            $existingRegdNos = [];
            $existingEmpNos = [];

            $userResult = mysqli_query($conn, "SELECT email, regd_no, emp_no, designation, student_year, student_section, admin_team FROM users");
            while ($userRow = mysqli_fetch_assoc($userResult)) {
                if (!empty($userRow['email'])) {
                    $existingEmails[$userRow['email']] = true;
                }
                if (!empty($userRow['regd_no'])) {
                    $existingRegdNos[$userRow['regd_no']] = true;
                }
                if (!empty($userRow['emp_no'])) {
                    $existingEmpNos[$userRow['emp_no']] = true;
                }
            }

            $summary = [
                'imported' => 0,
                'skipped' => 0,
                'failed_rows' => [],
            ];

            $stmtUser = mysqli_prepare(
                $conn,
                "INSERT INTO users (name, email, password, role, department, regd_no, emp_no, designation, student_year, student_section, admin_team, casual_leave_quota, must_change_password, password_changed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 15, 1, NULL)"
            );

            mysqli_begin_transaction($conn);

            try {
                foreach ($import['rows'] as $index => $row) {
                    $validation = validateImportedUserRow($row, $existingEmails, $existingRegdNos, $existingEmpNos);

                    if (!$validation['ok']) {
                        $summary['skipped']++;
                        $summary['failed_rows'][] = [
                            'row' => $index + 2,
                            'email' => trim($row['email'] ?? ''),
                            'errors' => $validation['errors'],
                        ];
                        continue;
                    }

                    $data = $validation['data'];
                    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                    $name = $data['name'];
                    $email = $data['email'];
                    $role = $data['role'];
                    $department = $data['department'];
                    $regdNo = $data['regd_no'];
                    $empNo = $data['emp_no'];
                    $designation = $data['designation'];
                    $studentYear = $data['student_year'];
                    $studentSection = $data['student_section'];
                    $adminTeam = (int)$data['admin_team'];
                    $studentYearValue = $studentYear !== null ? (string)$studentYear : null;

                    mysqli_stmt_bind_param(
                        $stmtUser,
                        'ssssssssssi',
                        $name,
                        $email,
                        $hashedPassword,
                        $role,
                        $department,
                        $regdNo,
                        $empNo,
                        $designation,
                        $studentYearValue,
                        $studentSection,
                        $adminTeam
                    );
                    $ok = mysqli_stmt_execute($stmtUser);

                    if ($ok) {
                        $summary['imported']++;
                        $existingEmails[$email] = true;
                        if (!empty($regdNo)) {
                            $existingRegdNos[$regdNo] = true;
                        }
                        if (!empty($empNo)) {
                            $existingEmpNos[$empNo] = true;
                        }
                    } else {
                        $summary['skipped']++;
                        $stmtError = mysqli_stmt_error($stmtUser);
                        $summary['failed_rows'][] = [
                            'row' => $index + 2,
                            'email' => $email,
                            'errors' => [$stmtError ?: 'Unknown database error.'],
                        ];
                    }
                }

                mysqli_commit($conn);
                $results = $summary;
                setFlashMessage('success', 'Import completed.');
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $errors[] = 'Import failed: ' . $e->getMessage();
            }

            mysqli_stmt_close($stmtUser);
        }
    }
}

$userCountsByRole = array_fill_keys($roleKeys, 0);
foreach ($fetchAll("SELECT role, COUNT(*) AS total FROM users GROUP BY role") as $row) {
    if (isset($userCountsByRole[$row['role']])) {
        $userCountsByRole[$row['role']] = (int)$row['total'];
    }
}

$statusCounts = array_fill_keys($statusKeys, 0);
foreach ($fetchAll("SELECT status, COUNT(*) AS total FROM leave_requests GROUP BY status") as $row) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = (int)$row['total'];
    }
}

$totalUsers = array_sum($userCountsByRole);
$totalRequests = array_sum($statusCounts);
$todayRequestsCount = (int)($fetchOne("SELECT COUNT(*) AS total FROM leave_requests WHERE DATE(created_at) = CURDATE()")['total'] ?? 0);
$thisYearRequestsCount = (int)($fetchOne("SELECT COUNT(*) AS total FROM leave_requests WHERE YEAR(created_at) = ?", 'i', [$selectedYear])['total'] ?? 0);
$thisMonthRequestsCount = (int)($fetchOne("SELECT COUNT(*) AS total FROM leave_requests WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?", 'ii', [$selectedYear, $selectedMonth])['total'] ?? 0);

$yearlyReportRows = [];
foreach ($fetchAll(
    "SELECT YEAR(created_at) AS report_year,
            COUNT(*) AS total_requests,
            SUM(status = 'pending') AS pending_requests,
            SUM(status = 'approved') AS approved_requests,
            SUM(status = 'rejected') AS rejected_requests,
            SUM(status = 'open') AS open_requests,
            SUM(status = 'closed') AS closed_requests
     FROM leave_requests
     GROUP BY YEAR(created_at)
     ORDER BY report_year DESC"
) as $row) {
    $yearlyReportRows[] = [
        'year' => (int)$row['report_year'],
        'total_requests' => (int)$row['total_requests'],
        'pending_requests' => (int)$row['pending_requests'],
        'approved_requests' => (int)$row['approved_requests'],
        'rejected_requests' => (int)$row['rejected_requests'],
        'open_requests' => (int)$row['open_requests'],
        'closed_requests' => (int)$row['closed_requests'],
    ];
}

$monthlyRowsByMonth = [];
foreach ($fetchAll(
    "SELECT MONTH(created_at) AS report_month,
            COUNT(*) AS total_requests,
            SUM(status = 'pending') AS pending_requests,
            SUM(status = 'approved') AS approved_requests,
            SUM(status = 'rejected') AS rejected_requests,
            SUM(status = 'open') AS open_requests,
            SUM(status = 'closed') AS closed_requests
     FROM leave_requests
     WHERE YEAR(created_at) = ?
     GROUP BY MONTH(created_at)
     ORDER BY report_month",
    'i',
    [$selectedYear]
) as $row) {
    $monthlyRowsByMonth[(int)$row['report_month']] = [
        'total_requests' => (int)$row['total_requests'],
        'pending_requests' => (int)$row['pending_requests'],
        'approved_requests' => (int)$row['approved_requests'],
        'rejected_requests' => (int)$row['rejected_requests'],
        'open_requests' => (int)$row['open_requests'],
        'closed_requests' => (int)$row['closed_requests'],
    ];
}

$monthlyReportRows = [];
for ($month = 1; $month <= 12; $month++) {
    $row = $monthlyRowsByMonth[$month] ?? [
        'total_requests' => 0,
        'pending_requests' => 0,
        'approved_requests' => 0,
        'rejected_requests' => 0,
        'open_requests' => 0,
        'closed_requests' => 0,
    ];

    $monthlyReportRows[] = [
        'month' => $month,
        'month_name' => $monthNames[$month],
        'total_requests' => $row['total_requests'],
        'pending_requests' => $row['pending_requests'],
        'approved_requests' => $row['approved_requests'],
        'rejected_requests' => $row['rejected_requests'],
        'open_requests' => $row['open_requests'],
        'closed_requests' => $row['closed_requests'],
    ];
}

$dailyRowsByDate = [];
foreach ($fetchAll(
    "SELECT DATE(created_at) AS report_date,
            COUNT(*) AS total_requests,
            SUM(status = 'pending') AS pending_requests,
            SUM(status = 'approved') AS approved_requests,
            SUM(status = 'rejected') AS rejected_requests,
            SUM(status = 'open') AS open_requests,
            SUM(status = 'closed') AS closed_requests
     FROM leave_requests
     WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?
     GROUP BY DATE(created_at)
     ORDER BY report_date",
    'ii',
    [$selectedYear, $selectedMonth]
) as $row) {
    $dailyRowsByDate[$row['report_date']] = [
        'total_requests' => (int)$row['total_requests'],
        'pending_requests' => (int)$row['pending_requests'],
        'approved_requests' => (int)$row['approved_requests'],
        'rejected_requests' => (int)$row['rejected_requests'],
        'open_requests' => (int)$row['open_requests'],
        'closed_requests' => (int)$row['closed_requests'],
    ];
}

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
$dailyReportRows = [];
for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $day);
    $row = $dailyRowsByDate[$date] ?? [
        'total_requests' => 0,
        'pending_requests' => 0,
        'approved_requests' => 0,
        'rejected_requests' => 0,
        'open_requests' => 0,
        'closed_requests' => 0,
    ];

    $dailyReportRows[] = [
        'date' => $date,
        'day' => $day,
        'total_requests' => $row['total_requests'],
        'pending_requests' => $row['pending_requests'],
        'approved_requests' => $row['approved_requests'],
        'rejected_requests' => $row['rejected_requests'],
        'open_requests' => $row['open_requests'],
        'closed_requests' => $row['closed_requests'],
    ];
}

$departmentRowsByCode = [];
foreach ($fetchAll(
    "SELECT u.department,
            COUNT(DISTINCT u.id) AS total_users,
            COUNT(DISTINCT IF(u.role = 'student', u.id, NULL)) AS students,
            COUNT(DISTINCT IF(u.role = 'faculty', u.id, NULL)) AS faculty,
            COUNT(DISTINCT IF(u.role = 'hod', u.id, NULL)) AS hod,
            COUNT(DISTINCT IF(u.role = 'admin', u.id, NULL)) AS admins,
            COUNT(lr.id) AS total_requests,
            SUM(lr.status = 'pending') AS pending_requests,
            SUM(lr.status = 'approved') AS approved_requests,
            SUM(lr.status = 'rejected') AS rejected_requests,
            SUM(lr.status = 'open') AS open_requests,
            SUM(lr.status = 'closed') AS closed_requests
     FROM users u
     LEFT JOIN leave_requests lr ON lr.user_id = u.id
     GROUP BY u.department"
) as $row) {
    $departmentRowsByCode[$row['department']] = [
        'total_users' => (int)$row['total_users'],
        'students' => (int)$row['students'],
        'faculty' => (int)$row['faculty'],
        'hod' => (int)$row['hod'],
        'admins' => (int)$row['admins'],
        'total_requests' => (int)$row['total_requests'],
        'pending_requests' => (int)$row['pending_requests'],
        'approved_requests' => (int)$row['approved_requests'],
        'rejected_requests' => (int)$row['rejected_requests'],
        'open_requests' => (int)$row['open_requests'],
        'closed_requests' => (int)$row['closed_requests'],
    ];
}

$departmentReportRows = [];
foreach ($departmentOptions as $department) {
    $code = $department['code'];
    $row = $departmentRowsByCode[$code] ?? [
        'total_users' => 0,
        'students' => 0,
        'faculty' => 0,
        'hod' => 0,
        'admins' => 0,
        'total_requests' => 0,
        'pending_requests' => 0,
        'approved_requests' => 0,
        'rejected_requests' => 0,
        'open_requests' => 0,
        'closed_requests' => 0,
    ];

    $departmentReportRows[] = [
        'department_code' => $code,
        'department' => $departmentLabels[$code] ?? strtoupper($code),
        'total_users' => $row['total_users'],
        'students' => $row['students'],
        'faculty' => $row['faculty'],
        'hod' => $row['hod'],
        'admins' => $row['admins'],
        'total_requests' => $row['total_requests'],
        'pending_requests' => $row['pending_requests'],
        'approved_requests' => $row['approved_requests'],
        'rejected_requests' => $row['rejected_requests'],
        'open_requests' => $row['open_requests'],
        'closed_requests' => $row['closed_requests'],
    ];
}

$liveWhereSql = "WHERE DATE(lr.created_at) = ?";
$liveParams = [$selectedDate];
$liveTypes = 's';
if ($selectedDepartment !== '') {
    $liveWhereSql .= " AND u.department = ?";
    $liveParams[] = $selectedDepartment;
    $liveTypes .= 's';
}

$liveSummary = $fetchOne(
    "SELECT COUNT(*) AS total_requests,
            SUM(lr.status = 'pending') AS pending_requests,
            SUM(lr.status = 'approved') AS approved_requests,
            SUM(lr.status = 'rejected') AS rejected_requests,
            SUM(lr.status = 'open') AS open_requests,
            SUM(lr.status = 'closed') AS closed_requests
     FROM leave_requests lr
     INNER JOIN users u ON u.id = lr.user_id
     $liveWhereSql",
    $liveTypes,
    $liveParams
);

$liveRows = $fetchAll(
    "SELECT lr.created_at,
            lr.leave_type,
            lr.from_date,
            lr.to_date,
            lr.expected_duration,
            lr.reason,
            lr.proof,
            lr.is_medical,
            lr.status,
            lr.hod_remarks,
            u.name,
            u.role,
            u.department,
            u.regd_no,
            u.emp_no,
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
     INNER JOIN users u ON u.id = lr.user_id
     $liveWhereSql
     ORDER BY lr.created_at DESC",
    $liveTypes,
    $liveParams
);

$renderStatGrid = static function (array $tiles, string $gridClass = 'stats-grid') {
    ob_start();
    ?>
    <div class="<?php echo htmlspecialchars($gridClass); ?>">
        <?php foreach ($tiles as $tile): ?>
        <?php if (!empty($tile['href'])): ?>
        <a class="stat-card <?php echo htmlspecialchars($tile['class']); ?>" href="<?php echo htmlspecialchars($tile['href']); ?>">
            <div class="stat-number"><?php echo (int)$tile['value']; ?></div>
            <div class="stat-label"><?php echo htmlspecialchars($tile['label']); ?></div>
        </a>
        <?php else: ?>
        <div class="stat-card <?php echo htmlspecialchars($tile['class']); ?>">
            <div class="stat-number"><?php echo (int)$tile['value']; ?></div>
            <div class="stat-label"><?php echo htmlspecialchars($tile['label']); ?></div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
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

$departmentPresenceRows = [];
foreach ($todayDepartmentPresence as $presenceRow) {
    $departmentPresenceRows[] = [
        $presenceRow['label'],
        (int)$presenceRow['total_students'],
        (int)$presenceRow['students_present'],
        [
            'html' => '<a href="' . htmlspecialchars($buildAbsenteesUrl('student', ['department' => $presenceRow['department']])) . '">'
                . (int)$presenceRow['students_absent'] . '</a>',
        ],
        (int)$presenceRow['total_staff'],
        (int)$presenceRow['staff_present'],
        [
            'html' => '<a href="' . htmlspecialchars($buildAbsenteesUrl('faculty', ['department' => $presenceRow['department']])) . '">'
                . (int)$presenceRow['staff_absent'] . '</a>',
        ],
    ];
}

$yearlyTableRows = [];
foreach ($yearlyReportRows as $row) {
    $yearlyTableRows[] = [
        (int)$row['year'],
        (int)$row['total_requests'],
        (int)$row['pending_requests'],
        (int)$row['approved_requests'],
        (int)$row['rejected_requests'],
        (int)$row['open_requests'],
        (int)$row['closed_requests'],
    ];
}

$monthlyTableRows = [];
foreach ($monthlyReportRows as $row) {
    $monthlyTableRows[] = [
        $row['month_name'],
        (int)$row['total_requests'],
        (int)$row['pending_requests'],
        (int)$row['approved_requests'],
        (int)$row['rejected_requests'],
        (int)$row['open_requests'],
        (int)$row['closed_requests'],
    ];
}

$dailyTableRows = [];
foreach ($dailyReportRows as $row) {
    $dailyTableRows[] = [
        $row['date'],
        (int)$row['day'],
        (int)$row['total_requests'],
        (int)$row['pending_requests'],
        (int)$row['approved_requests'],
        (int)$row['rejected_requests'],
        (int)$row['open_requests'],
        (int)$row['closed_requests'],
    ];
}

$departmentTableRows = [];
foreach ($departmentReportRows as $row) {
    $departmentUrl = !empty($row['department_code']) ? $buildRequestsUrl(['department' => $row['department_code']]) : null;
    $pendingUrl = !empty($row['department_code']) ? $buildRequestsUrl(['department' => $row['department_code'], 'status' => 'pending']) : null;
    $approvedUrl = !empty($row['department_code']) ? $buildRequestsUrl(['department' => $row['department_code'], 'status' => 'approved']) : null;
    $rejectedUrl = !empty($row['department_code']) ? $buildRequestsUrl(['department' => $row['department_code'], 'status' => 'rejected']) : null;
    $openUrl = !empty($row['department_code']) ? $buildRequestsUrl(['department' => $row['department_code'], 'status' => 'open']) : null;
    $closedUrl = !empty($row['department_code']) ? $buildRequestsUrl(['department' => $row['department_code'], 'status' => 'closed']) : null;

    $departmentTableRows[] = [
        [
            'html' => $departmentUrl
                ? '<a href="' . htmlspecialchars($departmentUrl) . '">' . htmlspecialchars($row['department']) . '</a>'
                : htmlspecialchars($row['department']),
        ],
        (int)$row['total_users'],
        (int)$row['students'],
        (int)$row['faculty'],
        (int)$row['hod'],
        (int)$row['admins'],
        [
            'html' => $departmentUrl
                ? '<a href="' . htmlspecialchars($departmentUrl) . '">' . (int)$row['total_requests'] . '</a>'
                : (string)(int)$row['total_requests'],
        ],
        [
            'html' => $pendingUrl
                ? '<a href="' . htmlspecialchars($pendingUrl) . '">' . (int)$row['pending_requests'] . '</a>'
                : (string)(int)$row['pending_requests'],
        ],
        [
            'html' => $approvedUrl
                ? '<a href="' . htmlspecialchars($approvedUrl) . '">' . (int)$row['approved_requests'] . '</a>'
                : (string)(int)$row['approved_requests'],
        ],
        [
            'html' => $rejectedUrl
                ? '<a href="' . htmlspecialchars($rejectedUrl) . '">' . (int)$row['rejected_requests'] . '</a>'
                : (string)(int)$row['rejected_requests'],
        ],
        [
            'html' => $openUrl
                ? '<a href="' . htmlspecialchars($openUrl) . '">' . (int)$row['open_requests'] . '</a>'
                : (string)(int)$row['open_requests'],
        ],
        [
            'html' => $closedUrl
                ? '<a href="' . htmlspecialchars($closedUrl) . '">' . (int)$row['closed_requests'] . '</a>'
                : (string)(int)$row['closed_requests'],
        ],
    ];
}

$liveTableRows = [];
foreach ($liveRows as $leave) {
    $liveTableRows[] = [
        date('d M Y h:i A', strtotime($leave['created_at'])),
        $leave['name'],
        ucfirst($leave['role']),
        $leave['role'] === 'faculty' ? getFacultyCasualLeaveDisplay($leave) : '-',
        $departmentLabels[$leave['department']] ?? strtoupper($leave['department']),
        getIdentifierValue($leave),
        ucfirst(str_replace('_', ' ', $leave['leave_type'])),
        $leave['from_date'],
        $leave['to_date'] ?: '-',
        $leave['expected_duration'] ?: '-',
        [
            'html' => '<span class="badge ' . htmlspecialchars(getStatusBadgeClass($leave['status'])) . '">'
                . htmlspecialchars(ucfirst($leave['status'])) . '</span>',
        ],
        !empty($leave['proof'])
            ? ['html' => '<a class="btn btn-secondary btn-sm" href="/leave_management_system/uploads/' . rawurlencode($leave['proof']) . '" target="_blank" rel="noopener">View</a>']
            : '-',
        $leave['hod_remarks'] ?: '-',
    ];
}

$recentYears = [];
foreach ($yearlyReportRows as $row) {
    $recentYears[$row['year']] = true;
}
$recentYears[$currentYear] = true;
krsort($recentYears);
$availableYears = array_keys($recentYears);
if (empty($availableYears)) {
    $availableYears = [$currentYear];
}

$studentOverviewSummary = [
    'total_users' => (int)($userCountsByRole['student'] ?? 0),
    'total_requests' => 0,
    'status_counts' => array_fill_keys($statusKeys, 0),
    'today_absent' => count(getAbsentUsersOnDate($conn, $currentDate, 'student', $selectedDepartment !== '' ? $selectedDepartment : null)),
];

$facultyOverviewSummary = [
    'total_users' => (int)($userCountsByRole['faculty'] ?? 0),
    'total_requests' => 0,
    'status_counts' => array_fill_keys($statusKeys, 0),
    'today_absent' => count(getAbsentUsersOnDate($conn, $currentDate, 'faculty', $selectedDepartment !== '' ? $selectedDepartment : null)),
];

foreach ($fetchAll(
    "SELECT u.role, lr.status, COUNT(*) AS total
     FROM leave_requests lr
     INNER JOIN users u ON u.id = lr.user_id
     WHERE u.role IN ('student', 'faculty')
     GROUP BY u.role, lr.status"
) as $row) {
    if ($row['role'] === 'student') {
        $studentOverviewSummary['status_counts'][$row['status']] = (int)$row['total'];
        $studentOverviewSummary['total_requests'] += (int)$row['total'];
    } elseif ($row['role'] === 'faculty') {
        $facultyOverviewSummary['status_counts'][$row['status']] = (int)$row['total'];
        $facultyOverviewSummary['total_requests'] += (int)$row['total'];
    }
}

$studentOverviewTiles = [
    [
        'label' => 'Total Students',
        'value' => $studentOverviewSummary['total_users'],
        'class' => '',
        'href' => '',
    ],
    [
        'label' => 'Total Leave Requests',
        'value' => $studentOverviewSummary['total_requests'],
        'class' => 'open',
        'href' => $buildRequestsUrl(['role' => 'student']),
    ],
    [
        'label' => 'Pending',
        'value' => $studentOverviewSummary['status_counts']['pending'],
        'class' => 'pending',
        'href' => $buildRequestsUrl(['role' => 'student', 'status' => 'pending']),
    ],
    [
        'label' => 'Approved',
        'value' => $studentOverviewSummary['status_counts']['approved'],
        'class' => 'approved',
        'href' => $buildRequestsUrl(['role' => 'student', 'status' => 'approved']),
    ],
    [
        'label' => 'Rejected',
        'value' => $studentOverviewSummary['status_counts']['rejected'],
        'class' => 'rejected',
        'href' => $buildRequestsUrl(['role' => 'student', 'status' => 'rejected']),
    ],
    [
        'label' => 'Open Medical',
        'value' => $studentOverviewSummary['status_counts']['open'],
        'class' => 'open',
        'href' => $buildRequestsUrl(['role' => 'student', 'status' => 'open']),
    ],
    [
        'label' => 'Closed Medical',
        'value' => $studentOverviewSummary['status_counts']['closed'],
        'class' => '',
        'href' => $buildRequestsUrl(['role' => 'student', 'status' => 'closed']),
    ],
    [
        'label' => "Today's Absentees",
        'value' => $studentOverviewSummary['today_absent'],
        'class' => 'rejected',
        'href' => $buildAbsenteesUrl('student'),
    ],
];

$facultyOverviewTiles = [
    [
        'label' => 'Total Faculty',
        'value' => $facultyOverviewSummary['total_users'],
        'class' => '',
        'href' => '',
    ],
    [
        'label' => 'Total Leave Requests',
        'value' => $facultyOverviewSummary['total_requests'],
        'class' => 'open',
        'href' => $buildRequestsUrl(['role' => 'faculty']),
    ],
    [
        'label' => 'Pending',
        'value' => $facultyOverviewSummary['status_counts']['pending'],
        'class' => 'pending',
        'href' => $buildRequestsUrl(['role' => 'faculty', 'status' => 'pending']),
    ],
    [
        'label' => 'Approved',
        'value' => $facultyOverviewSummary['status_counts']['approved'],
        'class' => 'approved',
        'href' => $buildRequestsUrl(['role' => 'faculty', 'status' => 'approved']),
    ],
    [
        'label' => 'Rejected',
        'value' => $facultyOverviewSummary['status_counts']['rejected'],
        'class' => 'rejected',
        'href' => $buildRequestsUrl(['role' => 'faculty', 'status' => 'rejected']),
    ],
    [
        'label' => 'Open Medical',
        'value' => $facultyOverviewSummary['status_counts']['open'],
        'class' => 'open',
        'href' => $buildRequestsUrl(['role' => 'faculty', 'status' => 'open']),
    ],
    [
        'label' => 'Closed Medical',
        'value' => $facultyOverviewSummary['status_counts']['closed'],
        'class' => '',
        'href' => $buildRequestsUrl(['role' => 'faculty', 'status' => 'closed']),
    ],
    [
        'label' => "Today's Absentees",
        'value' => $facultyOverviewSummary['today_absent'],
        'class' => 'rejected',
        'href' => $buildAbsenteesUrl('faculty'),
    ],
];

$collegeOverviewTiles = [
    [
        'label' => 'Total Users',
        'value' => $totalUsers,
        'class' => '',
        'href' => '',
    ],
    [
        'label' => 'Total Leave Requests',
        'value' => $totalRequests,
        'class' => 'open',
        'href' => $buildRequestsUrl(),
    ],
    [
        'label' => 'Pending',
        'value' => $statusCounts['pending'],
        'class' => 'pending',
        'href' => $buildRequestsUrl(['status' => 'pending']),
    ],
    [
        'label' => 'Approved',
        'value' => $statusCounts['approved'],
        'class' => 'approved',
        'href' => $buildRequestsUrl(['status' => 'approved']),
    ],
    [
        'label' => 'Rejected',
        'value' => $statusCounts['rejected'],
        'class' => 'rejected',
        'href' => $buildRequestsUrl(['status' => 'rejected']),
    ],
    [
        'label' => 'Open Medical',
        'value' => $statusCounts['open'],
        'class' => 'open',
        'href' => $buildRequestsUrl(['status' => 'open']),
    ],
    [
        'label' => 'Closed Medical',
        'value' => $statusCounts['closed'],
        'class' => '',
        'href' => $buildRequestsUrl(['status' => 'closed']),
    ],
    [
        'label' => "Today's Requests",
        'value' => $todayRequestsCount,
        'class' => 'approved',
        'href' => $buildRequestsUrl(array_filter([
            'date' => $currentDate,
            'department' => $selectedDepartment !== '' ? $selectedDepartment : null,
        ])),
    ],
];

$liveSummaryTiles = [
    [
        'label' => 'Requests on Day',
        'value' => (int)($liveSummary['total_requests'] ?? 0),
        'class' => '',
        'href' => $buildRequestsUrl(array_filter([
            'date' => $selectedDate,
            'department' => $selectedDepartment !== '' ? $selectedDepartment : null,
        ])),
    ],
    [
        'label' => 'Pending',
        'value' => (int)($liveSummary['pending_requests'] ?? 0),
        'class' => 'pending',
        'href' => $buildRequestsUrl(array_filter([
            'date' => $selectedDate,
            'department' => $selectedDepartment !== '' ? $selectedDepartment : null,
            'status' => 'pending',
        ])),
    ],
    [
        'label' => 'Approved',
        'value' => (int)($liveSummary['approved_requests'] ?? 0),
        'class' => 'approved',
        'href' => $buildRequestsUrl(array_filter([
            'date' => $selectedDate,
            'department' => $selectedDepartment !== '' ? $selectedDepartment : null,
            'status' => 'approved',
        ])),
    ],
    [
        'label' => 'Rejected',
        'value' => (int)($liveSummary['rejected_requests'] ?? 0),
        'class' => 'rejected',
        'href' => $buildRequestsUrl(array_filter([
            'date' => $selectedDate,
            'department' => $selectedDepartment !== '' ? $selectedDepartment : null,
            'status' => 'rejected',
        ])),
    ],
    [
        'label' => 'Open Medical',
        'value' => (int)($liveSummary['open_requests'] ?? 0),
        'class' => 'open',
        'href' => $buildRequestsUrl(array_filter([
            'date' => $selectedDate,
            'department' => $selectedDepartment !== '' ? $selectedDepartment : null,
            'status' => 'open',
        ])),
    ],
    [
        'label' => 'Closed Medical',
        'value' => (int)($liveSummary['closed_requests'] ?? 0),
        'class' => '',
        'href' => $buildRequestsUrl(array_filter([
            'date' => $selectedDate,
            'department' => $selectedDepartment !== '' ? $selectedDepartment : null,
            'status' => 'closed',
        ])),
    ],
];
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content admin-clean-main">
        <div class="content-header principal-header">
            <div>
                <h2>Admin Dashboard</h2>
                <p class="page-subtitle">A cleaner control room with just the essentials: search, live filters, today’s presence, latest activity, and import.</p>
            </div>
            <div class="principal-meta">
                <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($buildAbsenteesUrl('student')); ?>">Student Absentees</a>
                <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($buildAbsenteesUrl('faculty')); ?>">Faculty Absentees</a>
            </div>
        </div>

        <div class="card page-search-panel">
            <div class="card-header section-header">
                <div>
                    <h3>Quick People Search</h3>
                    <div class="section-note">Jump straight to a person without using the top navigation.</div>
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

        <div class="card">
            <div class="card-header section-header">
                <h3>At a Glance</h3>
                <div class="section-actions">
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($buildRequestsUrl()); ?>">All Requests</a>
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($buildUrl(['export' => 'college'])); ?>">Export CSV</a>
                </div>
            </div>
            <?php echo $renderStatGrid([
                ['label' => 'Total Users', 'value' => $totalUsers, 'class' => '', 'href' => '/leave_management_system/admin/people_search.php'],
                ['label' => 'Total Requests', 'value' => $totalRequests, 'class' => 'open', 'href' => $buildRequestsUrl()],
                ['label' => 'Pending', 'value' => $statusCounts['pending'], 'class' => 'pending', 'href' => $buildRequestsUrl(['status' => 'pending'])],
                ['label' => 'Approved', 'value' => $statusCounts['approved'], 'class' => 'approved', 'href' => $buildRequestsUrl(['status' => 'approved'])],
                ['label' => 'Today Requests', 'value' => $todayRequestsCount, 'class' => 'rejected', 'href' => $buildRequestsUrl(['date' => $currentDate, 'department' => $selectedDepartment !== '' ? $selectedDepartment : null])],
                ['label' => 'Today Absent', 'value' => $todayPresence['students_absent'] + $todayPresence['staff_absent'], 'class' => 'open', 'href' => $buildAbsenteesUrl('student')],
            ], 'stats-grid report-mini-grid'); ?>
        </div>

        <div class="card report-controls" id="report-controls">
            <div class="card-header section-header">
                <h3>Live Filters</h3>
                <div class="section-actions">
                    <a href="/leave_management_system/admin/dashboard.php" class="btn btn-secondary btn-sm">Reset</a>
                </div>
            </div>
            <form method="GET" class="filter-form report-filter-form">
                <div class="form-group">
                    <label for="year">Year</label>
                    <select id="year" name="year" class="form-control">
                        <?php foreach ($availableYears as $yearOption): ?>
                        <option value="<?php echo (int)$yearOption; ?>" <?php echo (int)$yearOption === $selectedYear ? 'selected' : ''; ?>>
                            <?php echo (int)$yearOption; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="month">Month</label>
                    <select id="month" name="month" class="form-control">
                        <?php foreach ($monthNames as $monthNumber => $monthLabel): ?>
                        <option value="<?php echo (int)$monthNumber; ?>" <?php echo (int)$monthNumber === $selectedMonth ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($monthLabel); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($selectedDate); ?>">
                </div>
                <div class="form-group">
                    <label for="department">Department</label>
                    <select id="department" name="department" class="form-control">
                        <option value="">All Departments</option>
                        <?php foreach ($departmentOptions as $department): ?>
                        <option value="<?php echo htmlspecialchars($department['code']); ?>" <?php echo $selectedDepartment === $department['code'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($department['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                </div>
            </form>
            <div class="form-hint">These filters only affect the live day table below.</div>
        </div>

        <div class="card" id="today-overview">
            <div class="card-header section-header">
                <div>
                    <h3>Today at a Glance</h3>
                    <div class="section-note">Presence and absentees for the current day.</div>
                </div>
                <div class="section-actions">
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($buildUrl(['export' => 'presence_overall'])); ?>">Export CSV</a>
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($buildUrl(['export' => 'presence_department'])); ?>">Dept CSV</a>
                </div>
            </div>
            <?php echo $renderStatGrid([
                ['label' => 'Students Present', 'value' => $todayPresence['students_present'], 'class' => 'approved', 'href' => ''],
                ['label' => 'Students Absent', 'value' => $todayPresence['students_absent'], 'class' => 'pending', 'href' => $buildAbsenteesUrl('student')],
                ['label' => 'Staff Present', 'value' => $todayPresence['staff_present'], 'class' => 'approved', 'href' => ''],
                ['label' => 'Staff Absent', 'value' => $todayPresence['staff_absent'], 'class' => 'open', 'href' => $buildAbsenteesUrl('faculty')],
                ['label' => 'Total Students', 'value' => $todayPresence['total_students'], 'class' => '', 'href' => ''],
                ['label' => 'Total Staff', 'value' => $todayPresence['total_staff'], 'class' => '', 'href' => ''],
            ], 'stats-grid report-mini-grid'); ?>
            <?php echo $renderTable(
                ['Department', 'Total Students', 'Students Present', 'Students Absent', 'Total Staff', 'Staff Present', 'Staff Absent'],
                $departmentPresenceRows,
                'No department presence data found.'
            ); ?>
        </div>

        <div class="card" id="live-today">
            <div class="card-header section-header">
                <div>
                    <h3>Latest Activity</h3>
                    <p class="section-note">Date: <?php echo htmlspecialchars(formatDate($selectedDate)); ?><?php echo $selectedDepartment !== '' ? ' | Department: ' . htmlspecialchars($departmentLabels[$selectedDepartment] ?? strtoupper($selectedDepartment)) : ''; ?></p>
                </div>
                <div class="section-actions">
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($buildUrl(['export' => 'live'])); ?>">Export CSV</a>
                </div>
            </div>

            <?php echo $renderStatGrid($liveSummaryTiles, 'stats-grid report-mini-grid'); ?>

            <?php echo $renderTable(
                ['Requested At', 'Applicant', 'Role', 'CL Taken / 15', 'Department', 'Identifier', 'Leave Type', 'From', 'To', 'Duration', 'Status', 'Proof', 'Remarks'],
                array_slice($liveTableRows, 0, 10),
                'No leave requests found for the selected day.'
            ); ?>
        </div>

        <div class="card" id="import-users">
            <div class="card-header section-header">
                <div>
                    <h3>Bulk Import</h3>
                    <div class="section-note">Upload users in CSV or XLSX format. Keep it simple and tucked away until you need it.</div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="flash-message flash-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($results): ?>
            <div class="flash-message flash-success">
                Imported <?php echo (int)$results['imported']; ?> users, skipped <?php echo (int)$results['skipped']; ?> rows.
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="import_file" class="required">CSV or XLSX File</label>
                    <input type="file" id="import_file" name="import_file" class="form-control" accept="<?php echo $xlsx_supported ? '.csv,.xlsx' : '.csv'; ?>" required>
                    <div class="form-hint">Passwords are hashed automatically before save.</div>
                </div>
                <button type="submit" class="btn btn-primary">Import Users</button>
            </form>

            <details class="import-details">
                <summary>File format and rules</summary>
                <pre class="code-block">name,email,role,department,regd_no,emp_no,designation,student_year,student_section,admin_team,password</pre>
                <ul class="simple-list">
                    <li><code>role</code> must be one of <code>student</code>, <code>faculty</code>, <code>hod</code>, <code>principal</code>, or <code>admin</code>.</li>
                    <li><code>department</code> must be one of <code>cse</code>, <code>ece</code>, <code>eee</code>, <code>mec</code>, <code>css</code>, <code>cit</code>, <code>csm</code>.</li>
                    <li>Students use <code>regd_no</code>, <code>student_year</code>, and <code>student_section</code>.</li>
                    <li>Faculty, HOD, principal, and admin users use <code>emp_no</code>.</li>
                    <li>Use <code>admin_team</code> as a checkbox value such as <code>1</code>, <code>yes</code>, or <code>on</code>.</li>
                </ul>
            </details>
        </div>
    </main>
</div>

</body>
</html>

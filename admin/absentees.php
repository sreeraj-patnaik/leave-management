<?php
require_once __DIR__ . '/../includes/header.php';
checkRole('admin');

$page_title = 'Absentees Today';
$departments = getDepartments($conn);
$departmentCodes = getDepartmentCodes();

$selectedRole = strtolower(trim($_GET['role'] ?? 'student'));
if (!in_array($selectedRole, ['student', 'faculty'], true)) {
    $selectedRole = 'student';
}

$selectedDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$selectedDepartment = strtolower(trim($_GET['department'] ?? ''));
if ($selectedDepartment !== '' && !in_array($selectedDepartment, $departmentCodes, true)) {
    $selectedDepartment = '';
}

$absentees = getAbsentUsersOnDate($conn, $selectedDate, $selectedRole, $selectedDepartment !== '' ? $selectedDepartment : null);
$defaultHistoryReturn = '/leave_management_system/admin/absentees.php?' . http_build_query(array_filter([
    'role' => $selectedRole,
    'date' => $selectedDate,
    'department' => $selectedDepartment !== '' ? $selectedDepartment : null,
]));
?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h2><?php echo $selectedRole === 'faculty' ? 'Faculty' : 'Student'; ?> Absentees</h2>
            <p class="page-subtitle">Click a person to open their full leave history and review the leaves behind today’s absence.</p>
        </div>

        <div class="card">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="role">Group</label>
                    <select id="role" name="role" class="form-control">
                        <option value="student" <?php echo $selectedRole === 'student' ? 'selected' : ''; ?>>Students</option>
                        <option value="faculty" <?php echo $selectedRole === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
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
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['code']); ?>" <?php echo $selectedDepartment === $dept['code'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="/leave_management_system/admin/absentees.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header section-header">
                <h3>Absent <?php echo $selectedRole === 'faculty' ? 'Faculty' : 'Students'; ?></h3>
            </div>

            <?php if (!empty($absentees)): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Identifier</th>
                            <th>Active Leave</th>
                            <th>From</th>
                            <th>To</th>
                            <?php if ($selectedRole === 'faculty'): ?>
                            <th>CL Taken / 15</th>
                            <?php endif; ?>
                            <th>History</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absentees as $absentee): ?>
                        <tr>
                            <td>
                                <a class="proof-link" href="<?php echo htmlspecialchars(buildUserHistoryUrl($absentee['id'], [], $defaultHistoryReturn)); ?>">
                                    <?php echo htmlspecialchars($absentee['name']); ?>
                                </a>
                                <br><small><?php echo htmlspecialchars(getUserMetaText($absentee)); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars(getDepartmentLabel($absentee['department'])); ?></td>
                            <td><?php echo htmlspecialchars(getIdentifierValue($absentee)); ?></td>
                            <td>
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $absentee['leave_type']))); ?>
                                <?php if (!empty($absentee['status'])): ?>
                                <br><span class="badge <?php echo getStatusBadgeClass($absentee['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($absentee['status'])); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(formatDate($absentee['from_date'])); ?></td>
                            <td><?php echo $absentee['to_date'] ? htmlspecialchars(formatDate($absentee['to_date'])) : '<span class="badge badge-info">Open</span>'; ?></td>
                            <?php if ($selectedRole === 'faculty'): ?>
                            <td>
                                <a
                                    class="casual-count-link"
                                    href="/leave_management_system/admin/user_history.php?user_id=<?php echo (int)$absentee['id']; ?>&return_to=<?php echo urlencode($defaultHistoryReturn); ?>"
                                    title="View <?php echo htmlspecialchars($absentee['name']); ?>'s leave history"
                                >
                                    <?php echo htmlspecialchars(getFacultyCasualLeaveDisplay($absentee)); ?>
                                </a>
                            </td>
                            <?php endif; ?>
                            <td>
                                <a class="btn btn-primary btn-sm" href="/leave_management_system/admin/user_history.php?user_id=<?php echo (int)$absentee['id']; ?>&return_to=<?php echo urlencode($defaultHistoryReturn); ?>">
                                    Open History
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>No absentees found for the selected filters.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>

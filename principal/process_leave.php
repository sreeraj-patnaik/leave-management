<?php
$page_title = 'Process HOD Leave';
require_once __DIR__ . '/../includes/header.php';

if (!isLoggedIn()) {
    redirect('/leave_management_system/auth/login.php');
}

checkRole('principal');

$defaultReturnUrl = '/leave_management_system/principal/requests.php';
$returnTo = trim($_GET['return_to'] ?? '');
if ($returnTo === '' || strpos($returnTo, '/leave_management_system/') !== 0) {
    $returnTo = $defaultReturnUrl;
}

$leave_id = (int)($_GET['id'] ?? 0);
$errors = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT lr.*, u.name, u.role, u.department, u.regd_no, u.emp_no, u.designation, u.student_year, u.student_section, u.admin_team, u.email
     FROM leave_requests lr
     JOIN users u ON lr.user_id = u.id
     WHERE lr.id = ? AND u.role = 'hod'"
);
mysqli_stmt_bind_param($stmt, "i", $leave_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$leave = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$leave) {
    setFlashMessage('error', 'Leave request not found.');
    redirect($returnTo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $remarks = sanitize($_POST['remarks'] ?? '');

    if (!in_array($action, ['approve', 'reject'], true)) {
        $errors[] = 'Invalid action.';
    }

    if (empty($errors)) {
        $new_status = $action === 'approve' ? 'approved' : 'rejected';

        $stmt = mysqli_prepare($conn, "UPDATE leave_requests SET status = ?, hod_remarks = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $new_status, $remarks, $leave_id);

        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage('success', 'HOD leave request ' . $new_status . ' successfully!');
            redirect($returnTo);
        } else {
            $errors[] = 'Failed to update leave request.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h2>HOD Leave Request Details</h2>
            <p class="page-subtitle">Principal review screen for HOD leave requests.</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Applicant Information</h3>
            </div>

            <div class="detail-row">
                <div class="detail-label">Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($leave['name']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Role:</div>
                <div class="detail-value"><?php echo ucfirst($leave['role']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Department:</div>
                <div class="detail-value"><?php echo htmlspecialchars(getDepartmentLabel($leave['department'])); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Profile:</div>
                <div class="detail-value"><?php echo htmlspecialchars(getUserMetaText($leave)); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><?php echo htmlspecialchars(getIdentifierLabel($leave['role'])); ?>:</div>
                <div class="detail-value"><?php echo htmlspecialchars(getIdentifierValue($leave)); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Email:</div>
                <div class="detail-value"><?php echo htmlspecialchars($leave['email']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">History:</div>
                <div class="detail-value">
                    <a class="proof-link" href="<?php echo htmlspecialchars(buildUserHistoryUrl($leave['user_id'], [], $returnTo)); ?>">
                        Open full leave history
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Leave Details</h3>
            </div>

            <div class="detail-row">
                <div class="detail-label">Leave Type:</div>
                <div class="detail-value">
                    <?php echo ucfirst(str_replace('_', ' ', $leave['leave_type'])); ?>
                    <?php if ($leave['is_medical']): ?>
                    <span class="badge badge-info">Medical</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">From Date:</div>
                <div class="detail-value"><?php echo formatDate($leave['from_date']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">To Date:</div>
                <div class="detail-value">
                    <?php echo $leave['to_date'] ? formatDate($leave['to_date']) : '<span class="badge badge-info">Open (Medical)</span>'; ?>
                </div>
            </div>
            <?php if ($leave['expected_duration']): ?>
            <div class="detail-row">
                <div class="detail-label">Expected Duration:</div>
                <div class="detail-value"><?php echo htmlspecialchars($leave['expected_duration']); ?></div>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <div class="detail-label">Reason:</div>
                <div class="detail-value"><?php echo nl2br(htmlspecialchars($leave['reason'])); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Current Status:</div>
                <div class="detail-value">
                    <span class="badge <?php echo getStatusBadgeClass($leave['status']); ?>">
                        <?php echo ucfirst($leave['status']); ?>
                    </span>
                </div>
            </div>
            <?php if ($leave['proof']): ?>
            <div class="detail-row">
                <div class="detail-label">Proof Document:</div>
                <div class="detail-value">
                    <a href="/leave_management_system/uploads/<?php echo htmlspecialchars($leave['proof']); ?>"
                       target="_blank" class="proof-link">View Document</a>
                </div>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <div class="detail-label">Applied On:</div>
                <div class="detail-value"><?php echo formatDate($leave['created_at']); ?></div>
            </div>
            <?php if ($leave['hod_remarks']): ?>
            <div class="detail-row">
                <div class="detail-label">Principal Remarks:</div>
                <div class="detail-value"><?php echo nl2br(htmlspecialchars($leave['hod_remarks'])); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($leave['status'] === 'pending'): ?>
        <div class="card">
            <div class="card-header">
                <h3>Take Action</h3>
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

            <form method="POST">
                <div class="form-group">
                    <label for="remarks">Remarks (Optional)</label>
                    <textarea id="remarks" name="remarks" class="form-control" placeholder="Add any comments or remarks..."><?php echo $remarks ?? ''; ?></textarea>
                </div>

                <div class="action-group">
                    <button type="submit" name="action" value="approve" class="btn btn-success">
                        Approve Leave
                    </button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger">
                        Reject Leave
                    </button>
                    <a href="<?php echo htmlspecialchars($returnTo); ?>" class="btn btn-secondary">Back to List</a>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="action-group">
            <a href="<?php echo htmlspecialchars($returnTo); ?>" class="btn btn-secondary">Back to List</a>
        </div>
        <?php endif; ?>
    </main>
</div>

</body>
</html>

<?php
include '../config/db.php';
include '../includes/auth_check.php';
require_role(['student', 'faculty', 'hod']);

$error = '';
$success = '';
$from = '';
$to = '';
$reason = '';
$leaveType = 'Casual';

$leaveTypes = [
    'Casual',
    'Sick',
    'Earned',
    'Maternity',
    'Study',
    'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $from = trim($_POST['from'] ?? '');
        $to = trim($_POST['to'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $leaveType = trim($_POST['leave_type'] ?? 'Casual');

        $fromDate = DateTime::createFromFormat('Y-m-d', $from);
        $toDate = DateTime::createFromFormat('Y-m-d', $to);

        if (!$fromDate || !$toDate) {
            $error = 'Please enter valid dates.';
        } elseif ($fromDate > $toDate) {
            $error = 'The end date must be the same as or after the start date.';
        } elseif ($reason === '') {
            $error = 'Please provide a reason for your leave.';
        } elseif (!in_array($leaveType, $leaveTypes, true)) {
            $error = 'Please select a valid leave type.';
        } else {
            $approverId = get_final_approver_for_requester($pdo, $current_user);

            if (!$approverId) {
                $error = 'Could not locate the correct approver for your role. Please contact the administrator.';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO leaves (user_id, leave_type, from_date, to_date, reason, current_approver_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([
                    $current_user['id'],
                    $leaveType,
                    $from,
                    $to,
                    $reason,
                    $approverId,
                    'pending',
                ]);

                $success = 'Your leave request has been submitted successfully.';
                $from = '';
                $to = '';
                $reason = '';
                $leaveType = 'Casual';
            }
        }
    }
}

$token = generate_csrf_token();
include '../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="card-title mb-3">Apply for Leave</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= h($error) ?></div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success"><?= h($success) ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start date</label>
                            <input type="date" name="from" class="form-control" required value="<?= h($from) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End date</label>
                            <input type="date" name="to" class="form-control" required value="<?= h($to) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Leave type</label>
                        <select name="leave_type" class="form-select" required>
                            <?php foreach ($leaveTypes as $type): ?>
                                <option value="<?= h($type) ?>" <?= $leaveType === $type ? 'selected' : '' ?>><?= h($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="4" required><?= h($reason) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Submit Request</button>
                    <a href="<?= APP_ROOT ?>/student/my_leaves.php" class="btn btn-outline-secondary ms-2">My Leaves</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
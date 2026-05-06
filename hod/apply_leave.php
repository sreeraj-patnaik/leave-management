<?php
$page_title = 'Apply Leave';
require_once __DIR__ . '/../includes/header.php';
checkRole('hod');

$user_id = $_SESSION['user_id'];
$user = getUserById($conn, $user_id);
$identifier_label = getIdentifierLabel('hod');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type = sanitize($_POST['leave_type'] ?? '');
    $from_date = sanitize($_POST['from_date'] ?? '');
    $to_date = sanitize($_POST['to_date'] ?? '');
    $expected_duration = sanitize($_POST['expected_duration'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');
    $is_medical = isset($_POST['is_medical']) ? 1 : 0;
    $proof = null;

    if (!in_array($leave_type, ['casual', 'medical', 'on_duty', 'academic', 'vacation'], true)) {
        $errors[] = 'Invalid leave type.';
    }

    if (empty($from_date)) {
        $errors[] = 'From date is required.';
    }

    if ($is_medical || $leave_type === 'medical') {
        $is_medical = 1;
        $leave_type = 'medical';
        $status = 'open';
        $to_date = null;

        if (empty($expected_duration)) {
            $errors[] = 'Expected duration is required for medical leave.';
        }
    } elseif (in_array($leave_type, ['on_duty', 'academic'], true)) {
        $status = 'pending';

        if (empty($to_date)) {
            $errors[] = 'To date is required.';
        }

        if (!isset($_FILES['proof']) || $_FILES['proof']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Proof document is required for ' . str_replace('_', ' ', $leave_type) . ' leave.';
        } else {
            $file_errors = validateFileUpload($_FILES['proof']);
            $errors = array_merge($errors, $file_errors);
        }
    } elseif ($leave_type === 'vacation') {
        $status = 'pending';

        if (empty($to_date)) {
            $errors[] = 'To date is required.';
        }

        if (empty($expected_duration)) {
            $errors[] = 'Duration is required for vacation leave.';
        }
    } else {
        $status = 'pending';

        if (empty($to_date)) {
            $errors[] = 'To date is required for casual leave.';
        }
    }

    if (empty($reason)) {
        $errors[] = 'Reason is required.';
    }

    if (empty($errors) && isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
        $proof = uploadFile($_FILES['proof']);
        if (!$proof) {
            $errors[] = 'Failed to upload proof file.';
        }
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO leave_requests (user_id, leave_type, from_date, to_date, expected_duration, reason, proof, is_medical, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            "issssssis",
            $user_id,
            $leave_type,
            $from_date,
            $to_date,
            $expected_duration,
            $reason,
            $proof,
            $is_medical,
            $status
        );

        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage('success', 'Leave application submitted successfully!');
            redirect('view_leaves.php');
        } else {
            $errors[] = 'Failed to submit leave application.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h2>Apply for Leave</h2>
            <p class="page-subtitle">HOD leave requests go to the Principal for approval.</p>
        </div>

        <div class="card">
            <?php if (!empty($errors)): ?>
            <div class="flash-message flash-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars(getDepartmentLabel($user['department'])); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><?php echo htmlspecialchars($identifier_label); ?></label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars(getIdentifierValue($user)); ?>" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label for="leave_type" class="required">Leave Type</label>
                    <select id="leave_type" name="leave_type" class="form-control" required onchange="toggleLeaveFields()">
                        <option value="">Select Leave Type</option>
                        <option value="casual">Casual Leave</option>
                        <option value="medical">Medical Leave</option>
                        <option value="on_duty">On-Duty Leave (Proof Required)</option>
                        <option value="academic">Academic Leave (Proof Required)</option>
                        <option value="vacation">Vacation Leave</option>
                    </select>
                </div>

                <div class="form-group checkbox-group" id="medical-checkbox-group">
                    <input type="checkbox" id="is_medical" name="is_medical" onchange="toggleLeaveFields()">
                    <label for="is_medical">Medical Leave (Open-ended until recovery)</label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="from_date" class="required">From Date</label>
                        <input type="date" id="from_date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date ?? ''); ?>" required>
                    </div>

                    <div class="form-group" id="to-date-group">
                        <label for="to_date" class="required">To Date</label>
                        <input type="date" id="to_date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group" id="expected-duration-group" style="display: none;">
                    <label for="expected_duration" class="required">Expected Duration</label>
                    <input type="text" id="expected_duration" name="expected_duration" class="form-control" placeholder="e.g., 1 week, 10 days" value="<?php echo htmlspecialchars($expected_duration ?? ''); ?>">
                    <div class="form-hint">Approximate recovery or leave duration</div>
                </div>

                <div class="form-group" id="proof-group" style="display: none;">
                    <label for="proof">Upload Proof</label>
                    <input type="file" id="proof" name="proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                    <div class="form-hint">Needed for on-duty and academic leave. Accepted formats: JPG, PNG, PDF (Max 5MB)</div>
                </div>

                <div class="form-group">
                    <label for="reason" class="required">Reason</label>
                    <textarea id="reason" name="reason" class="form-control" required><?php echo htmlspecialchars($reason ?? ''); ?></textarea>
                </div>

                <div class="action-group">
                    <button type="submit" class="btn btn-primary">Submit Application</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
function toggleLeaveFields() {
    const leaveType = document.getElementById('leave_type').value;
    const isMedical = document.getElementById('is_medical').checked;
    const medicalSelected = leaveType === 'medical' || isMedical;
    const proofNeeded = leaveType === 'on_duty' || leaveType === 'academic';
    const durationNeeded = medicalSelected || leaveType === 'vacation';
    const toDateNeeded = !medicalSelected;

    const toDateGroup = document.getElementById('to-date-group');
    const toDateInput = document.getElementById('to_date');
    const durationGroup = document.getElementById('expected-duration-group');
    const durationInput = document.getElementById('expected_duration');
    const proofGroup = document.getElementById('proof-group');
    const proofInput = document.getElementById('proof');

    if (medicalSelected) {
        document.getElementById('leave_type').value = 'medical';
        document.getElementById('is_medical').checked = true;
    }

    toDateGroup.style.display = toDateNeeded ? 'block' : 'none';
    toDateInput.required = toDateNeeded;
    if (!toDateNeeded) {
        toDateInput.value = '';
    }

    durationGroup.style.display = durationNeeded ? 'block' : 'none';
    durationInput.required = durationNeeded;
    if (!durationNeeded && leaveType !== 'vacation') {
        durationInput.value = '';
    }

    proofGroup.style.display = proofNeeded ? 'block' : 'none';
    proofInput.required = proofNeeded;
    if (!proofNeeded) {
        proofInput.value = '';
    }
}

document.getElementById('leave_type').addEventListener('change', toggleLeaveFields);
document.getElementById('is_medical').addEventListener('change', toggleLeaveFields);
toggleLeaveFields();
</script>

</body>
</html>

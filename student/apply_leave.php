<?php
$page_title = 'Apply Leave';
require_once __DIR__ . '/../includes/header.php';
checkRole('student');

$user_id = $_SESSION['user_id'];
$user = getUserById($conn, $user_id);
$identifier_label = getIdentifierLabel('student');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type = sanitize($_POST['leave_type'] ?? '');
    $from_date = sanitize($_POST['from_date'] ?? '');
    $to_date = sanitize($_POST['to_date'] ?? '');
    $expected_duration = sanitize($_POST['expected_duration'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');
    $is_medical = isset($_POST['is_medical']) ? 1 : 0;
    
    // Validation
    if (!in_array($leave_type, ['casual', 'medical'])) {
        $errors[] = 'Invalid leave type.';
    }
    
    if (empty($from_date)) {
        $errors[] = 'From date is required.';
    }
    
    // Medical leave logic
    if ($is_medical || $leave_type === 'medical') {
        $is_medical = 1;
        $leave_type = 'medical';
        $status = 'open';
        $to_date = null; // No end date for medical leave
        
        if (empty($expected_duration)) {
            $errors[] = 'Expected duration is required for medical leave.';
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
    
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, 
            "INSERT INTO leave_requests (user_id, leave_type, from_date, to_date, expected_duration, reason, is_medical, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "isssssis", 
            $user_id, $leave_type, $from_date, $to_date, $expected_duration, $reason, $is_medical, $status
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
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                    </div>
                    
                <div class="form-group">
                    <label><?php echo htmlspecialchars($identifier_label); ?></label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getIdentifierValue($user)); ?>" readonly>
                </div>
                </div>
                
                <div class="form-group">
                    <label for="leave_type" class="required">Leave Type</label>
                    <select id="leave_type" name="leave_type" class="form-control" required>
                        <option value="">Select Leave Type</option>
                        <option value="casual">Casual Leave</option>
                        <option value="medical">Medical Leave</option>
                    </select>
                </div>
                
                <div class="form-group checkbox-group" id="medical-checkbox-group">
                    <input type="checkbox" id="is_medical" name="is_medical" onchange="toggleMedicalFields()">
                    <label for="is_medical">Medical Leave (Open-ended until recovery)</label>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="from_date" class="required">From Date</label>
                        <input type="date" id="from_date" name="from_date" class="form-control" 
                               value="<?php echo $from_date ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group" id="to-date-group">
                        <label for="to_date" class="required">To Date</label>
                        <input type="date" id="to_date" name="to_date" class="form-control" 
                               value="<?php echo $to_date ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-group" id="expected-duration-group" style="display: none;">
                    <label for="expected_duration" class="required">Expected Duration</label>
                    <input type="text" id="expected_duration" name="expected_duration" class="form-control" 
                           placeholder="e.g., 1 week, 10 days" value="<?php echo $expected_duration ?? ''; ?>">
                    <div class="form-hint">Approximate expected recovery time</div>
                </div>
                
                <div class="form-group">
                    <label for="reason" class="required">Reason</label>
                    <textarea id="reason" name="reason" class="form-control" required><?php echo $reason ?? ''; ?></textarea>
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
document.getElementById('leave_type').addEventListener('change', function() {
    if (this.value === 'medical') {
        document.getElementById('is_medical').checked = true;
        toggleMedicalFields();
    }
});

function toggleMedicalFields() {
    const isMedical = document.getElementById('is_medical').checked;
    const toDateGroup = document.getElementById('to-date-group');
    const expectedDurationGroup = document.getElementById('expected-duration-group');
    const toDateInput = document.getElementById('to_date');
    const expectedDurationInput = document.getElementById('expected_duration');
    
    if (isMedical) {
        toDateGroup.style.display = 'none';
        toDateInput.required = false;
        toDateInput.value = '';
        expectedDurationGroup.style.display = 'block';
        expectedDurationInput.required = true;
        document.getElementById('leave_type').value = 'medical';
    } else {
        toDateGroup.style.display = 'block';
        toDateInput.required = true;
        expectedDurationGroup.style.display = 'none';
        expectedDurationInput.required = false;
    }
}
</script>

</body>
</html>

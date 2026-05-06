<?php
$page_title = 'Upload Proof';
require_once __DIR__ . '/../includes/header.php';
checkRole('student');

$user_id = $_SESSION['user_id'];
$user = getUserById($conn, $user_id);
$leave_id = intval($_GET['id'] ?? 0);
$errors = [];

// Get leave request
$stmt = mysqli_prepare($conn, "SELECT * FROM leave_requests WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $leave_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$leave = mysqli_fetch_assoc($result);

if (!$leave) {
    setFlashMessage('error', 'Leave request not found.');
    redirect('view_leaves.php');
}

if ($leave['status'] !== 'open') {
    setFlashMessage('error', 'Proof upload is only available for open medical leaves.');
    redirect('view_leaves.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_date = sanitize($_POST['to_date'] ?? '');
    
    if (empty($to_date)) {
        $errors[] = 'Return date is required.';
    }
    
    if (!isset($_FILES['proof']) || $_FILES['proof']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please upload proof document.';
    } else {
        $file_errors = validateFileUpload($_FILES['proof']);
        $errors = array_merge($errors, $file_errors);
    }
    
    if (empty($errors)) {
        $filename = uploadFile($_FILES['proof']);
        
        if ($filename) {
            $stmt = mysqli_prepare($conn, 
                "UPDATE leave_requests SET to_date = ?, proof = ?, status = 'closed' WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt, "ssi", $to_date, $filename, $leave_id);
            
            if (mysqli_stmt_execute($stmt)) {
                setFlashMessage('success', 'Proof uploaded successfully. Medical leave closed.');
                redirect('view_leaves.php');
            } else {
                $errors[] = 'Failed to update leave request.';
            }
        } else {
            $errors[] = 'Failed to upload file.';
        }
    }
}
?>

<div class="main-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="content-header">
            <h2>Upload Medical Proof</h2>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Profile</h3>
            </div>
            <div class="detail-row">
                <div class="detail-label">Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($user['name']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><?php echo htmlspecialchars(getIdentifierLabel('student')); ?>:</div>
                <div class="detail-value"><?php echo htmlspecialchars(getIdentifierValue($user)); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Department:</div>
                <div class="detail-value"><?php echo htmlspecialchars(getDepartmentLabel($user['department'])); ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Leave Details</h3>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Leave Type:</div>
                <div class="detail-value"><?php echo ucfirst($leave['leave_type']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">From Date:</div>
                <div class="detail-value"><?php echo formatDate($leave['from_date']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Expected Duration:</div>
                <div class="detail-value"><?php echo htmlspecialchars($leave['expected_duration']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Reason:</div>
                <div class="detail-value"><?php echo htmlspecialchars($leave['reason']); ?></div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Upload Proof & Close Leave</h3>
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
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="to_date" class="required">Actual Return Date</label>
                    <input type="date" id="to_date" name="to_date" class="form-control" required
                           min="<?php echo $leave['from_date']; ?>">
                    <div class="form-hint">The date you returned/recovered</div>
                </div>
                
                <div class="form-group">
                    <label for="proof" class="required">Upload Proof (Medical Certificate)</label>
                    <input type="file" id="proof" name="proof" class="form-control" 
                           accept=".jpg,.jpeg,.png,.pdf" required>
                    <div class="form-hint">Accepted formats: JPG, PNG, PDF (Max 5MB)</div>
                </div>
                
                <div class="action-group">
                    <button type="submit" class="btn btn-primary">Upload & Close Leave</button>
                    <a href="view_leaves.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>

</body>
</html>

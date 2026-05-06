<?php
$page_title = 'Upload Proof';
require_once __DIR__ . '/../includes/header.php';
checkRole('hod');

$user_id = $_SESSION['user_id'];
$user = getUserById($conn, $user_id);

$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM leave_requests
     WHERE user_id = ? AND status = 'open' AND leave_type = 'medical'
     ORDER BY created_at DESC"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$leaves = mysqli_stmt_get_result($stmt);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_id = intval($_POST['leave_id'] ?? 0);
    $to_date = sanitize($_POST['to_date'] ?? '');

    $stmt = mysqli_prepare($conn, "SELECT * FROM leave_requests WHERE id = ? AND user_id = ? AND status = 'open' AND leave_type = 'medical'");
    mysqli_stmt_bind_param($stmt, "ii", $leave_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $leave = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$leave) {
        $errors[] = 'Invalid medical leave request.';
    } elseif (empty($to_date)) {
        $errors[] = 'Return date is required.';
    } elseif (!isset($_FILES['proof']) || $_FILES['proof']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please upload proof document.';
    } else {
        $file_errors = validateFileUpload($_FILES['proof']);
        $errors = array_merge($errors, $file_errors);
    }

    if (empty($errors)) {
        $filename = uploadFile($_FILES['proof']);

        if ($filename) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE leave_requests SET to_date = ?, proof = ?, status = 'closed' WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt, "ssi", $to_date, $filename, $leave_id);

            if (mysqli_stmt_execute($stmt)) {
                setFlashMessage('success', 'Proof uploaded successfully. Medical leave closed.');
                redirect('view_leaves.php');
            } else {
                $errors[] = 'Failed to update leave request.';
            }
            mysqli_stmt_close($stmt);
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
            <p class="page-subtitle">HOD medical leaves are also reviewed by the Principal.</p>
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
                <div class="detail-label"><?php echo htmlspecialchars(getIdentifierLabel('hod')); ?>:</div>
                <div class="detail-value"><?php echo htmlspecialchars(getIdentifierValue($user)); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Department:</div>
                <div class="detail-value"><?php echo htmlspecialchars(getDepartmentLabel($user['department'])); ?></div>
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

        <?php if (mysqli_num_rows($leaves) > 0): ?>
        <?php while ($leave = mysqli_fetch_assoc($leaves)): ?>
        <div class="card">
            <div class="card-header">
                <h3><?php echo ucfirst(str_replace('_', ' ', $leave['leave_type'])); ?> Leave - <?php echo formatDate($leave['from_date']); ?></h3>
            </div>

            <div class="detail-row">
                <div class="detail-label">From:</div>
                <div class="detail-value"><?php echo formatDate($leave['from_date']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Expected Duration:</div>
                <div class="detail-value"><?php echo htmlspecialchars($leave['expected_duration'] ?: '-'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Reason:</div>
                <div class="detail-value"><?php echo htmlspecialchars($leave['reason']); ?></div>
            </div>

            <form method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
                <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">

                <div class="form-group">
                    <label for="to_date_<?php echo $leave['id']; ?>" class="required">Actual Return Date</label>
                    <input type="date" id="to_date_<?php echo $leave['id']; ?>" name="to_date" class="form-control" required min="<?php echo $leave['from_date']; ?>">
                    <div class="form-hint">The date you returned/recovered</div>
                </div>

                <div class="form-group">
                    <label for="proof_<?php echo $leave['id']; ?>" class="required">Upload Proof (Medical Certificate)</label>
                    <input type="file" id="proof_<?php echo $leave['id']; ?>" name="proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                    <div class="form-hint">Accepted formats: JPG, PNG, PDF (Max 5MB)</div>
                </div>

                <button type="submit" class="btn btn-primary">Upload Proof</button>
            </form>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <p>No medical leaves pending proof upload.</p>
                <a href="view_leaves.php" class="btn btn-secondary">View All Leaves</a>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

</body>
</html>

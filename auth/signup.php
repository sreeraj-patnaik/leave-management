<?php
include '../config/db.php';

$error = '';
$success = '';
$name = '';
$email = '';
$role = 'student';
$department = '';
$regdNo = '';
$employeeId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = $_POST['role'] ?? 'student';
        $department = trim($_POST['department'] ?? '');
        $regdNo = trim($_POST['regd_no'] ?? '');
        $employeeId = trim($_POST['emp_id'] ?? '');

        if ($name === '' || $email === '' || $password === '' || $department === '') {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!in_array($role, ['student', 'faculty'], true)) {
            $error = 'Invalid role selected.';
        } elseif ($role === 'student' && $regdNo === '') {
            $error = 'Registration number is required for students.';
        } elseif ($role !== 'student' && $employeeId === '') {
            $error = 'Employee ID is required for faculty.';
        } else {
            $dup = $pdo->prepare(
                'SELECT id FROM users WHERE email = ? OR (regd_no IS NOT NULL AND regd_no = ?) OR (emp_id IS NOT NULL AND emp_id = ?)'
            );
            $dup->execute([$email, $regdNo, $employeeId]);
            $existing = $dup->fetch();

            if ($existing) {
                $error = 'An account with these details already exists.';
            } else {
                $stmt = $pdo->prepare(
                    "SELECT id FROM users WHERE role = 'hod' AND department = ? LIMIT 1"
                );
                $stmt->execute([$department]);
                $hod = $stmt->fetch();

                if (!$hod) {
                    $error = 'No HOD found for this department.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $insert = $pdo->prepare(
                        'INSERT INTO users (name, email, password, role, department, regd_no, emp_id, status, approved_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)' 
                    );

                    $insert->execute([
                        $name,
                        $email,
                        $hash,
                        $role,
                        $department,
                        $role === 'student' ? $regdNo : null,
                        $role === 'student' ? null : $employeeId,
                        'pending',
                        null,
                    ]);

                    $success = 'Signup successful. Waiting for HOD approval.';
                    $name = $email = $department = $regdNo = $employeeId = '';
                    $role = 'student';
                }
            }
        }
    }
}

$token = generate_csrf_token();
?>
<?php include '../includes/header.php'; ?>

<div class="form-container">
    <div class="form-card">
        <div class="form-title">Create Account</div>
        <div class="form-subtitle">Student and faculty onboarding (HOD approval required).</div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h($token) ?>">

            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input name="name" class="form-control" value="<?= h($name) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input name="email" type="email" class="form-control" value="<?= h($email) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input name="password" type="password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
                    <option value="faculty" <?= $role === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Registration Number (Students)</label>
                <input name="regd_no" class="form-control" value="<?= h($regdNo) ?>" placeholder="Regd No">
            </div>

            <div class="mb-3">
                <label class="form-label">Employee ID (Faculty)</label>
                <input name="emp_id" class="form-control" value="<?= h($employeeId) ?>" placeholder="Employee ID">
            </div>

            <div class="mb-4">
                <label class="form-label">Department</label>
                <select name="department" class="form-select" required>
                    <option value="">Select Department</option>
                    <option value="cse" <?= $department === 'cse' ? 'selected' : '' ?>>CSE</option>
                    <option value="ece" <?= $department === 'ece' ? 'selected' : '' ?>>ECE</option>
                    <option value="eee" <?= $department === 'eee' ? 'selected' : '' ?>>EEE</option>
                    <option value="mec" <?= $department === 'mec' ? 'selected' : '' ?>>MEC</option>
                    <option value="scc" <?= $department === 'scc' ? 'selected' : '' ?>>SCC</option>
                    <option value="cit" <?= $department === 'cit' ? 'selected' : '' ?>>CIT</option>
                    <option value="csm" <?= $department === 'csm' ? 'selected' : '' ?>>CSM</option>
                </select>
            </div>

            <button type="submit" class="btn btn-main w-100">Submit for Approval</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


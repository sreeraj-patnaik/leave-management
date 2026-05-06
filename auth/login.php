<?php
include '../config/db.php';

if (!empty($_SESSION['user_id'])) {
    redirect('/index.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $passwordTrim = trim($password);

        if ($email === '' || $passwordTrim === '') {
            $error = 'Please provide both email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            $stmt = $pdo->prepare('SELECT id, password, role, name, status FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            $storedPassword = $user['password'] ?? '';
            $isHashed = $user && password_get_info($storedPassword)['algo'] !== 0;

            $passwordOk = $user && password_verify($password, $storedPassword);
            if (!$passwordOk && $user && !$isHashed) {
                $passwordOk = hash_equals($storedPassword, $passwordTrim) || hash_equals(trim($storedPassword), $passwordTrim);
            }

            if ($user && $passwordOk) {
                if (($user['status'] ?? 'approved') !== 'approved') {
                    $error = 'Your account is awaiting approval.';
                } else {
                    if (!$isHashed) {
                        $hash = password_hash($passwordTrim, PASSWORD_DEFAULT);
                        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                            ->execute([$hash, $user['id']]);
                    }

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'] ?? $email;

                    redirect('/index.php');
                }
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

$token = generate_csrf_token();
?>

<?php include '../includes/header.php'; ?>

<div class="login-shell">
    <div class="login-panel">
        <div class="panel-badge">Official Institute Portal</div>
        <h1>LIET Leave Management</h1>
        <p>Secure access for students, faculty, HOD, and principal roles. Every approval is logged for audit and compliance.</p>
        <div class="panel-meta">
            <div>
                <div class="meta-title">Campus</div>
                <div>Vizianagaram, Andhra Pradesh</div>
            </div>
            <div>
                <div class="meta-title">Support</div>
                <div>helpdesk@lendi.edu.in</div>
            </div>
        </div>
        <div class="panel-note">Use your institute credentials to continue.</div>
    </div>

    <div class="login-card">
        <h3 class="mb-3">Sign In</h3>
        <p class="text-muted mb-4">Authorized users only</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h($token) ?>">

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required value="<?= h($email) ?>" placeholder="name@lendi.edu.in">
            </div>

            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required placeholder="Enter password">
            </div>

            <button type="submit" class="btn btn-main w-100">Sign In</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

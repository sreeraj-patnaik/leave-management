<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');

session_start();

define('APP_ROOT', '/leave-management');

define('DB_DSN', 'mysql:host=localhost;dbname=leave_management;charset=utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo '<h1>Database connection failed.</h1>';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . APP_ROOT . $path);
    exit;
}

function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function get_final_approver_for_requester(PDO $pdo, array $requester): ?int
{
    if ($requester['role'] === 'student') {
        if (!empty($requester['assigned_faculty_id'])) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = ? LIMIT 1');
            $stmt->execute([(int) $requester['assigned_faculty_id'], 'faculty']);
            $faculty = $stmt->fetch();
            if ($faculty) {
                return (int) $faculty['id'];
            }
        }

        $facultyStmt = $pdo->prepare('SELECT id FROM users WHERE role = ? AND department = ? LIMIT 1');
        $facultyStmt->execute(['faculty', $requester['department']]);
        $faculty = $facultyStmt->fetch();

        return $faculty ? (int) $faculty['id'] : null;
    }

    if ($requester['role'] === 'faculty') {
        $hodStmt = $pdo->prepare('SELECT id FROM users WHERE role = ? AND department = ? LIMIT 1');
        $hodStmt->execute(['hod', $requester['department']]);
        $hod = $hodStmt->fetch();
        return $hod ? (int) $hod['id'] : null;
    }

    if ($requester['role'] === 'hod') {
        $principalStmt = $pdo->prepare('SELECT id FROM users WHERE role = ? LIMIT 1');
        $principalStmt->execute(['principal']);
        $principal = $principalStmt->fetch();
        return $principal ? (int) $principal['id'] : null;
    }

    return null;
}
?>
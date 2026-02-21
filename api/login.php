<?php
/**
 * Login API Endpoint
 * Handles user authentication and JWT token generation
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';
require_once __DIR__ . '/../auth_middleware.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required']);
    exit;
}

$username = trim($input['username']);
$password = $input['password'];

try {
    // Find user by username or email (remove is_active filter to check separately)
    $stmt = $pdo->prepare("
        SELECT id, username, email, password, full_name, role, is_active, two_fa_secret, two_fa_enabled 
        FROM users 
        WHERE (username = :username OR email = :username)
    ");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    // Verify password first
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    // Check if account is active (after password verification)
    if (!$user['is_active']) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Account Deactivated',
            'message' => 'your account has been deactivated contact your admin to activate'
        ]);
        exit;
    }

    // 2FA Verification
    if ($user['two_fa_enabled']) {
        if (!isset($input['code'])) {
            // 2FA is required but not provided
            echo json_encode([
                'success' => false,
                'require_2fa' => true,
                'message' => 'Two-factor authentication code required'
            ]);
            exit;
        }

        require_once __DIR__ . '/../lib/GoogleAuthenticator.php';
        $gauth = new GoogleAuthenticator();

        $code = trim((string) $input['code']);
        if (!$gauth->verifyCode($user['two_fa_secret'], $code)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid authentication code']);
            exit;
        }
    }

    // Update last login
    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
    $updateStmt->execute(['id' => $user['id']]);

    // Generate JWT token
    $payload = [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'full_name' => $user['full_name']
    ];

    $token = JWT::encode($payload, 24); // 24 hours expiry

    // Set cookie
    AuthMiddleware::setAuthCookie($token);

    // Return success response
    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
            'two_fa_enabled' => (bool) $user['two_fa_enabled']
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
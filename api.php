<?php
error_reporting(0);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Local XAMPP MySQL Settings
$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "reclaimx_db";
// ==================================================================
// 2. SMTP EMAIL CONFIGURATION (Gmail App Password Recommended)
// ==================================================================
$smtpHost     = "smtp.gmail.com";
$smtpPort     = 587;
$smtpUser     = "your-sender-email@gmail.com"; // Your Gmail address
$smtpPass     = "xxxx xxxx xxxx xxxx";         // 16-character Google App Password
$smtpFromName = "ReclaimX Campus Portal";
$allowedDomain = "@college.edu";               // Change to your actual college email domain
$adminPasscode = "admin2026";                  // Change before going live!

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database Connection Failed."]);
    exit();
}
$conn->set_charset("utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function getRequestBody() {
    $rawInput = file_get_contents('php://input');
    $json = json_decode($rawInput, true);
    return is_array($json) ? $json : $_POST;
}

function requireSessionUser() {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized access. Please log in."]);
        exit();
    }
    return $_SESSION['user'];
}

function purgeExpiredOTPs($conn) {
    $conn->query("DELETE FROM verifications WHERE expires_at < NOW()");
}

function sendOtpEmail($toEmail, $otp, $purpose, $smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFromName) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtpPort;

        $mail->setFrom($smtpUser, $smtpFromName);
        $mail->addAddress($toEmail);

        $isReset = ($purpose === 'forgot_password');
        $mail->isHTML(true);
        $mail->Subject = $isReset
            ? "Password Reset Verification Code - ReclaimX"
            : "Account Email Verification Code - ReclaimX";

        $titleText = $isReset ? "Password Reset Request" : "Verify Your Campus Email";
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 480px; margin: auto; padding: 24px; background: #0f172a; color: #f8fafc; border-radius: 12px;'>
                <h2 style='color: #818cf8; margin-top: 0;'>$titleText</h2>
                <p style='color: #cbd5e1; font-size: 14px;'>Use the 6-digit verification code below to continue on ReclaimX:</p>
                <div style='background: #1e293b; border: 1px solid #334155; padding: 16px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                    <span style='font-size: 28px; font-weight: bold; letter-spacing: 6px; color: #34d399;'>$otp</span>
                </div>
                <p style='color: #94a3b8; font-size: 12px;'>This code expires in 10 minutes. If you did not request this, please ignore this email.</p>
            </div>
        ";
        $mail->AltBody = "Your ReclaimX 6-digit OTP code is: $otp (expires in 10 minutes).";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ------------------------------------------------------------------
// OTP & AUTHENTICATION ENDPOINTS
// ------------------------------------------------------------------

// 1. REQUEST OTP (Register or Forgot/Change Password)
if ($action === 'send_otp' && $method === 'POST') {
    purgeExpiredOTPs($conn);

    $data = getRequestBody();
    $email = trim($data['email'] ?? '');
    $purpose = trim($data['purpose'] ?? 'register');

    if (empty($email)) {
        http_response_code(400);
        echo json_encode(["error" => "Email address is required."]);
        exit();
    }

    if (!str_ends_with(strtolower($email), strtolower($allowedDomain))) {
        http_response_code(400);
        echo json_encode(["error" => "Restricted to campus emails ($allowedDomain)."]);
        exit();
    }

    if ($purpose === 'forgot_password') {
        $checkUser = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkUser->bind_param("s", $email);
        $checkUser->execute();
        if ($checkUser->get_result()->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["error" => "No account found registered with this campus email."]);
            exit();
        }
    } elseif ($purpose === 'register') {
        $checkUser = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkUser->bind_param("s", $email);
        $checkUser->execute();
        if ($checkUser->get_result()->num_rows > 0) {
            http_response_code(400);
            echo json_encode(["error" => "Email address is already registered."]);
            exit();
        }
    }

    $otp = sprintf("%06d", mt_rand(100000, 999999));

    $del = $conn->prepare("DELETE FROM verifications WHERE email = ? AND purpose = ?");
    $del->bind_param("ss", $email, $purpose);
    $del->execute();

    $ins = $conn->prepare("INSERT INTO verifications (email, otp, purpose, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $ins->bind_param("sss", $email, $otp, $purpose);
    $ins->execute();

    $sent = sendOtpEmail($email, $otp, $purpose, $smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFromName);

    if ($sent) {
        echo json_encode([
            "success" => true,
            "message" => "Verification OTP sent to $email."
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to send verification email. Check SMTP settings."]);
    }
    exit();
}

// 2. REGISTER USER (WITH OTP VERIFICATION)
if ($action === 'register' && $method === 'POST') {
    purgeExpiredOTPs($conn);

    $data = getRequestBody();
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $rawPassword = $data['password'] ?? '';
    $otp = trim($data['otp'] ?? '');
    $adminSecret = trim($data['admin_secret'] ?? '');

    if (empty($name) || empty($email) || empty($rawPassword) || empty($otp)) {
        http_response_code(400);
        echo json_encode(["error" => "All fields including OTP verification code are required."]);
        exit();
    }

    $vStmt = $conn->prepare("SELECT id FROM verifications WHERE email = ? AND otp = ? AND purpose = 'register' AND expires_at > NOW()");
    $vStmt->bind_param("ss", $email, $otp);
    $vStmt->execute();
    if ($vStmt->get_result()->num_rows === 0) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid or expired OTP code."]);
        exit();
    }

    $role = ($adminSecret === $adminPasscode) ? 'admin' : 'user';
    $hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);

    if ($stmt->execute()) {
        $clearOtp = $conn->prepare("DELETE FROM verifications WHERE email = ? AND purpose = 'register'");
        $clearOtp->bind_param("s", $email);
        $clearOtp->execute();

        echo json_encode(["success" => true, "message" => "Account created successfully!"]);
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Email address is already registered."]);
    }
    exit();
}

// 3. FORGOT / CHANGE PASSWORD RESET VIA OTP
if ($action === 'reset_password_otp' && $method === 'POST') {
    purgeExpiredOTPs($conn);

    $data = getRequestBody();
    $email = trim($data['email'] ?? '');
    $otp = trim($data['otp'] ?? '');
    $newPassword = $data['new_password'] ?? '';

    if (empty($email) || empty($otp) || empty($newPassword)) {
        http_response_code(400);
        echo json_encode(["error" => "Email, OTP, and new password are required."]);
        exit();
    }

    $vStmt = $conn->prepare("SELECT id FROM verifications WHERE email = ? AND otp = ? AND purpose = 'forgot_password' AND expires_at > NOW()");
    $vStmt->bind_param("ss", $email, $otp);
    $vStmt->execute();
    if ($vStmt->get_result()->num_rows === 0) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid or expired verification OTP."]);
        exit();
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $upStmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $upStmt->bind_param("ss", $hashedPassword, $email);
    $upStmt->execute();

    $clearOtp = $conn->prepare("DELETE FROM verifications WHERE email = ? AND purpose = 'forgot_password'");
    $clearOtp->bind_param("s", $email);
    $clearOtp->execute();

    echo json_encode(["success" => true, "message" => "Password reset successfully! You can now log in."]);
    exit();
}

// 4. LOGIN
if ($action === 'login' && $method === 'POST') {
    $data = getRequestBody();
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        unset($user['password']);
        $_SESSION['user'] = $user;
        echo json_encode(["success" => true, "user" => $user]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Invalid email address or password."]);
    }
    exit();
}

// 5. LOGOUT
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    echo json_encode(["success" => true]);
    exit();
}

// 6. SESSION CHECK
if ($action === 'me') {
    echo json_encode($_SESSION['user'] ?? null);
    exit();
}

// 7. FETCH ALL USERS (ADMIN ONLY)
if ($action === 'users' && $method === 'GET') {
    $sessionUser = requireSessionUser();
    if ($sessionUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["error" => "Access denied."]);
        exit();
    }
    $res = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY id DESC");
    $users = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) $users[] = $row;
    }
    echo json_encode($users);
    exit();
}

// 8. UPDATE ROLE (ADMIN ONLY)
if ($action === 'update_user_role' && $method === 'PUT') {
    $sessionUser = requireSessionUser();
    if ($sessionUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["error" => "Access denied."]);
        exit();
    }
    $data = getRequestBody();
    $target_user_id = intval($data['user_id'] ?? 0);
    $new_role = ($data['role'] === 'admin') ? 'admin' : 'user';

    if ($target_user_id > 0) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $target_user_id);
        $stmt->execute();
        echo json_encode(["success" => true]);
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid User ID."]);
    }
    exit();
}

// ------------------------------------------------------------------
// ITEMS CRUD ENDPOINTS
// ------------------------------------------------------------------

// GET: Fetch all items
if ($method === 'GET') {
    $result = $conn->query("SELECT * FROM items ORDER BY created_at DESC, id DESC");
    $items = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    echo json_encode($items);
    exit();
}

// POST: Add new item (Requires Auth + Enforces Registered User Name & Email)
if ($method === 'POST') {
    $sessionUser = requireSessionUser();

    $data = $_POST;
    if (empty($data)) {
        $data = json_decode(file_get_contents("php://input"), true) ?? [];
    }

    if (empty($data['title'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required fields"]);
        exit();
    }

    $user_id = $sessionUser['id'];
    $type = $data['type'] ?? 'lost';
    $title = $data['title'];
    $item = !empty($data['item']) ? $data['item'] : ($data['category'] ?? 'General');
    $color = $data['color'] ?? 'N/A';
    $location = $data['location'] ?? 'Unknown';
    $date = $data['date'] ?? date('Y-m-d');

    $reporter_name = !empty($sessionUser['name']) ? $sessionUser['name'] : (!empty($data['reporter_name']) ? $data['reporter_name'] : 'Anonymous');
    $reporter_email = !empty($sessionUser['email']) ? $sessionUser['email'] : ($data['reporter_email'] ?? '');

    $reporter_phone = !empty($data['reporter_phone']) ? $data['reporter_phone'] : 'N/A';
    $description = $data['description'] ?? '';
    $image_url = '';

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $image_url = $destPath;
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO items (user_id, type, title, item, color, location, date, reporter_name, reporter_email, reporter_phone, description, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssssssss", $user_id, $type, $title, $item, $color, $location, $date, $reporter_name, $reporter_email, $reporter_phone, $description, $image_url);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "id" => $stmt->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => $stmt->error]);
    }
    $stmt->close();
    exit();
}

// PUT: Update status to resolved (Requires Auth)
if ($method === 'PUT') {
    $sessionUser = requireSessionUser();
    $data = getRequestBody();
    $id = intval($data['id'] ?? 0);

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE items SET status = 'resolved' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["success" => true]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $stmt->error]);
        }
        $stmt->close();
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid Item ID"]);
    }
    exit();
}

// DELETE: Delete record and attached image file (Owner or Admin only)
if ($method === 'DELETE') {
    $sessionUser = requireSessionUser();
    $data = getRequestBody();
    $id = intval($data['id'] ?? 0);

    $stmtSelect = $conn->prepare("SELECT user_id, image_url FROM items WHERE id = ?");
    $stmtSelect->bind_param("i", $id);
    $stmtSelect->execute();
    $post = $stmtSelect->get_result()->fetch_assoc();
    $stmtSelect->close();

    if (!$post) {
        http_response_code(404);
        echo json_encode(["error" => "Report not found"]);
        exit();
    }

    $isOwner = (intval($post['user_id']) === intval($sessionUser['id']));
    $isAdmin = ($sessionUser['role'] === 'admin');

    if ($isOwner || $isAdmin) {
        if (!empty($post['image_url']) && file_exists($post['image_url'])) {
            unlink($post['image_url']);
        }

        $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(["success" => true]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $stmt->error]);
        }
        $stmt->close();
    } else {
        http_response_code(403);
        echo json_encode(["error" => "Permission denied. Only post owners or admins can delete this report."]);
    }
    exit();
}

$conn->close();
?>
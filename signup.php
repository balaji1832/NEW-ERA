<?php
// ---------------- CORS ----------------
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(200);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// ---------------- POST Check ----------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit();
}

// ---------------- Read Input ----------------
$input = $_POST;
if (empty($input)) {
    $raw  = file_get_contents("php://input");
    $json = json_decode($raw, true);
    if (is_array($json)) $input = $json;
}

$email = trim($input['email'] ?? '');

// ---------------- Email Validation ----------------
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email address"]);
    exit();
}


// =========================================================
// ✅ DB INSERT : signup_list
// =========================================================

include './conn.php';

$stmt = $conn->prepare("INSERT INTO signup_list (email, created_at) VALUES (?, NOW())");
$stmt->bind_param("s", $email);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "DB insert failed: " . $stmt->error
    ]);
    exit();
}

$stmt->close();
$conn->close();


// =========================================================
// ✅ SEND TO GOOGLE SHEET (NON-BLOCKING)
// =========================================================

$googleScriptURL = "https://script.google.com/macros/s/AKfycbzUd2gSVt7XzeVAoKYbH9Nob2-X7dOpsMRLsQv6nK7dg8LXOGe4Cj2O5Bj3S-69MHU61A/exec";

$payload = [
    "email"        => $email,
    "submitted_at" => date("Y-m-d H:i:s")
];

try {
    $ch = curl_init($googleScriptURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_exec($ch);
    curl_close($ch);
} catch (Throwable $e) {
    // ignore Google error
}


// =========================================================
// ✅ SEND EMAILS USING PHPMailer
// =========================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './mailer/Exception.php';
require './mailer/PHPMailer.php';
require './mailer/SMTP.php';

// ---------------- ADMIN EMAIL ----------------
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'mail.ayatiworks.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'emailsmtp@ayatiworks.com';
    $mail->Password   = 'hYd@W,$nwNjC';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->setFrom('emailsmtp@ayatiworks.com', 'Newsletter Signup');
    $mail->addAddress('balaji@ayatiworks.com');
    $mail->addReplyTo($email);

    $mail->isHTML(true);
    $mail->Subject = "New Newsletter Signup";

    $mail->Body = "
<div style='max-width:650px;margin:auto;background:#ffffff;
            border:1px solid #e5e5e5;border-radius:8px;
            font-family:Arial;color:#333;'>

  <div style='background:#f7f7f7;padding:20px;text-align:center;border-bottom:1px solid #ddd;'>
      <img src='https://neweraengineers.ayatiworks.com/Images/logo.png' style='max-width:160px;margin-bottom:10px;' />
      <h2 style='margin:0;font-size:22px;'>New Newsletter Signup</h2>
  </div>

  <div style='padding:20px;'>
      <p style='font-size:16px;'>A new user has subscribed to the newsletter.</p>

      <p><strong>Email:</strong> ".htmlspecialchars($email)."</p>
      <p><strong>Time:</strong> ".date("Y-m-d H:i:s")."</p>
  </div>

  <div style='background:#1e1e1e;padding:15px;text-align:center;color:#fff;'>
      <p style='margin:0;font-size:13px;'>New Era Engineers & Traders<br>
      TTK Road, Alwarpet, Chennai – 600018</p>
  </div>

</div>";

    $mail->send();


    // ---------------- USER CONFIRMATION EMAIL ----------------
    $mail2 = new PHPMailer(true);
    $mail2->isSMTP();
    $mail2->Host       = 'mail.ayatiworks.com';
    $mail2->SMTPAuth   = true;
    $mail2->Username   = 'emailsmtp@ayatiworks.com';
    $mail2->Password   = 'hYd@W,$nwNjC';
    $mail2->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail2->Port       = 465;

    $mail2->setFrom('emailsmtp@ayatiworks.com', 'Newsletter Signup');
    $mail2->addAddress($email);

    $mail2->isHTML(true);
    $mail2->Subject = "Thank you for signing up!";

    $mail2->Body = "
<div style='max-width:600px;margin:auto;background:#ffffff;
            border:1px solid #e5e5e5;border-radius:8px;
            font-family:Arial;color:#333;'>

  <div style='background:#f7f7f7;padding:20px;text-align:center'>
      <img src='https://neweraengineers.com/Images/logo.png' style='max-width:150px;margin-bottom:10px;' />
      <h2 style='margin:0;font-size:22px;'>Thank You for Signing Up!</h2>
  </div>

  <div style='padding:25px;'>
      <p style='font-size:16px;'>Hello,</p>

      <p style='font-size:16px;'>Thank you for subscribing to our newsletter! You will soon receive updates and insights directly in your inbox.</p>

      <div style='margin:25px 0; padding:15px; background:#f1fcf1; border-left:4px solid #2ecc71;'>
          <p style='margin:0; font-size:15px; color:#2ecc71;'>
              ✔ Subscription Confirmed — You're now on our mailing list!
          </p>
      </div>
  </div>

  <div style='background:#1e1e1e;padding:15px;text-align:center;color:#fff;'>
      <p style='margin:0;font-size:13px;'>New Era Engineers & Traders<br>TTK Road, Chennai</p>
  </div>

</div>";

    $mail2->send();

    echo json_encode([
        "status"  => "success",
        "message" => "Signup successful! Confirmation email sent."
    ]);
    exit();

} catch (Exception $e) {
    echo json_encode([
        "status"  => "error",
        "message" => "Email failed: " . $e->getMessage()
    ]);
    exit();
}
?>

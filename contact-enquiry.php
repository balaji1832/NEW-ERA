<?php
// ---------- CORS (optional, safe for AJAX) ----------
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    http_response_code(200);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// ---------- DB Connection ----------
include './conn.php';

// ---------- PHPMailer ----------
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'mailer/Exception.php';
require 'mailer/PHPMailer.php';
require 'mailer/SMTP.php';

// ---------- Only POST allowed ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status"=>"error","message"=>"Invalid request method."]);
    exit();
}

// ---------- Read input ----------
$input = $_POST;
if (empty($input)) {
    $input = json_decode(file_get_contents("php://input"), true);
}

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"Invalid payload."]);
    exit();
}

// ---------- Helper ----------
function clean($v){
    global $conn;
    return mysqli_real_escape_string($conn, trim($v ?? ''));
}

// ---------- Mandatory ----------
$name     = clean($input['name'] ?? '');
$phone    = clean($input['phone'] ?? '');
$email    = clean($input['email'] ?? '');
$whatsapp = clean($input['whatsapp'] ?? '');
$company  = clean($input['company'] ?? '');
$source   = clean($input['source'] ?? '');

// ---------- Optional ----------
$liquidName      = clean($input['liquid_name'] ?? '');
$flowRate        = clean($input['flow_rate'] ?? '');
$dischargeHead   = clean($input['discharge_head'] ?? '');
$suctionCondition= clean($input['suction_condition'] ?? '');
$viscosity       = clean($input['viscosity'] ?? '');
$specificGravity = clean($input['specific_gravity'] ?? '');
$temperature     = clean($input['temperature'] ?? '');
$phValue         = clean($input['ph_value'] ?? '');

// ---------- Validate Mandatory ----------
if (
    empty($name) ||
    empty($phone) ||
    empty($email) ||
    empty($whatsapp) ||
    empty($company) ||
    empty($source)
) {
    http_response_code(400);
    echo json_encode([
        "status"=>"error",
        "message"=>"Name, Phone, Email, WhatsApp, Company & Source are required."
    ]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"Invalid email format."]);
    exit();
}

// ---------- Insert ----------
$sql = "INSERT INTO contact_enquiry
        (name, phone, email, whatsapp, company, source,
         liquid_name, flow_rate, discharge_head, suction_condition,
         viscosity, specific_gravity, temperature, ph_value, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ssssssssssssss",
    $name,
    $phone,
    $email,
    $whatsapp,
    $company,
    $source,
    $liquidName,
    $flowRate,
    $dischargeHead,
    $suctionCondition,
    $viscosity,
    $specificGravity,
    $temperature,
    $phValue
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>"Database insert failed: ".$stmt->error]);
    exit();
}

$stmt->close();


// -------------------------------------------------------
// SEND DATA TO GOOGLE SHEETS
// -------------------------------------------------------

$googleScriptURL = "https://script.google.com/macros/s/AKfycbzo_hVllzWVVMk7p1FFkylg5ehFtLWoreIOlMEluJ1N9iLB9Q8aiim3DknAgXJrfOb2vg/exec";

$sheetPayload = [
    "name"             => $name,
    "phone"            => $phone,
    "email"            => $email,
    "whatsapp"         => $whatsapp,
    "company"          => $company,
    "source"           => $source,
    "liquid_name"      => $liquidName,
    "flow_rate"        => $flowRate,
    "discharge_head"   => $dischargeHead,
    "suction_condition"=> $suctionCondition,
    "viscosity"        => $viscosity,
    "specific_gravity" => $specificGravity,
    "temperature"      => $temperature,
    "ph_value"         => $phValue,
    "submitted_at"     => date("Y-m-d H:i:s")
];

try {
    $ch = curl_init($googleScriptURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sheetPayload));
    $response = curl_exec($ch);
    curl_close($ch);
} catch (Throwable $e) {
    // Fail silently
}

// -------------------------------------------------------
// EMAIL TEMPLATE
// -------------------------------------------------------

$h = fn($v)=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');

$body = "
<div style='max-width:650px;margin:0 auto;background:#ffffff;
            border:1px solid #e5e5e5;border-radius:8px;
            font-family:Arial,Helvetica,sans-serif;color:#333;'>

  <div style='background:#f7f7f7;padding:15px;text-align:center;'>
      <img src='https://neweraengineers.ayatiworks.com/Images/logo.png'
           alt='New Era Engineers'
           style='max-width:180px;margin:10px auto;display:block;' />
      <h2 style='margin:5px 0 0;font-size:22px;font-weight:600;color:#333;'>
          New Contact Enquiry
      </h2>
  </div>

  <div style='padding:20px;'>
    <table cellpadding='10' cellspacing='0'
           style='width:100%;border-collapse:collapse;font-size:15px;'>

      <tr><td><strong>Name</strong></td><td>{$h($name)}</td></tr>
      <tr><td><strong>Phone</strong></td><td>{$h($phone)}</td></tr>
      <tr><td><strong>Email</strong></td><td>{$h($email)}</td></tr>
      <tr><td><strong>WhatsApp</strong></td><td>{$h($whatsapp)}</td></tr>
      <tr><td><strong>Company</strong></td><td>{$h($company)}</td></tr>
      <tr><td><strong>Source</strong></td><td>{$h($source)}</td></tr>

      ".(!empty($liquidName) ? "<tr><td><strong>Liquid Name</strong></td><td>{$h($liquidName)}</td></tr>" : "")."
      ".(!empty($flowRate) ? "<tr><td><strong>Flow Rate</strong></td><td>{$h($flowRate)}</td></tr>" : "")."
      ".(!empty($dischargeHead) ? "<tr><td><strong>Discharge Head</strong></td><td>{$h($dischargeHead)}</td></tr>" : "")."
      ".(!empty($suctionCondition) ? "<tr><td><strong>Suction Condition</strong></td><td>{$h($suctionCondition)}</td></tr>" : "")."
      ".(!empty($viscosity) ? "<tr><td><strong>Viscosity</strong></td><td>{$h($viscosity)}</td></tr>" : "")."
      ".(!empty($specificGravity) ? "<tr><td><strong>Specific Gravity</strong></td><td>{$h($specificGravity)}</td></tr>" : "")."
      ".(!empty($temperature) ? "<tr><td><strong>Temperature</strong></td><td>{$h($temperature)}</td></tr>" : "")."
      ".(!empty($phValue) ? "<tr><td><strong>PH Value</strong></td><td>{$h($phValue)}</td></tr>" : "")."
    </table>
  </div>

  <div style='background:#1e1e1e;padding:15px;text-align:center;color:#fff;'>
    <p style='margin:0;font-size:13px;'>New Era Engineers & Traders<br>No.96-A, TTK Road, Chennai 600018</p>
    <p style='margin-top:5px;font-size:12px;color:#ccc;'>".date('Y')." All Rights Reserved.</p>
  </div>

</div>
";

// ---------- Send Email ----------
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'mail.ayatiworks.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'emailsmtp@ayatiworks.com';
    $mail->Password   = 'hYd@W,$nwNjC';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->setFrom('emailsmtp@ayatiworks.com', 'Contact Enquiry');
    $mail->addAddress('balaji@ayatiworks.com');
    $mail->addReplyTo($email, $name);

    $mail->isHTML(true);
    $mail->Subject = "New Contact Enquiry - {$name}";
    $mail->Body    = $body;

    $mail->send();

    http_response_code(200);
    echo json_encode([
        "status"=>"success",
        "message"=>"Enquiry submitted successfully."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status"=>"error",
        "message"=>"Email failed: ".$mail->ErrorInfo
    ]);
}

$conn->close();
?>

<?php
// ---------- CORS (OPTIONS preflight) ----------
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    http_response_code(200);
    exit();
}

// ---------- CORS for actual responses ----------
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// ---------- Include database ----------
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
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit();
}

// ---------- Read input (FormData or JSON) ----------
$input = $_POST;
if (empty($input)) {
    $input = json_decode(file_get_contents("php://input"), true);
}

// ---------- Sanitize ----------
function clean($v) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($v ?? ''));
}

$contactPerson  = clean($input['contact_person'] ?? '');
$company        = clean($input['company'] ?? '');
$email          = clean($input['email'] ?? '');
$phone          = clean($input['phone'] ?? '');
$whatsapp       = clean($input['whatsapp'] ?? '');
$productName    = clean($input['product_name'] ?? '');
$modelNumber    = clean($input['model_number'] ?? '');
$serialNumber   = clean($input['serial_number'] ?? '');
$invoiceNumber  = clean($input['invoice_number'] ?? '');
$invoiceDate    = clean($input['invoice_date'] ?? '');
$issue          = clean($input['issue'] ?? '');

// ---------- Required validation (ONLY 5 mandatory fields) ----------
if (
    empty($contactPerson) ||
    empty($company)       ||
    empty($email)         ||
    empty($phone)         ||
    empty($whatsapp)
) {
    http_response_code(400);
    echo json_encode([
        "status"  => "error",
        "message" => "Name, Company, Email, Phone and WhatsApp are required."
    ]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email format."]);
    exit();
}

// ---------- INSERT INTO TABLE ----------
$sql = "INSERT INTO service_enquiry 
        (contact_person, company, email, phone, whatsapp, product_name, model_number, serial_number, invoice_number, invoice_date, issue, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "sssssssssss",
    $contactPerson,
    $company,
    $email,
    $phone,
    $whatsapp,
    $productName,
    $modelNumber,
    $serialNumber,
    $invoiceNumber,
    $invoiceDate,
    $issue
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database insert failed."]);
    exit();
}

// ---------- Send data to Google Sheets (non-blocking) ----------
$googleScriptURL = 'https://script.google.com/macros/s/AKfycbwX1V-XTGM5aGxEtbDpv9MmP8wHJR5nHKTO4kgMv3F8DfGlzzh3fSUf4vHguSWtGFbsfQ/exec'; // TODO: replace

// Build payload to send
$sheetPayload = [
    'contact_person' => $contactPerson,
    'company'        => $company,
    'email'          => $email,
    'phone'          => $phone,
    'whatsapp'       => $whatsapp,
    'product_name'   => $productName,
    'model_number'   => $modelNumber,
    'serial_number'  => $serialNumber,
    'invoice_number' => $invoiceNumber,
    'invoice_date'   => $invoiceDate,
    'issue'          => $issue,
    'submitted_at'   => date('Y-m-d H:i:s')
];

if (!empty($googleScriptURL)) {
    try {
        $ch = curl_init($googleScriptURL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Send as JSON (adjust Apps Script to parse JSON)
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sheetPayload));

        $sheetResponse = curl_exec($ch);
        $sheetHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // Optional: you can log $sheetHttpCode / $sheetResponse if needed
    } catch (\Throwable $th) {
        // Silently ignore sheet errors (don’t break user flow)
    }
}

// ---------- Email Body ----------
$body = "
<div style='max-width:650px;margin:0 auto;background:#ffffff;
            border:1px solid #e5e5e5;border-radius:8px;
            font-family:Arial,Helvetica,sans-serif;color:#333;'>

    <!-- Header -->
    <div style='background:#f7f7f7;padding:15px 20px;
                border-bottom:1px solid #e1e1e1;text-align:center;'>
        <img src='https://neweraengineers.ayatiworks.com/Images/logo.png' 
             alt='New Era Engineers' 
             style='max-width:180px;margin:10px auto;display:block;' />
        <h2 style='margin:5px 0 0 0;font-size:22px;font-weight:600;color:#333;'>
            New Service Enquiry
        </h2>
    </div>

    <!-- Content -->
    <div style='padding:20px;'>

        <table cellpadding='10' cellspacing='0' 
               style='width:100%;border-collapse:collapse;font-size:15px;'>

            <tr>
                <td style='background:#fafafa;width:35%;font-weight:bold;border:1px solid #eaeaea;'>Name</td>
                <td style='border:1px solid #eaeaea;'>{$contactPerson}</td>
            </tr>

            <tr>
                <td style='background:#fafafa;font-weight:bold;border:1px solid #eaeaea;'>Company</td>
                <td style='border:1px solid #eaeaea;'>{$company}</td>
            </tr>

            <tr>
                <td style='background:#fafafa;font-weight:bold;border:1px solid #eaeaea;'>Email</td>
                <td style='border:1px solid #eaeaea;'>{$email}</td>
            </tr>

            <tr>
                <td style='background:#fafafa;font-weight:bold;border:1px solid #eaeaea;'>Phone</td>
                <td style='border:1px solid #eaeaea;'>{$phone}</td>
            </tr>

            <tr>
                <td style='background:#fafafa;font-weight:bold;border:1px solid #eaeaea;'>WhatsApp</td>
                <td style='border:1px solid #eaeaea;'>{$whatsapp}</td>
            </tr>

            <!-- Optional fields visible only if filled -->
            ".(!empty($productName) ? "
            <tr>
                <td style='background:#fafafa;font-weight:bold;border:1px solid #eaeaea;'>Product Name</td>
                <td style='border:1px solid #eaeaea;'>{$productName}</td>
            </tr>" : "")."

            ".(!empty($modelNumber) ? "
            <tr>
                <td style='background:#fafafa;font-weight:bold;border:1px solid #eaeaea;'>Model Number</td>
                <td style='border:1px solid #eaeaea;'>{$modelNumber}</td>
            </tr>" : "")."

            ".(!empty($serialNumber) ? "
            <tr>
                <td style='background:#fafafa;font-weight:bold;border:1px solid #eaeaea;'>Serial Number</td>
                <td style='border:1px solid #eaeaea;'>{$serialNumber}</td>
            </tr>" : "")."

            ".(!empty($invoiceNumber) ? "
            <tr>
                <td style='background:#fafafa;font-weight:bold;border:1px solid #eaeaea;'>Tax Invoice No.</td>
                <td style='border:1px solid #eaeaea;'>{$invoiceNumber}</td>
            </tr>" : "")."

            ".(!empty($invoiceDate) ? "
            <tr>
                <td style='background:#fafafa;font-weight:bold;border:1px solid #eaeaea;'>Invoice Date</td>
                <td style='border:1px solid #eaeaea;'>{$invoiceDate}</td>
            </tr>" : "")."

            ".(!empty($issue) ? "
            <tr>
                <td style='background:#fafafa;font-weight:bold;border:1px solid #eaeaea;'>Issue</td>
                <td style='border:1px solid #eaeaea;'>{$issue}</td>
            </tr>" : "")."

        </table>

        <p style='margin-top:20px;font-size:13px;color:#777;'>
            <strong>Submitted on:</strong> ".date("Y-m-d H:i:s")."
        </p>
    </div>

    <!-- Footer -->
    <div style='background:#1e1e1e;padding:15px;text-align:center;
                color:#ffffff;border-top:1px solid #444;'>

        <p style='margin:0;font-size:13px;line-height:20px;'>
            New Era Engineers & Traders<br>
            No.96-A, TTK Road, Alwarpet, Chennai 600018, INDIA<br>
            Phone: +91-7200627289 • Email: sales@neweraengineers.com
        </p>
        
        <p style='margin-top:10px;font-size:12px;color:#ccc;'>
             ".date("Y")." New Era Engineers & Traders. All Rights Reserved.
        </p>
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

    $mail->setFrom('emailsmtp@ayatiworks.com', 'Service Enquiry');
    $mail->addAddress('balaji@ayatiworks.com');
    $mail->addReplyTo($email, $contactPerson);

    $mail->isHTML(true);
    $mail->Subject = "New Service Enquiry - $contactPerson";
    $mail->Body    = $body;

    $mail->send();

    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Thank you! Our team will reach you soon."]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Email could not be sent."]);
}

$stmt->close();
$conn->close();
?>

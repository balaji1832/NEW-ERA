<?php
// contact_save.php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
  exit;
}

function v($k){ return isset($_POST[$k]) ? trim($_POST[$k]) : ''; }
function len_between($s,$min,$max){ $l = mb_strlen($s,'UTF-8'); return $l >= $min && $l <= $max; }
function add_err(&$e,$k,$m){ $e[$k] = $m; }

$name      = v('name');
$phoneRaw  = v('phone');
$email     = v('email');
$waRaw     = v('whatsapp');
$company   = v('company');

$errors = [];

// Normalize phone-like fields: keep + and digits
$phone = preg_replace('/[^\d+]/','', $phoneRaw);
$wa    = preg_replace('/[^\d+]/','', $waRaw);
$digitsPhone = preg_replace('/\D/','', $phone);
$digitsWa    = preg_replace('/\D/','', $wa);

// name (required, 2–60, letters . - ’ space)
if (!len_between($name,2,60) || !preg_match("/^[A-Za-z.'\-\s]{2,60}$/u",$name)) {
  add_err($errors,'name','Only letters . - apostrophe space; 2–60 chars.');
}

// email (required, <=120)
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !len_between($email,1,120)) {
  add_err($errors,'email','Valid email (max 120 chars) is required.');
}

// phone (optional) 10–15 digits
if ($phone !== '') {
  if (!len_between($phone,1,20) || strlen($digitsPhone) < 10 || strlen($digitsPhone) > 15) {
    add_err($errors,'phone','Enter a valid phone (10–15 digits, may start with +).');
  }
}

// whatsapp (optional) 10–15 digits
if ($wa !== '') {
  if (!len_between($wa,1,20) || strlen($digitsWa) < 10 || strlen($digitsWa) > 15) {
    add_err($errors,'whatsapp','Enter a valid WhatsApp (10–15 digits, may start with +).');
  }
}

// identical numbers are allowed now. no check here.

// company (optional, 2–80, letters/numbers & . , ( ) - ’ space)
if ($company !== '' && (!len_between($company,2,80) || !preg_match("/^[A-Za-z0-9&().,'\-\s]{2,80}$/u",$company))) {
  add_err($errors,'company','Only letters/numbers & . , ( ) - apostrophe; 2–80 chars.');
}

if ($errors) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'errors'=>$errors]);
  exit;
}

// Insert
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 250);

$sql = "INSERT INTO contact_leads (name, phone, email, whatsapp, company, ip, user_agent)
        VALUES (?,?,?,?,?,?,?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Prepare failed']);
  exit;
}
$stmt->bind_param("sssssss", $name, $phone, $email, $wa, $company, $ip, $ua);

if ($stmt->execute()) {
  echo json_encode(['ok'=>true,'message'=>'Thanks! We will reach out shortly.']);
} else {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Database error']);
}
$stmt->close();
$conn->close();

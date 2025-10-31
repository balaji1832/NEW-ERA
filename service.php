<?php
// service.php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php'; // mysqli $conn

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Method not allowed','toast'=>['type'=>'error','title'=>'Method not allowed','message'=>'Use POST to submit the form.']]);
  exit;
}

/* ----------------- helpers ----------------- */
function val($k){ return isset($_POST[$k]) ? trim($_POST[$k]) : ''; }
function len_between($s, $min, $max){ $l = mb_strlen($s, 'UTF-8'); return $l >= $min && $l <= $max; }
function add_err(&$errs, $key, $msg){ $errs[$key] = $msg; }

/* ----------------- collect ----------------- */
$email         = val('email');
$company       = val('company');
$contact       = val('contact_person');
$phone_raw     = val('phone');
$whatsapp_raw  = val('whatsapp');
$product_name  = val('product_name');
$model_number  = val('model_number');
$serial_number = val('serial_number');
$invoice_num   = val('invoice_number');
$invoice_date  = val('invoice_date'); // yyyy-mm-dd
$issue         = val('issue');

/* ----------------- normalize ----------------- */
// allow + and digits only; keep + if present
$phone    = preg_replace('/[^\d+]/', '', $phone_raw);
$whatsapp = preg_replace('/[^\d+]/', '', $whatsapp_raw);
// pure digit lengths (for 10–15 rule)
$digitsPhone    = preg_replace('/\D/', '', $phone);
$digitsWhatsapp = preg_replace('/\D/', '', $whatsapp);

/* ----------------- validate (mirrors DB) ----------------- */
$errors = [];

// email VARCHAR(80)
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !len_between($email,1,80)) {
  add_err($errors, 'email', 'Valid email (max 80 chars) is required.');
}

// company VARCHAR(80) with the same pattern as the HTML
if (!len_between($company, 2, 80) || !preg_match("/^[A-Za-z0-9&().,'\-\s]{2,80}$/u", $company)) {
  add_err($errors, 'company', 'Only letters/numbers & . , ( ) - apostrophe; 2–80 chars.');
}

// contact_person VARCHAR(60)
if (!len_between($contact, 2, 60) || !preg_match("/^[A-Za-z.'\-\s]{2,60}$/u", $contact)) {
  add_err($errors, 'contact_person', 'Only letters . - apostrophe space; 2–60 chars.');
}

// phone / whatsapp VARCHAR(20) -> 10–15 digits allowed, optional +
if ($phone === '' || !len_between($phone, 1, 20) || strlen($digitsPhone) < 10 || strlen($digitsPhone) > 15) {
  add_err($errors, 'phone', 'Enter a valid phone (10–15 digits, may start with +).');
}
if ($whatsapp === '' || !len_between($whatsapp, 1, 20) || strlen($digitsWhatsapp) < 10 || strlen($digitsWhatsapp) > 15) {
  add_err($errors, 'whatsapp', 'Enter a valid WhatsApp (10–15 digits, may start with +).');
}
if ($digitsPhone !== '' && $digitsWhatsapp !== '' && $digitsPhone === $digitsWhatsapp) {
  add_err($errors, 'whatsapp', 'WhatsApp number should not be identical to Phone.');
}

// product_name VARCHAR(80)
if (!len_between($product_name, 2, 80)) {
  add_err($errors, 'product_name', 'Product name 2–80 chars.');
}

// model_number VARCHAR(40)
if ($model_number === '' || !len_between($model_number, 1, 40) || !preg_match("/^[A-Za-z0-9\-\/\s]{1,40}$/u", $model_number)) {
  add_err($errors, 'model_number', 'Model: letters/numbers/space - / ; max 40 chars.');
}

// serial_number VARCHAR(40)
if ($serial_number === '' || !len_between($serial_number, 3, 40) || !preg_match("/^[A-Za-z0-9\/\-\s]{3,40}$/u", $serial_number)) {
  add_err($errors, 'serial_number', 'Serial: letters/numbers/space - / ; 3–40 chars.');
}

// invoice_number VARCHAR(40)
if ($invoice_num === '' || !len_between($invoice_num, 3, 40) || !preg_match("/^[A-Za-z0-9\/\-\s]{3,40}$/u", $invoice_num)) {
  add_err($errors, 'invoice_number', 'Invoice No: letters/numbers/space - / ; 3–40 chars.');
}

// invoice_date DATE
if ($invoice_date === '') {
  add_err($errors, 'invoice_date', 'Invoice date is required.');
} else {
  $d = DateTime::createFromFormat('Y-m-d', $invoice_date);
  if (!$d || $d->format('Y-m-d') !== $invoice_date) {
    add_err($errors, 'invoice_date', 'Invalid invoice date (YYYY-MM-DD).');
  }
}

// issue VARCHAR(500)
if (!len_between($issue, 10, 500)) {
  add_err($errors, 'issue', 'Issue description must be 10–500 characters.');
}

if ($errors) {
  http_response_code(422);
  echo json_encode([
    'ok'=>false,
    'errors'=>$errors,
    'toast'=>['type'=>'error','title'=>'Validation failed','message'=>'Please correct the highlighted fields.']
  ]);
  exit;
}

/* ----------------- insert ----------------- */
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 250);

$sql = "INSERT INTO service_requests
 (email,company,contact_person,phone,whatsapp,product_name,model_number,serial_number,invoice_number,invoice_date,issue,ip,user_agent)
 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Prepare failed','toast'=>['type'=>'error','title'=>'Server error','message'=>'Failed to prepare statement.']]);
  exit;
}

$stmt->bind_param(
  "sssssssssssss",
  $email, $company, $contact, $phone, $whatsapp, $product_name, $model_number,
  $serial_number, $invoice_num, $invoice_date, $issue, $ip, $ua
);

if ($stmt->execute()) {
  echo json_encode([
    'ok'=>true,
    'message'=>'Saved',
    'toast'=>['type'=>'success','title'=>'Submitted','message'=>'Your request has been saved.']
  ]);
} else {
  // duplicate-ish hints (if you added UNIQUE indexes)
  $hint = 'Database error';
  if ($conn->errno === 1062) {
    // crude detector for which field might be duplicated (optional)
    $dupField = null;
    $msg = $conn->error ?: '';
    if (stripos($msg,'email') !== false)         $dupField = 'email';
    if (stripos($msg,'invoice_number') !== false)$dupField = 'invoice_number';
    if (stripos($msg,'serial_number') !== false) $dupField = 'serial_number';

    if ($dupField) {
      http_response_code(409);
      echo json_encode([
        'ok'=>false,
        'error'=> ucfirst(str_replace('_',' ',$dupField)).' already exists.',
        'errors'=> [$dupField => ucfirst(str_replace('_',' ',$dupField)).' already exists.'],
        'toast'=>['type'=>'error','title'=>'Duplicate','message'=>ucfirst(str_replace('_',' ',$dupField)).' already exists.']
      ]);
      $stmt->close(); $conn->close(); exit;
    }
    $hint = 'Duplicate entry';
  }

  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$hint,'toast'=>['type'=>'error','title'=>'Server error','message'=>$hint.'. Please try again.']]);
}

$stmt->close();
$conn->close();

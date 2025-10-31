<?php
$conn = new mysqli("localhost", "root", "", "newera");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$email = $_POST['email'];

$stmt = $conn->prepare("INSERT INTO sign_up (email, created_at) VALUES (?, now())");
$stmt->bind_param("s", $email);

if ($stmt->execute()) {
  echo "<script>alert('Thank you for signing up!'); window.location.href='index.html';</script>";
} else {
  echo "<script>alert('This email is already registered!'); window.location.href='index.html';</script>";
}

$stmt->close();
$conn->close();
?>

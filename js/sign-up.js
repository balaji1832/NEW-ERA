
function validateForm() {
  const email = document.getElementById("emailInput").value.trim();
  const gdpr = document.getElementById("gdpr").checked;

  if (email === "") {
    alert("Please enter your email.");
    return false;
  }

  if (!gdpr) {
    alert("Please accept GDPR terms.");
    return false;
  }

  return true;
}


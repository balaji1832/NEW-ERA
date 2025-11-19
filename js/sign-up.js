function showToast(message, type = "error") {
  const box = document.getElementById("toastBox");
  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  toast.innerText = message;
  box.appendChild(toast);

  setTimeout(() => {
    toast.remove();
  }, 3000);
}

async function validateForm(event) {
  event.preventDefault(); // stop normal submission

  const email = document.getElementById("emailInput");
  const gdpr = document.getElementById("gdpr");
  const responseBox = document.getElementById("responseMessage");

  responseBox.innerHTML = ""; // clear previous text

  if (!email || !gdpr) {
    showToast("Form error. Please refresh and try again.", "error");
    return false;
  }

  const emailValue = email.value.trim();
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  if (emailValue === "") {
    showToast("Please enter your email.", "error");
    email.focus();
    return false;
  }

  if (!emailPattern.test(emailValue)) {
    showToast("Please enter a valid email address.", "error");
    email.focus();
    return false;
  }

  if (!gdpr.checked) {
    showToast("Please accept GDPR terms.", "error");
    gdpr.focus();
    return false;
  }

  showToast("Submitting…", "success");

  // Submit using AJAX fetch
  const formData = new FormData(document.getElementById("signupForm"));

  try {
    const res = await fetch("signup.php", {
      method: "POST",
      body: formData
    });

    const data = await res.json();

    if (data.status === "success") {
      showToast(data.message, "success");
      responseBox.style.color = "green";
      responseBox.innerHTML = data.message;
      document.getElementById("signupForm").reset();
    } else {
      showToast(data.message, "error");
      responseBox.style.color = "red";
      responseBox.innerHTML = data.message;
    }

  } catch (err) {
    showToast("Network error. Try again.", "error");
    responseBox.style.color = "red";
    responseBox.innerHTML = "Network error. Try again.";
  }

  return false; // prevent normal submit
}


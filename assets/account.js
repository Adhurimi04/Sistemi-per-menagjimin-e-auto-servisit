const accountForm = document.getElementById("account-form");
const accountStatus = document.getElementById("account-status");

async function getCsrfToken() {
  const response = await fetch("backend/api/auth.php?action=csrf", {
    credentials: "same-origin",
  });
  const result = await response.json();
  if (!response.ok) {
    throw new Error(result.error || "Nuk u përgatit sesioni.");
  }
  return result.csrfToken;
}

async function submitAccountForm(event) {
  event.preventDefault();
  const button = accountForm.querySelector('button[type="submit"]');
  const originalText = button.textContent;
  button.disabled = true;
  button.textContent = "Duke procesuar...";
  accountStatus.textContent = "";
  accountStatus.dataset.state = "pending";

  try {
    const csrfToken = await getCsrfToken();
    const response = await fetch(
      `backend/api/auth.php?action=${encodeURIComponent(accountForm.dataset.action)}`,
      {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrfToken,
        },
        body: JSON.stringify(Object.fromEntries(new FormData(accountForm))),
      }
    );
    const result = await response.json();
    if (!response.ok) {
      throw new Error(result.error || "Kërkesa nuk u plotësua.");
    }
    window.location.assign("dashboard.php");
  } catch (error) {
    accountStatus.textContent = error.message;
    accountStatus.dataset.state = "error";
  } finally {
    button.disabled = false;
    button.textContent = originalText;
  }
}

accountForm.addEventListener("submit", submitAccountForm);

fetch("backend/api/auth.php?action=me", { credentials: "same-origin" })
  .then((response) => response.json())
  .then(({ user }) => {
    if (user) {
      window.location.replace("dashboard.php");
    }
  })
  .catch((error) => {
    console.error("Could not check the current Auto Pro session:", error);
  });

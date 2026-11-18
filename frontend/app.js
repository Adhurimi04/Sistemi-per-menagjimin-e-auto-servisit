// Close the mobile menu after selecting a navigation link.
document.querySelectorAll(".nav-links a").forEach((link) => {
  link.addEventListener("click", () => {
    document.getElementById("menu-toggle").checked = false;
  });
});

const dateInput = document.getElementById("date");
const today = new Date();
const localToday = new Date(today.getTime() - today.getTimezoneOffset() * 60000)
  .toISOString()
  .split("T")[0];
dateInput.min = localToday;

const bookingForm = document.getElementById("booking-form");
const formStatus = document.getElementById("form-status");
const submitButton = bookingForm.querySelector('button[type="submit"]');
const submitButtonText = submitButton.innerHTML;
const bookingAuthRequired = document.getElementById("booking-auth-required");
const serviceSelect = document.getElementById("service");
const apiBase = "../backend/api/";
let csrfToken;

const taskStatusLabels = {
  TODO: "Në pritje",
  IN_PROGRESS: "Në proces",
  DONE: "Përfunduar",
};

async function loadTasks() {
  const taskList = document.getElementById("task-list");

  try {
    const response = await fetch(`${apiBase}tasks.php`);
    const result = await response.json();
    if (!response.ok || !result.success || !Array.isArray(result.data)) {
      throw new Error(result.message || "Lista e detyrave nuk u ngarkua.");
    }

    if (!result.data.length) {
      taskList.replaceChildren(
        Object.assign(document.createElement("p"), {
          className: "empty-state",
          textContent: "Nuk ka detyra të regjistruara.",
        })
      );
      return;
    }

    const cards = result.data.map((task) => {
      const card = document.createElement("article");
      card.className = "task-card";

      const heading = document.createElement("div");
      heading.className = "task-card-heading";

      const title = document.createElement("h3");
      title.textContent = `#${task.id} – ${task.title}`;

      const status = document.createElement("span");
      status.className = `task-status task-status-${task.status}`;
      status.textContent = taskStatusLabels[task.status] || task.status;
      heading.append(title, status);

      const details = document.createElement("p");
      details.className = "booking-detail";
      details.textContent =
        `Vetura ID: ${task.vehicle_id} · Mekaniku: ${task.assigned_to || "Pa caktuar"}`;

      card.append(heading, details);
      return card;
    });
    taskList.replaceChildren(...cards);
  } catch (error) {
    console.error("Could not load service tasks:", error);
    taskList.replaceChildren(
      Object.assign(document.createElement("p"), {
        className: "empty-state",
        textContent:
          error instanceof Error ? error.message : "Lista e detyrave nuk u ngarkua.",
      })
    );
  }
}

loadTasks();

async function initializeBookingForm() {
  const [sessionResponse, servicesResponse, csrfResponse] = await Promise.all([
    fetch(`${apiBase}auth.php?action=me`, { credentials: "same-origin" }),
    fetch(`${apiBase}services.php`, { credentials: "same-origin" }),
    fetch(`${apiBase}auth.php?action=csrf`, { credentials: "same-origin" }),
  ]);
  const [sessionData, servicesData, csrfData] = await Promise.all([
    sessionResponse.json(),
    servicesResponse.json(),
    csrfResponse.json(),
  ]);
  if (!sessionResponse.ok || !servicesResponse.ok || !csrfResponse.ok) {
    throw new Error(
      sessionData.error ||
        servicesData.error ||
        csrfData.error ||
        "Nuk u ngarkuan të dhënat e rezervimit."
    );
  }

  csrfToken = csrfData.csrfToken;
  serviceSelect.replaceChildren(new Option("Zgjedh shërbimin", ""));
  for (const service of servicesData.services) {
    serviceSelect.add(new Option(service.name, service.id));
  }

  if (!sessionData.user) {
    bookingAuthRequired.hidden = false;
    return;
  }

  document.getElementById("name").value = sessionData.user.name;
  document.getElementById("phone").value = sessionData.user.phone;
  bookingAuthRequired.hidden = true;
  bookingForm.hidden = false;
}

initializeBookingForm().catch((error) => {
  console.error("Could not initialize booking form:", error);
  bookingAuthRequired.hidden = false;
  const notice = document.createElement("p");
  notice.className = "form-disclaimer";
  notice.textContent = "Formulari nuk u ngarkua. Rifresko faqen ose kyçu përsëri.";
  bookingAuthRequired.append(notice);
});

bookingForm.addEventListener("submit", async (event) => {
  event.preventDefault();

  const formData = new FormData(bookingForm);
  submitButton.disabled = true;
  submitButton.textContent = "Duke dërguar...";
  formStatus.textContent = "Rezervimi juaj po ruhet...";
  formStatus.dataset.state = "pending";

  try {
    const response = await fetch(`${apiBase}bookings.php`, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken,
      },
      body: JSON.stringify({
        service_id: Number(formData.get("service")),
        booking_date: formData.get("date"),
        vehicle: String(formData.get("car")).trim(),
        message: String(formData.get("message")).trim(),
      }),
    });
    const result = await response.json();
    if (!response.ok) {
      if (response.status === 401) {
        bookingAuthRequired.hidden = false;
        bookingForm.hidden = true;
      }
      throw new Error(result.error || "Rezervimi nuk u ruajt.");
    }

    let emailSent = false;
    if (window.emailjs) {
      try {
        await emailjs.send(
          "service_gm23eth",
          "template_bfr4xku",
          {
            to_email: "adhurimmehmetii2006@gmail.com",
            name: result.booking.name,
            email: result.booking.email,
            phone: result.booking.phone,
            service: result.booking.service,
            date: result.booking.date,
            car: result.booking.car,
            message: result.booking.message,
          },
          { publicKey: "E8qxRPZZ8vkyPirDz" }
        );
        emailSent = true;
      } catch (error) {
        console.error("EmailJS booking notification failed:", error);
      }
    }

    formStatus.textContent = emailSent
      ? `Faleminderit, ${result.booking.name}! Rezervimi u ruajt dhe njoftimi u dërgua me email.`
      : "Rezervimi u ruajt me sukses, por njoftimi me email nuk u dërgua. Mund ta shohësh te historiku në llogarinë tënde.";
    formStatus.dataset.state = "success";
    document.getElementById("date").value = "";
    document.getElementById("car").value = "";
    document.getElementById("message").value = "";
    dateInput.min = localToday;
  } catch (error) {
    console.error("Booking submission failed:", error);
    formStatus.textContent =
      error instanceof Error ? error.message : "Rezervimi nuk u dërgua. Provo përsëri.";
    formStatus.dataset.state = "error";
  } finally {
    submitButton.disabled = false;
    submitButton.innerHTML = submitButtonText;
  }
});

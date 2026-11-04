const API = {
  auth: "backend/api/auth.php",
  bookings: "backend/api/bookings.php",
  services: "backend/api/services.php",
  users: "backend/api/users.php",
};

const STATUS_LABELS = {
  pending: "Në pritje",
  confirmed: "E konfirmuar",
  in_progress: "Në proces",
  completed: "Përfunduar",
  cancelled: "Anuluar",
};

let currentUser;
let csrfToken;

function element(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

async function request(url, options = {}) {
  const headers = { ...(options.headers || {}) };
  if (options.method && options.method !== "GET") {
    headers["Content-Type"] = "application/json";
    headers["X-CSRF-Token"] = csrfToken;
  }
  const response = await fetch(url, {
    credentials: "same-origin",
    ...options,
    headers,
  });
  const result = await response.json();
  if (!response.ok) {
    if (response.status === 401) window.location.replace("login.php");
    throw new Error(result.error || "Kërkesa nuk u plotësua.");
  }
  return result;
}

function formatDate(value) {
  const date = new Date(`${value}T00:00:00`);
  return new Intl.DateTimeFormat("sq-XK", {
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(date);
}

function makeStat(label, value) {
  const card = element("article", "dashboard-stat");
  card.append(element("span", "", label), element("strong", "", String(value)));
  return card;
}

function renderStats(bookings) {
  const stats = document.getElementById("dashboard-stats");
  stats.replaceChildren();
  const pending = bookings.filter((booking) => booking.status === "pending").length;
  const active = bookings.filter((booking) =>
    ["confirmed", "in_progress"].includes(booking.status)
  ).length;
  stats.append(
    makeStat(currentUser.role === "admin" ? "Gjithsej rezervime" : "Rezervimet e tua", bookings.length),
    makeStat("Në pritje", pending),
    makeStat("Në proces", active)
  );
}

function appendDetail(card, label, value) {
  const row = element("p", "booking-detail");
  row.append(element("strong", "", `${label}: `), document.createTextNode(value || "—"));
  card.append(row);
}

function renderBookings(bookings) {
  renderStats(bookings);
  const list = document.getElementById("bookings-list");
  list.replaceChildren();
  if (!bookings.length) {
    list.append(element("p", "empty-state", "Nuk ka ende rezervime. Kërko termin tënd të parë më sipër."));
    return;
  }

  for (const booking of bookings) {
    const card = element("article", "booking-card");
    const heading = element("div", "booking-card-heading");
    const title = element("div");
    title.append(
      element("h3", "", booking.service_name),
      element("span", "booking-reference", `Rezervimi #${booking.id}`)
    );
    heading.append(title, element("span", `status-badge status-${booking.status}`, STATUS_LABELS[booking.status] || booking.status));
    card.append(heading);

    if (currentUser.role === "admin") {
      appendDetail(card, "Klienti", booking.customer_name);
      appendDetail(card, "Emaili", booking.customer_email);
      appendDetail(card, "Telefoni", booking.customer_phone);
    }
    appendDetail(card, "Data e preferuar", formatDate(booking.booking_date));
    appendDetail(card, "Vetura", booking.vehicle);
    appendDetail(card, "Kërkesa", booking.message);
    if (booking.admin_note) appendDetail(card, "Shënim nga servisi", booking.admin_note);
    appendDetail(card, "Krijuar më", new Date(booking.created_at).toLocaleString("sq-XK"));

    if (currentUser.role === "admin") {
      const controls = element("form", "booking-admin-controls");
      controls.dataset.bookingId = booking.id;
      const statusLabel = element("label", "", "Statusi");
      const status = element("select");
      status.name = "status";
      for (const [value, label] of Object.entries(STATUS_LABELS)) {
        const option = element("option", "", label);
        option.value = value;
        option.selected = booking.status === value;
        status.append(option);
      }
      statusLabel.append(status);
      const noteLabel = element("label", "", "Shënim për klientin");
      const note = element("textarea");
      note.name = "admin_note";
      note.rows = 2;
      note.maxLength = 3000;
      note.value = booking.admin_note || "";
      noteLabel.append(note);
      const save = element("button", "btn btn-primary", "Ruaj përditësimin");
      save.type = "submit";
      controls.append(statusLabel, noteLabel, save);
      card.append(controls);
    }
    list.append(card);
  }
}

function renderServices(services) {
  const select = document.getElementById("booking-service");
  select.replaceChildren(new Option("Zgjedh shërbimin", ""));
  for (const service of services.filter((item) => Number(item.active) === 1)) {
    select.add(new Option(service.name, service.id));
  }

  const adminList = document.getElementById("services-list");
  if (!adminList) return;
  adminList.replaceChildren();
  for (const service of services) {
    const row = element("div", "service-admin-row");
    const info = element("div");
    info.append(
      element("strong", "", service.name),
      element("p", "", service.description || "Pa përshkrim"),
      element("span", "service-state", Number(service.active) === 1 ? "Aktiv" : "Joaktiv")
    );
    const toggle = element("button", "btn btn-outline btn-small",
      Number(service.active) === 1 ? "Çaktivizo" : "Aktivizo");
    toggle.type = "button";
    toggle.dataset.serviceId = service.id;
    toggle.dataset.active = Number(service.active) === 1 ? "false" : "true";
    row.append(info, toggle);
    adminList.append(row);
  }
}

function renderUsers(users) {
  const container = document.getElementById("clients-list");
  container.replaceChildren();
  const table = element("table", "data-table");
  const head = element("thead");
  const headerRow = element("tr");
  for (const label of ["Klienti", "Emaili", "Telefoni", "Roli", "Regjistruar", "Veprimi"]) {
    headerRow.append(element("th", "", label));
  }
  head.append(headerRow);
  const body = element("tbody");
  for (const user of users) {
    const row = element("tr");
    for (const value of [
      user.name,
      user.email,
      user.phone,
      user.role === "admin" ? "Administrator" : "Klient",
      new Date(user.created_at).toLocaleDateString("sq-XK"),
    ]) {
      row.append(element("td", "", value));
    }
    const action = element("td");
    if (Number(user.id) === Number(currentUser.id)) {
      action.textContent = "Llogaria jote";
    } else {
      const role = element("select");
      role.dataset.userId = user.id;
      role.setAttribute("aria-label", `Roli i ${user.name}`);
      role.add(new Option("Klient", "client", false, user.role === "client"));
      role.add(new Option("Administrator", "admin", false, user.role === "admin"));
      action.append(role);
    }
    row.append(action);
    body.append(row);
  }
  table.append(head, body);
  container.append(table);
}

function setMessage(node, text, state) {
  node.textContent = text;
  node.dataset.state = state;
}

async function initializeDashboard() {
  const csrf = await fetch(`${API.auth}?action=csrf`, { credentials: "same-origin" });
  const csrfData = await csrf.json();
  if (!csrf.ok) throw new Error(csrfData.error || "Nuk u përgatit sesioni.");
  csrfToken = csrfData.csrfToken;

  const { user } = await request(`${API.auth}?action=me`);
  if (!user) {
    window.location.replace("login.php");
    return;
  }
  currentUser = user;
  document.getElementById("dashboard-name").textContent = user.name.split(" ")[0];
  document.getElementById("dashboard-role").textContent =
    user.role === "admin" ? "PANELI I ADMINISTRATORIT" : "LLOGARIA E KLIENTIT";
  document.getElementById("dashboard-intro").textContent =
    user.role === "admin"
      ? "Menaxho klientët, shërbimet dhe të gjitha rezervimet e autoservisit."
      : `Llogaria jote: ${user.email} · ${user.phone}. Kërko termin dhe ndiq përditësimet.`;
  document.getElementById("history-title").textContent =
    user.role === "admin" ? "Të gjitha rezervimet" : "Historiku yt";

  if (user.role === "admin") {
    document.getElementById("booking-panel").hidden = true;
    document.querySelectorAll(".admin-only").forEach((panel) => { panel.hidden = false; });
  }

  const [bookingsResult, servicesResult] = await Promise.all([
    request(API.bookings),
    request(API.services),
  ]);
  renderBookings(bookingsResult.bookings);
  renderServices(servicesResult.services);

  if (user.role === "admin") {
    const usersResult = await request(API.users);
    renderUsers(usersResult.users);
  }
}

document.getElementById("dashboard-booking-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const button = form.querySelector('button[type="submit"]');
  const status = document.getElementById("booking-status");
  button.disabled = true;
  setMessage(status, "Duke regjistruar rezervimin...", "pending");
  try {
    const payload = Object.fromEntries(new FormData(form));
    payload.service_id = Number(payload.service_id);
    const result = await request(API.bookings, {
      method: "POST",
      body: JSON.stringify(payload),
    });

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

    form.reset();
    setMessage(
      status,
      emailSent
        ? `${result.message} Njoftimi u dërgua edhe me email.`
        : `${result.message} Njoftimi me email nuk u dërgua; kërkesa është ruajtur në llogari.`,
      emailSent ? "success" : "pending"
    );
    const [bookingsResult] = await Promise.all([request(API.bookings)]);
    renderBookings(bookingsResult.bookings);
  } catch (error) {
    setMessage(status, error.message, "error");
  } finally {
    button.disabled = false;
  }
});

document.getElementById("bookings-list").addEventListener("submit", async (event) => {
  if (!event.target.matches(".booking-admin-controls")) return;
  event.preventDefault();
  const form = event.target;
  const button = form.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    await request(API.bookings, {
      method: "PATCH",
      body: JSON.stringify({
        id: Number(form.dataset.bookingId),
        status: form.elements.status.value,
        admin_note: form.elements.admin_note.value.trim(),
      }),
    });
    const { bookings } = await request(API.bookings);
    renderBookings(bookings);
    setMessage(document.getElementById("dashboard-status"), "Rezervimi u përditësua.", "success");
  } catch (error) {
    setMessage(document.getElementById("dashboard-status"), error.message, "error");
    button.disabled = false;
  }
});

document.getElementById("service-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const button = form.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    await request(API.services, {
      method: "POST",
      body: JSON.stringify(Object.fromEntries(new FormData(form))),
    });
    form.reset();
    const { services } = await request(API.services);
    renderServices(services);
    setMessage(document.getElementById("dashboard-status"), "Shërbimi u shtua.", "success");
  } catch (error) {
    setMessage(document.getElementById("dashboard-status"), error.message, "error");
  } finally {
    button.disabled = false;
  }
});

document.getElementById("services-list").addEventListener("click", async (event) => {
  const button = event.target.closest("button[data-service-id]");
  if (!button) return;
  button.disabled = true;
  try {
    await request(API.services, {
      method: "PATCH",
      body: JSON.stringify({
        id: Number(button.dataset.serviceId),
        active: button.dataset.active === "true",
      }),
    });
    const { services } = await request(API.services);
    renderServices(services);
  } catch (error) {
    setMessage(document.getElementById("dashboard-status"), error.message, "error");
    button.disabled = false;
  }
});

document.getElementById("clients-list").addEventListener("change", async (event) => {
  if (!event.target.matches("select[data-user-id]")) return;
  const select = event.target;
  select.disabled = true;
  try {
    await request(API.users, {
      method: "PATCH",
      body: JSON.stringify({ id: Number(select.dataset.userId), role: select.value }),
    });
    const { users } = await request(API.users);
    renderUsers(users);
  } catch (error) {
    setMessage(document.getElementById("dashboard-status"), error.message, "error");
    select.disabled = false;
  }
});

document.getElementById("logout-button").addEventListener("click", async (event) => {
  event.currentTarget.disabled = true;
  try {
    await request(`${API.auth}?action=logout`, {
      method: "POST",
      body: JSON.stringify({}),
    });
    window.location.replace("login.php");
  } catch (error) {
    setMessage(document.getElementById("dashboard-status"), error.message, "error");
    event.currentTarget.disabled = false;
  }
});

const bookingDate = document.getElementById("booking-date");
const now = new Date();
bookingDate.min = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
  .toISOString()
  .slice(0, 10);

initializeDashboard().catch((error) => {
  console.error("Could not load Auto Pro dashboard:", error);
  setMessage(document.getElementById("dashboard-status"), error.message, "error");
});

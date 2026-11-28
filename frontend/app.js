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
let currentUser;
let serviceTasks = [];
let serviceCatalog = [];

const taskForm = document.getElementById("task-form");
const taskFormStatus = document.getElementById("task-form-status");
const servicePreset = document.getElementById("service-preset");

const taskStatusLabels = {
  TODO: "Në pritje",
  IN_PROGRESS: "Në punë",
  DONE: "Përfunduar",
};

async function loadTasks() {
  const taskList = document.getElementById("task-list");

  try {
    const response = await fetch(`${apiBase}tasks.php`, { credentials: "same-origin" });
    const result = await response.json();
    if (!response.ok || !result.success || !Array.isArray(result.data)) {
      throw new Error(result.message || result.error || "Lista e detyrave nuk u ngarkua.");
    }

    serviceTasks = result.data;
    serviceCatalog = Array.isArray(result.catalog) ? result.catalog : [];
    populateServicePresets();
    renderTasks();
  } catch (error) {
    console.error("Could not load service tasks:", error);
    taskList.replaceChildren(
      Object.assign(document.createElement("p"), {
        className: "empty-state",
        textContent:
          error instanceof Error ? error.message : "Lista e detyrave nuk u ngarkua.",
      })
    );
    document.getElementById("task-count").textContent = "";
  }
}

function renderTasks() {
  const taskList = document.getElementById("task-list");
  const filter = document.getElementById("task-status-filter").value;
  const filteredTasks =
    filter === "all"
      ? serviceTasks
      : serviceTasks.filter((task) => task.status === filter);
  document.getElementById("task-count").textContent =
    `${filteredTasks.length} nga ${serviceTasks.length} detyra`;

  if (!filteredTasks.length) {
    taskList.replaceChildren(
      Object.assign(document.createElement("p"), {
        className: "empty-state",
        textContent: serviceTasks.length
          ? "Nuk ka detyra me këtë status."
          : "Nuk ka detyra të regjistruara.",
      })
    );
    return;
  }

  const cards = filteredTasks.map((task) => {
    const card = document.createElement("article");
    card.className = "task-card";

    const heading = document.createElement("div");
    heading.className = "task-card-heading";

    const title = document.createElement("h3");
    title.textContent = `#${task.id} – ${task.title}`;

    const isKnownStatus = Object.hasOwn(taskStatusLabels, task.status);
    const canUpdateStatus = currentUser.role === "admin" || currentUser.role === "mechanic";
    const status = document.createElement(isKnownStatus && canUpdateStatus ? "select" : "span");
    status.className = `task-status task-status-${task.status}`;
    if (isKnownStatus && canUpdateStatus) {
      status.dataset.taskId = task.id;
      status.title = "Zgjedh statusin e detyrës";
      status.setAttribute("aria-label", `Ndrysho statusin: ${taskStatusLabels[task.status]}`);
      for (const [value, label] of Object.entries(taskStatusLabels)) {
        status.add(new Option(label, value, false, value === task.status));
      }
    } else {
      status.textContent = taskStatusLabels[task.status] || task.status;
    }
    heading.append(title, status);

    const details = document.createElement("p");
    details.className = "booking-detail";
    details.textContent =
      `Vetura #${task.vehicle_id} · Mekaniku #${task.assigned_to || "Pa caktuar"}`;

    card.append(heading, details);

    const taskPrice = Number(task.price);
    const hasPrice = Number.isFinite(taskPrice) && taskPrice > 0;
    const price = document.createElement("p");
    price.className = hasPrice ? "task-price" : "task-price task-price-unset";
    price.textContent = hasPrice
      ? `${taskPrice.toFixed(2)} € (TVSH 18% e përfshirë)`
      : "Çmimi nuk është caktuar";
    card.append(price);

    const actions = document.createElement("div");
    actions.className = "task-card-actions";
    if (hasPrice) {
      const invoiceButton = document.createElement("button");
      invoiceButton.type = "button";
      invoiceButton.className = "task-invoice-button";
      invoiceButton.dataset.invoiceTaskId = task.id;
      invoiceButton.textContent = "Fatura PDF";
      invoiceButton.setAttribute("aria-label", `Shkarko faturën PDF për detyrën #${task.id}`);
      actions.append(invoiceButton);
    }

    if (currentUser.role === "admin") {
      const deleteButton = document.createElement("button");
      deleteButton.type = "button";
      deleteButton.className = "task-delete-button";
      deleteButton.dataset.deleteTaskId = task.id;
      deleteButton.textContent = "Fshi detyrën";
      deleteButton.setAttribute("aria-label", `Fshi detyrën #${task.id}`);
      actions.append(deleteButton);
    }
    card.append(actions);
    return card;
  });
  taskList.replaceChildren(...cards);
}

function populateServicePresets() {
  const options = serviceCatalog.map((service) => {
    const price = Number(service.default_price);
    return new Option(
      `${service.service_name} (${price.toFixed(2)} €)`,
      String(service.id)
    );
  });
  servicePreset.replaceChildren(new Option("Shërbim me porosi", ""), ...options);
}

function downloadInvoicePDF(task) {
  const JsPDF = window.jspdf && window.jspdf.jsPDF;
  if (!JsPDF) {
    throw new Error("Libraria e faturës PDF nuk u ngarkua. Kontrollo lidhjen me internetin.");
  }

  const price = Number(task.price);
  if (!Number.isFinite(price) || price <= 0) {
    throw new Error("Çmimi i detyrës nuk është i vlefshëm.");
  }

  const doc = new JsPDF();
  if (typeof doc.autoTable !== "function") {
    throw new Error("Tabela e faturës PDF nuk u ngarkua.");
  }

  const vatRate = 0.18;
  const netAmount = price / (1 + vatRate);
  const vatAmount = price - netAmount;
  const today = new Intl.DateTimeFormat("sq-XK").format(new Date());
  const invoiceNumber = `AP-${new Date().getFullYear()}-${String(task.id).padStart(4, "0")}`;

  doc.setFillColor(17, 24, 39);
  doc.rect(0, 0, 210, 42, "F");
  doc.setFillColor(239, 68, 68);
  doc.rect(0, 42, 210, 2.5, "F");
  doc.setFillColor(239, 68, 68);
  doc.roundedRect(15, 9, 18, 18, 3, 3, "F");
  doc.setTextColor(255, 255, 255);
  doc.setFont("helvetica", "bold");
  doc.setFontSize(11);
  doc.text("AP", 24, 21, { align: "center" });
  doc.setFontSize(22);
  doc.text("AUTO PRO.", 39, 20);
  doc.setFont("helvetica", "normal");
  doc.setFontSize(9.5);
  doc.setTextColor(209, 213, 219);
  doc.text("Autoservis & Vulkanizer Profesional | Prizren, Kosovë", 15, 32);
  doc.text("Tel: +383 45 916 042 | Web: www.autopro-pz.com", 15, 38);

  doc.setTextColor(255, 255, 255);
  doc.setFont("helvetica", "bold");
  doc.setFontSize(18);
  doc.text("FATURË SERVISI", 195, 16, { align: "right" });
  doc.setFont("helvetica", "normal");
  doc.setFontSize(9);
  doc.setTextColor(252, 165, 165);
  doc.text(`Nr. faturës: ${invoiceNumber}`, 195, 25, { align: "right" });
  doc.setTextColor(209, 213, 219);
  doc.text(`Data: ${today}`, 195, 33, { align: "right" });

  doc.setFillColor(248, 250, 252);
  doc.setDrawColor(226, 232, 240);
  doc.roundedRect(15, 52, 180, 28, 3, 3, "FD");
  doc.setTextColor(30, 41, 59);
  doc.setFont("helvetica", "bold");
  doc.setFontSize(10);
  doc.text("TË DHËNAT E VETURËS DHE SERVISIT", 20, 60);
  doc.setFont("helvetica", "normal");
  doc.text(`ID e veturës: #${task.vehicle_id}`, 20, 70);
  doc.text(`Mekaniku: ${task.assigned_to || "Stafi Auto Pro"}`, 120, 70);
  doc.text(`Statusi: ${taskStatusLabels[task.status] || task.status}`, 20, 76);
  doc.text("Monedha: EUR (€)", 120, 76);

  doc.autoTable({
    startY: 88,
    head: [["Nr.", "Përshkrimi i shërbimit", "Sasia", "Çmimi", "Totali"]],
    body: [[
      "1",
      task.title,
      "1",
      `${price.toFixed(2)} €`,
      `${price.toFixed(2)} €`,
    ]],
    theme: "grid",
    headStyles: {
      fillColor: [30, 41, 59],
      textColor: [255, 255, 255],
      fontStyle: "bold",
      fontSize: 9,
    },
    bodyStyles: {
      textColor: [15, 23, 42],
      fontSize: 9,
      cellPadding: 4,
    },
    columnStyles: {
      0: { halign: "center" },
      2: { halign: "center" },
      3: { halign: "right" },
      4: { halign: "right", fontStyle: "bold" },
    },
  });

  let summaryY = doc.lastAutoTable.finalY + 10;
  if (summaryY > 220) {
    doc.addPage();
    summaryY = 25;
  }
  doc.setFillColor(248, 250, 252);
  doc.setDrawColor(203, 213, 225);
  doc.roundedRect(115, summaryY, 80, 38, 2, 2, "FD");
  doc.setFont("helvetica", "normal");
  doc.setFontSize(9);
  doc.setTextColor(71, 85, 105);
  doc.text("Vlera pa TVSH:", 120, summaryY + 8);
  doc.text(`${netAmount.toFixed(2)} €`, 190, summaryY + 8, { align: "right" });
  doc.text("TVSH (18%):", 120, summaryY + 16);
  doc.text(`${vatAmount.toFixed(2)} €`, 190, summaryY + 16, { align: "right" });
  doc.setDrawColor(239, 68, 68);
  doc.line(120, summaryY + 20, 190, summaryY + 20);
  doc.setFont("helvetica", "bold");
  doc.setFontSize(11);
  doc.setTextColor(17, 24, 39);
  doc.text("TOTALI PËR PAGESË:", 120, summaryY + 30);
  doc.setTextColor(220, 38, 38);
  doc.text(`${price.toFixed(2)} €`, 190, summaryY + 30, { align: "right" });

  const signatureY = summaryY + 58;
  if (signatureY < 270) {
    doc.setDrawColor(148, 163, 184);
    doc.line(20, signatureY, 80, signatureY);
    doc.line(130, signatureY, 190, signatureY);
    doc.setFont("helvetica", "normal");
    doc.setFontSize(8);
    doc.setTextColor(100, 116, 139);
    doc.text("Nënshkrimi & Vula (Auto Pro)", 50, signatureY + 5, { align: "center" });
    doc.text("Nënshkrimi i klientit", 160, signatureY + 5, { align: "center" });
  }
  doc.setFontSize(8);
  doc.text(
    "Faleminderit që zgjodhët Auto Pro Prizren! Faturë elektronike.",
    105,
    285,
    { align: "center" }
  );
  doc.save(`Fatura_${invoiceNumber}_Vetura_${task.vehicle_id}.pdf`);
}

function showTaskLoginNotice() {
  const taskList = document.getElementById("task-list");
  const notice = document.createElement("p");
  notice.className = "empty-state";
  notice.append(
    document.createTextNode("Kyçu për të parë detyrat e servisit. "),
    Object.assign(document.createElement("a"), {
      href: "../login.php",
      textContent: "Kyçu",
    })
  );
  taskList.replaceChildren(notice);
  document.getElementById("task-count").textContent = "";
}

async function initializeTasks() {
  try {
    const [sessionResponse, csrfResponse] = await Promise.all([
      fetch(`${apiBase}auth.php?action=me`, { credentials: "same-origin" }),
      fetch(`${apiBase}auth.php?action=csrf`, { credentials: "same-origin" }),
    ]);
    const [sessionData, csrfData] = await Promise.all([
      sessionResponse.json(),
      csrfResponse.json(),
    ]);
    if (!sessionResponse.ok || !csrfResponse.ok) {
      throw new Error(sessionData.error || csrfData.error || "Sesioni nuk u ngarkua.");
    }
    currentUser = sessionData.user;
    csrfToken = csrfData.csrfToken;
    if (!currentUser) {
      showTaskLoginNotice();
      return;
    }
    if (currentUser.role === "admin" || currentUser.role === "mechanic") {
      taskForm.hidden = false;
      if (currentUser.vehicle_id) {
        document.getElementById("task-vehicle-id").value = currentUser.vehicle_id;
      }
    }
    await loadTasks();
  } catch (error) {
    console.error("Could not initialize service tasks:", error);
    const taskList = document.getElementById("task-list");
    taskList.replaceChildren(
      Object.assign(document.createElement("p"), {
        className: "empty-state",
        textContent: error instanceof Error ? error.message : "Lista e detyrave nuk u ngarkua.",
      })
    );
    document.getElementById("task-count").textContent = "";
  }
}

initializeTasks();

document.getElementById("task-status-filter").addEventListener("change", renderTasks);

servicePreset.addEventListener("change", () => {
  const service = serviceCatalog.find((item) => String(item.id) === servicePreset.value);
  if (!service) return;
  document.getElementById("task-title").value = service.service_name;
  document.getElementById("task-price").value = Number(service.default_price).toFixed(2);
});

taskForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const submit = taskForm.querySelector('button[type="submit"]');
  const formData = new FormData(taskForm);
  submit.disabled = true;
  taskFormStatus.textContent = "Detyra po ruhet...";
  taskFormStatus.dataset.state = "pending";
  try {
    const assignedTo = String(formData.get("assigned_to")).trim();
    const response = await fetch(`${apiBase}tasks.php`, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken,
      },
      body: JSON.stringify({
        title: String(formData.get("title")).trim(),
        vehicle_id: Number(formData.get("vehicle_id")),
        assigned_to: assignedTo ? Number(assignedTo) : null,
        price: String(formData.get("price")),
      }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || result.error || "Detyra nuk u ruajt.");
    }
    taskForm.reset();
    taskFormStatus.textContent = result.message;
    taskFormStatus.dataset.state = "success";
    await loadTasks();
  } catch (error) {
    console.error("Could not create service task:", error);
    taskFormStatus.textContent =
      error instanceof Error ? error.message : "Gabim gjatë ruajtjes së detyrës.";
    taskFormStatus.dataset.state = "error";
  } finally {
    submit.disabled = false;
  }
});

document.getElementById("task-list").addEventListener("click", async (event) => {
  const invoiceButton = event.target.closest("button[data-invoice-task-id]");
  if (invoiceButton) {
    const task = serviceTasks.find(
      (item) => Number(item.id) === Number(invoiceButton.dataset.invoiceTaskId)
    );
    if (!task) return;
    try {
      downloadInvoicePDF(task);
    } catch (error) {
      document.getElementById("task-update-status").textContent =
        error instanceof Error ? error.message : "Fatura PDF nuk u krijua.";
      document.getElementById("task-update-status").dataset.state = "error";
    }
    return;
  }

  const deleteButton = event.target.closest("button[data-delete-task-id]");
  if (deleteButton) {
    const id = Number(deleteButton.dataset.deleteTaskId);
    if (!window.confirm(`A jeni i sigurt që dëshironi ta fshini detyrën #${id}?`)) {
      return;
    }

    const updateStatus = document.getElementById("task-update-status");
    deleteButton.disabled = true;
    updateStatus.textContent = "Duke fshirë detyrën...";
    updateStatus.dataset.state = "pending";
    try {
      const response = await fetch(`${apiBase}tasks.php`, {
        method: "DELETE",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrfToken,
        },
        body: JSON.stringify({ id }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || result.error || "Detyra nuk u fshi.");
      }

      updateStatus.textContent = result.message;
      updateStatus.dataset.state = "success";
      await loadTasks();
    } catch (error) {
      console.error("Could not delete service task:", error);
      updateStatus.textContent =
        error instanceof Error ? error.message : "Gabim gjatë fshirjes së detyrës.";
      updateStatus.dataset.state = "error";
    } finally {
      if (deleteButton.isConnected) deleteButton.disabled = false;
    }
    return;
  }

});

document.getElementById("task-list").addEventListener("change", async (event) => {
  const select = event.target.closest("select[data-task-id]");
  if (!select) return;

  const task = serviceTasks.find((item) => Number(item.id) === Number(select.dataset.taskId));
  const nextStatus = select.value;
  if (!task || !Object.hasOwn(taskStatusLabels, nextStatus) || nextStatus === task.status) return;

  const updateStatus = document.getElementById("task-update-status");
  select.disabled = true;
  updateStatus.textContent = "Duke përditësuar statusin...";
  updateStatus.dataset.state = "pending";

  try {
    const response = await fetch(`${apiBase}tasks.php`, {
      method: "PUT",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken,
      },
      body: JSON.stringify({
        id: Number(select.dataset.taskId),
        status: nextStatus,
      }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || result.error || "Statusi nuk u përditësua.");
    }

    updateStatus.textContent = result.message;
    updateStatus.dataset.state = "success";
    await loadTasks();
  } catch (error) {
    console.error("Could not update service task status:", error);
    select.value = task.status;
    updateStatus.textContent =
      error instanceof Error ? error.message : "Gabim gjatë ndryshimit të statusit.";
    updateStatus.dataset.state = "error";
  } finally {
    if (select.isConnected) select.disabled = false;
  }
});

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

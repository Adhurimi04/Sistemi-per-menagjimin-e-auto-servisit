<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
app_start_session();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Paneli AUTO PRO për rezervime, shërbime dhe historikun e servisit.">
  <title>Paneli | AUTO PRO</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">
  <header class="header">
    <nav class="navbar container">
      <a href="index.html" class="logo">
        <span class="logo-icon">AP</span>
        <span>AUTO <strong>PRO</strong><small>AUTOSERVICE &amp; DIAGNOSTICS</small></span>
      </a>
      <div class="dashboard-nav">
        <a href="index.html">Faqja kryesore</a>
        <button type="button" class="logout-button" id="logout-button">Dil nga llogaria</button>
      </div>
    </nav>
  </header>
  <main class="container dashboard-main">
    <section class="dashboard-heading">
      <span class="eyebrow" id="dashboard-role">PANELI AUTO PRO</span>
      <h1>Përshëndetje, <span id="dashboard-name">...</span></h1>
      <p class="dashboard-lead" id="dashboard-intro">Po ngarkohen të dhënat e llogarisë.</p>
    </section>

    <section class="dashboard-stats" id="dashboard-stats" aria-label="Përmbledhje"></section>

    <section class="dashboard-panel" id="booking-panel">
      <div class="panel-heading">
        <div><span class="eyebrow">SHËRBIMI YT I RADHËS</span><h2>Kërko një termin</h2></div>
      </div>
      <form id="dashboard-booking-form" class="dashboard-booking-form">
        <div class="form-row">
          <div class="form-group">
            <label for="booking-service">Shërbimi</label>
            <select id="booking-service" name="service_id" required><option value="">Duke ngarkuar...</option></select>
          </div>
          <div class="form-group">
            <label for="booking-date">Data e preferuar</label>
            <input id="booking-date" name="booking_date" type="date" required>
          </div>
        </div>
        <div class="form-group">
          <label for="booking-vehicle">Vetura</label>
          <input id="booking-vehicle" name="vehicle" type="text" maxlength="160" placeholder="p.sh. VW Golf 7">
        </div>
        <div class="form-group">
          <label for="booking-message">Përshkrimi (opsional)</label>
          <textarea id="booking-message" name="message" rows="3" maxlength="3000" placeholder="Përshkruaj shkurt problemin ose kërkesën..."></textarea>
        </div>
        <button class="btn btn-primary" type="submit">Dërgo kërkesën <span>↗</span></button>
        <p id="booking-status" class="form-disclaimer" role="status" aria-live="polite"></p>
      </form>
    </section>

    <section class="dashboard-panel">
      <div class="panel-heading">
        <div><span class="eyebrow">REZERVIMET</span><h2 id="history-title">Historiku yt</h2></div>
      </div>
      <div id="bookings-list" class="bookings-list" aria-live="polite"></div>
    </section>

    <section class="dashboard-panel admin-only" id="clients-panel" hidden>
      <div class="panel-heading">
        <div><span class="eyebrow">KLIENTËT</span><h2>Llogaritë e klientëve</h2></div>
      </div>
      <div class="table-wrap" id="clients-list"></div>
    </section>

    <section class="dashboard-panel admin-only" id="services-panel" hidden>
      <div class="panel-heading">
        <div><span class="eyebrow">KATALOGU</span><h2>Menaxho shërbimet</h2></div>
      </div>
      <form id="service-form" class="service-form">
        <div class="form-group"><label for="service-name">Emri i shërbimit</label><input id="service-name" name="name" required minlength="2" maxlength="120"></div>
        <div class="form-group"><label for="service-description">Përshkrimi</label><input id="service-description" name="description" maxlength="500"></div>
        <button class="btn btn-primary" type="submit">Shto shërbim</button>
      </form>
      <div id="services-list" class="services-admin-list"></div>
    </section>
    <p id="dashboard-status" class="account-status" role="status" aria-live="polite"></p>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js" defer></script>
  <script src="assets/dashboard.js" defer></script>
</body>
</html>

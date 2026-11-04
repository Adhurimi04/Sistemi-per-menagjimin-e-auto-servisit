<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Krijo llogarinë AUTO PRO për rezervime dhe historikun e servisit.">
  <title>Regjistrohu | AUTO PRO</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="account-page">
  <header class="header">
    <nav class="navbar container">
      <a href="index.html" class="logo">
        <span class="logo-icon">AP</span>
        <span>AUTO <strong>PRO</strong><small>AUTOSERVICE &amp; DIAGNOSTICS</small></span>
      </a>
      <a href="index.html" class="account-back">← Kthehu te faqja</a>
    </nav>
  </header>
  <main class="account-main">
    <section class="account-card">
      <span class="eyebrow">KLIENT I RI</span>
      <h1>Krijo llogarinë <span>tënde.</span></h1>
      <p class="account-lead">Rezervo lehtë dhe mbaji të gjitha shërbimet në një vend.</p>
      <form id="account-form" data-action="register" class="account-form">
        <label for="name">Emri dhe mbiemri</label>
        <input id="name" name="name" type="text" autocomplete="name" required minlength="2" maxlength="120" placeholder="Emri juaj">
        <label for="email">Emaili</label>
        <input id="email" name="email" type="email" autocomplete="email" required maxlength="190" placeholder="emri@email.com">
        <label for="phone">Numri i telefonit</label>
        <input id="phone" name="phone" type="tel" autocomplete="tel" required minlength="5" maxlength="40" placeholder="+383 ...">
        <label for="password">Fjalëkalimi <span class="field-hint">(minimumi 10 karaktere)</span></label>
        <input id="password" name="password" type="password" autocomplete="new-password" required minlength="10" maxlength="72">
        <button class="btn btn-primary" type="submit">Krijo llogari <span>↗</span></button>
        <p id="account-status" class="account-status" role="status" aria-live="polite"></p>
      </form>
      <p class="account-switch">Ke llogari? <a href="login.php">Kyçu këtu</a></p>
    </section>
  </main>
  <script src="assets/account.js" defer></script>
</body>
</html>

<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Kyçu në llogarinë AUTO PRO për të menaxhuar rezervimet e autoservisit.">
  <title>Kyçu | AUTO PRO</title>
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
      <span class="eyebrow">MIRË SE U KTHEVE</span>
      <h1>Kyçu në <span>llogarinë tënde.</span></h1>
      <p class="account-lead">Menaxho rezervimet dhe shiko përditësimet e servisit.</p>
      <form id="account-form" data-action="login" class="account-form">
        <label for="email">Emaili</label>
        <input id="email" name="email" type="email" autocomplete="email" required maxlength="190" placeholder="emri@email.com">
        <label for="password">Fjalëkalimi</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="btn btn-primary" type="submit">Kyçu <span>↗</span></button>
        <p id="account-status" class="account-status" role="status" aria-live="polite"></p>
      </form>
      <p class="account-switch">Nuk ke llogari? <a href="register.php">Regjistrohu këtu</a></p>
    </section>
  </main>
  <script src="assets/account.js" defer></script>
</body>
</html>

# AUTO PRO

Sistem për menaxhimin e autoservisit. Projekti përdor HTML, CSS dhe JavaScript në frontend, PHP për API-të dhe MySQL/MariaDB për ruajtjen e të dhënave.

## Kërkesat

- XAMPP me Apache, PHP dhe MySQL/MariaDB.
- PHP me shtesat `mysqli` dhe `PDO_MySQL`.
- Për dërgimin e njoftimeve për detyrat, PHP duhet ta ketë aktiv shtesën cURL dhe të jetë konfiguruar `RESEND_API_KEY`.

## Instalimi lokal me XAMPP

1. Vendose projektin te `C:\xampp\htdocs\AutoPro`.
2. Nise Apache dhe MySQL nga XAMPP.
3. Importo `backend/autopro_db.sql` në phpMyAdmin:
   - Hape `http://localhost/phpmyadmin`.
   - Zgjedh **Import**.
   - Zgjedh skedarin `backend/autopro_db.sql` dhe kliko **Import** ose **Go**.
4. Hape `http://localhost/AutoPro/` për faqen kryesore ose `http://localhost/AutoPro/frontend/` për frontend-in me listën e detyrave.
5. Databaza `auto pro`, tabelat e llogarive/rezervimeve dhe shërbimet fillestare krijohen automatikisht kur përdoret aplikacioni.
6. Krijo llogari nga **Regjistrohu**. Llogaritë e reja marrin rolin `client`.

> **Kujdes:** Importi i `autopro_db.sql` zëvendëson tabelën `service_tasks` në databazën `autopro_db`. Bëj kopje rezervë para importimit nëse ke të dhëna që dëshiron t'i ruash. Skedari është eksport i të dhënave në momentin kur u krijua; nuk përditësohet automatikisht pas ndryshimeve të mëvonshme në databazë.

### Caktimi i administratorit

Ekzekuto komandën e mëposhtme në PowerShell, duke zëvendësuar emailin me atë të llogarisë:

```powershell
cd C:\xampp\htdocs\AutoPro
& 'C:\xampp\php\php.exe' .\backend\create_admin.php 'emaili-yt@example.com'
```

Dil nga llogaria dhe kyçu përsëri që roli i ri të hyjë në fuqi.

## Databazat

Projekti përdor dy databaza të ndara:

- **`auto pro`** — llogaritë, shërbimet dhe rezervimet. Skema krijohet automatikisht nga `backend/bootstrap.php`.
- **`autopro_db`** — tabela `service_tasks` për detyrat e servisit. Skema dhe të dhënat fillestare gjenden te `backend/autopro_db.sql`.

Në konfigurimin standard të XAMPP, lidhja e aplikacionit me `auto pro` përdor përdoruesin `root` pa fjalëkalim. Për databazën e aplikacionit mund të caktohen këto variabla mjedisi:

- `AUTO_PRO_DB_HOST`
- `AUTO_PRO_DB_NAME`
- `AUTO_PRO_DB_USER`
- `AUTO_PRO_DB_PASSWORD`

Endpoint-i `backend/api/tasks.php` përdor aktualisht `localhost`, përdoruesin `root` pa fjalëkalim dhe databazën `autopro_db`. Për dërgimin e njoftimeve me email gjatë krijimit të detyrës, cakto `RESEND_API_KEY` në mjedisin e PHP-së. Mos vendos çelësa privatë në kod ose në Git.

## Funksionet

- Klientët mund të regjistrohen, të kyçen, të kërkojnë termine dhe të shohin historikun/statusin e rezervimeve.
- Administratorët mund të menaxhojnë rezervimet, klientët, rolet, ID-të e veturave dhe shërbimet.
- Detyrat e servisit kërkojnë kyçje. Klientët shohin vetëm detyrat e veturës së caktuar; administratorët dhe mekanikët mund të shtojnë ose përditësojnë detyra, ndërsa vetëm administratorët mund t'i fshijnë.
- Rezervimet ruhen në MySQL. EmailJS dërgon njoftim për rezervimin në adresën e autoservisit.

## API-të

- `backend/api/auth.php` — regjistrim, kyçje, dalje, sesioni aktual dhe token CSRF.
- `backend/api/bookings.php` — listim, krijim dhe përditësim i rezervimeve.
- `backend/api/services.php` — listim dhe menaxhim i shërbimeve.
- `backend/api/users.php` — listim përdoruesish, menaxhim i roleve dhe caktim i ID-së së veturës.
- `backend/api/tasks.php` — detyrat e servisit:
  - `GET` — kërkon kyçje; klienti sheh vetëm veturën e lidhur me llogarinë e tij.
  - `POST` — vetëm administratori ose mekaniku mund të krijojë detyrë; kërkohet token CSRF.
  - `PUT` — vetëm administratori ose mekaniku mund të ndryshojë statusin (`TODO`, `IN_PROGRESS`, `DONE`); kërkohet token CSRF.
  - `DELETE` — vetëm administratori mund të fshijë detyrën; kërkohet token CSRF.

Rolet dhe ID-ja e veturës lexohen nga llogaria në databazë, jo nga të dhënat e dërguara prej shfletuesit. Cakto ID-në e veturës së klientit në panelin e administratorit që filtrimi i detyrave të funksionojë.

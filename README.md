# AUTO PRO — Sistemi për menaxhimin e autoservisit

Faqe për autoservis me regjistrim/kyçje të klientëve, role administratori dhe klienti, menaxhim shërbimesh dhe rezervimesh. Ndërfaqja përdor HTML, CSS dhe JavaScript; backend-i është PHP me MySQL.

## Nisja lokale (XAMPP)

1. Vendose projektin brenda `C:\xampp\htdocs\AutoPro`.
2. Nise Apache dhe MySQL nga XAMPP.
3. Hape `http://localhost/AutoPro/`. Në konfigurimin lokal të parazgjedhur, aplikacioni lidhet me MySQL si `root` pa fjalëkalim dhe krijon automatikisht databazën `auto pro`, tabelat dhe shërbimet fillestare.
4. Krijo llogari nga **Regjistrohu**. Çdo llogari e re merr rolin `client`.
5. Për ta bërë llogarinë tënde administrator, ekzekuto nga PowerShell:

   ```powershell
   cd C:\xampp\htdocs\AutoPro
   & 'C:\xampp\php\php.exe' .\backend\create_admin.php 'emaili-yt@example.com'
   ```

   Dil dhe kyçu përsëri që ndryshimi i rolit të hyjë në fuqi.

## Funksionet

- Klientët mund të rezervojnë termin dhe të shohin historikun dhe statusin e rezervimeve të tyre.
- Administratorët mund të shohin klientët dhe rezervimet, të ndryshojnë rolet, statuset dhe shënimet, si dhe të shtojnë ose çaktivizojnë shërbime.
- Rezervimet ruhen në MySQL. EmailJS dërgon njoftimin e rezervimit në adresën e autoservisit.
- Fjalëkalimet ruhen me hash; API-të përdorin sesione, prepared statements dhe token CSRF për veprimet që ndryshojnë të dhëna.

## Konfigurimi i databazës

Vlerat parazgjedhje funksionojnë për instalimin lokal standard të XAMPP. Për konfigurim tjetër, cakto:

- `AUTO_PRO_DB_HOST`
- `AUTO_PRO_DB_NAME`
- `AUTO_PRO_DB_USER`
- `AUTO_PRO_DB_PASSWORD`

Nëse përdoret endpoint-i i vjetër `backend/api/tasks.php`, cakto edhe `RESEND_API_KEY` në mjedisin e PHP-së. Mos vendos çelësa privatë në kod ose në Git.

## Endpoint-et e aplikacionit

- `backend/api/auth.php` — regjistrim, kyçje, dalje dhe sesioni aktual.
- `backend/api/services.php` — listimi dhe menaxhimi i shërbimeve.
- `backend/api/bookings.php` — krijimi, historiku dhe përditësimi i rezervimeve.
- `backend/api/users.php` — listimi i përdoruesve dhe menaxhimi i roleve nga administratori.

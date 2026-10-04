# Звіт fiksitt

## Галерея робіт, етап4, 4 жовтня 2026

Локально18081/#arbeid: 15 реальних фото, горизонтальний слайдер зі стрілками,
лічильником видимого діапазону, клавіатурою та великим переглядом без обрізання.
Збережено9 наявних записів, додано6 фото з папки власника. Решта фото з папки
не публікується автоматично. Сервер/Git/DNS/Tezamed/SMTP не змінювалися.

21 комплект WebP full/900/320; нові оригінали21,450,246байтів -> великі WebP
510,380байтів. Найбільший великий WebP113,608байтів. Hero, service/gallery/admin
фото та фото в SEO використовують WebP; PNG favicon/touch і SVG логотип не фото.
Ліміт завантаження5MiB включно; MIME/decode/EXIF/metadata/resize перевірені.
Заявки й приватні фото не переносяться до публічної галереї.

tests/gallery.php5 груп PASS: файли/шляхи/екранування/srcset, порожня/одне/відсутнє
фото, видимість, повторюваний імпорт зі збереженням старих рядків, legacy/shared
очищення. tests/gallery-http.cjs5 груп PASS: auth/CSRF, JPEG/PNG/WebP, орієнтація,
видалення EXIF, небезпечне ім'я, точно5MiB/понад/підробка/порожнє, replacement/
shared/delete, SQL-збій без залишених файлів. Регресії reviews7/workflow9/HTTP5
PASS. PHP/JS syntax та diff --check PASS.

Ручний браузерний тест: надмірний файл блокується; дозволений зберігається;
мініатюра/редактор WebP завантажуються, пошук працює. Адмінка320/390/768/1440,
галерея320/390/768/844/1440, dialog320/390/844 landscape/1440 без горизонтального
переповнення; контролі в межах. Стрілки, End, Escape й повернення фокуса пройшли.
Консоль без помилок. Chromium, не фізичний iPhone/Safari. Без JS перевірені
нативні посилання в HTML; повний окремий прогін браузера без JS не виконували.
Бекап власника .qa/gallery-preview-before.sql; імпорт6, повтор0. Дані тестів
видалені, QA-контейнери зупинені без видалення volumes. Локальний18081 працює.
Докази .qa/gallery-*.json, gallery-desktop.jpg, gallery-mobile.jpg.

## Фото в адмінці, 4 жовтня 2026

Локально узгоджено фото в admin services list/editor із публічним fallback.
tests/admin-media.cjs PASS: усі 6 фото, editor/public equivalence, custom slug/
category/missing-path/custom-image precedence, ширини 375/947/1440, service
rows не змінено. PHP lint і diff --check PASS. .qa/admin-media/ містить скриншоти.
Це display-only fix, не масове заповнення image-полів. На сервер не відправлено.

## Локальні SEO та адмінка, 4 жовтня 2026

Зміни лише локальні. Сервер, Git push, DNS та реальні заявки не змінювались.
Основний preview: http://127.0.0.1:18081/. Окремий тестовий Docker project
fiksitt-seo-qa використовував 18082/18026, власну БД і тимчасові записи.

Перевірки PASS:

- PHP lint публічних сторінок, app/ та admin/.
- 27 основних регресійних груп tests/qa.cjs: auth/CSRF, CRUD, uploads,
  налаштування, заявки, Mailpit, відмови DB/mail, session/rate limits,
  мобільна навігація та 50 responsive переглядів. First-admin setup не
  повторювався в цьому запуску, бо вже існував тимчасовий QA-admin.
- 5 додаткових груп tests/extra.cjs: форма без JS, XSS/404, HTTPS cookie,
  точний CSP JSON-LD hash та справжні ширини hero source.
- 7 груп tests/seo-admin.cjs: production HTTPS opt-in, захист local/staging,
  301 aliases зі збереженням service-prefill, пріоритет route slug над query,
  404 unknown category, canonical/schema/OG/sitemap/CSP, admin search/filter/
  pagination/contact links, preview-XSS, editable home metadata, WebP variants
  та 20 додаткових переглядів адмінки на 320/375/768/1440.
- tests/branding.cjs: новий бренд, іконки/manifest, листи, admin/public та
  4 responsive homepage views. Запускалося на основному локальному preview.

Локальний performance sampling (не Lighthouse і не польові Core Web Vitals):
6 комбінацій URL/ширини, headless Edge, без throttling, кеші можуть бути теплими.
LCP спостерігався в межах 72–552 ms, накопичений first-viewport CLS = 0.
Ці значення не означають такої ж швидкості на телефоні/сервері; INP не виміряно.
Звіти: .qa/results.json, .qa/extra-results.json, .qa/seo-admin/results.json,
.qa/performance/results.json, responsive screenshots у .qa/seo-admin/.

SEO_LAUNCH.md описує фактичні зміни та майбутнє підключення домену/HTTPS,
Search Console й реальних бізнес-даних. Індексація вимкнена за замовчуванням.
Немає SQL-міграцій. Для майбутнього deploy явно перевірити seo_indexable,
щоб не лишити production noindex після завершення запуску.

Нижче збережено історію попередніх перевірок/серверних релізів.

Після дозволу власника створено окремий локальний admin masxpros@gmail.com
на 18081, випадковий пароль у приватному owner-only файлі поза Git. Вхід,
dashboard/account/SEO на 375/1440, noindex та logout: PASS. Серверний пароль
не змінювався. fiksitt-seo-qa containers/network прибрано, QA volume збережено.

## Активація адмінки 3 жовтня 2026

Створено підтверджений власником акаунт masxpros@gmail.com (password_hash,
випадковий тимчасовий пароль у приватному локальному файлі поза Git).
На сервері один admin і одна наявна заявка; services/gallery/settings
і заявка збережені. SMTP ще не налаштовано.

Огляд має лічильник нових заявок, переходи до розділів і створення послуги/
фото. Додано Konto зі зміною пароля: старий пароль, CSRF, підтвердження,
rate limit, регенерація session/CSRF і відхилення інших старих сесій.
Схема БД незмінна. SETUP_TOKEN очищено.

До HTTPS адмінка доступна тільки через SSH-тунель на 127.0.0.1:18090.
Публічні admin/login.php і admin/account/ повертають 403; головна 200.
Whitelist ґрунтується на REMOTE_ADDR, forwarded-заголовки не довірені.

Повторний QA в окремому lily-qa-fixes: 28 основних і 5 додаткових груп PASS,
PHP lint PASS. tests/admin-account.cjs: 35 переглядів на 5 ширинах, правильні
dashboard shortcuts, зміна пароля, неправильний старий пароль/підтвердження,
CSRF, нова сесія, відхилення іншої сесії й старого пароля, IP whitelist: PASS.
Тестові акаунти/контент прибрано.

Production QA через тунель: реальний вхід власника, 14 admin views на 320/
1440 px без overflow, account form, logout, noindex/no-store, setup 404,
публічна адмінка 403 і публічний сайт 200: PASS. Справжні заявки не змінювали.
Збережено source/image/config backup у
/opt/lily-montering/backups/admin-20261003T202349Z/; config root-only.
Результати: .qa/admin-account/results.json, .qa/production-admin/results.json.

## Оновлення 3 жовтня 2026

Виправлення опубліковано на http://188.137.232.136:8080/ у production Docker
stack, який поки доступний як HTTP staging. Старі розділи нижче описують QA
2 жовтня, а не актуальні підтвердження production.

Додано 4 реальні фото у 12 JPEG/WebP-варіантах. Усі 6 карток і сторінок послуг
мають фото; пріоритет зображень із БД збережено. Прибрано повтор короткого
опису, виправлено порожню колонку, footer, якірні переходи та мобільну CTA.
Додано вибір країни/коду телефону, серверну нормалізацію й відображення
отримувача пошти у налаштуваннях адмінки. Схема/дані БД не змінювались.

tests/public-fixes.cjs: 4 групи PASS, включно з 40 перевірками головної,
каталогу і 6 послуг на 320/375/768/1024/1440 px, фото, відсутністю overflow,
footer, відсутністю дубльованого опису, якірними переходами та CSP/runtime.
JS і no-JS заявки фактично записані з номерами +4712345678 / +380508068159
і email_sent=1 у локальному Mailpit-середовищі. Тестові DB-заявки видалено.
Країна, повний міжнародний номер, невідомий код, неправильний формат і
fallback для відсутнього фото перевірені. PHP lint: PASS.
tests/delivery-settings.cjs: PASS для відображення email отримувача,
відправника, SMTP й посилання на заявки в адмінці на 320/1440 px;
окремий тестовий акаунт видалено після перевірки.

Локальний звичайний stack мав відмову DB-авторизації; його дані/config не
змінювались. Перевірки виконано в окремому Docker-проєкті lily-qa-fixes.
Для стабільного no-JS Playwright скролу задано reducedMotion=reduce.

Онлайн read-only QA: 40 responsive перевірок PASS, усі фото завантажились,
country selector/autofill і навігація працюють; приватний app/phone.php дає
403, невідома послуга 404. Справжню онлайн-форму не надсилали.
Дані services/gallery/settings мають однаковий fingerprint до/після,
наявна заявка збережена (requests=1). MAIL_RECIPIENT/MAIL_FROM/SMTP_HOST
не налаштовані; admins=0. Реальна SMTP-доставка, HTTPS і доступ власника
до адмінки залишаються невиконаними, не вважати їх перевіреними.

Backup: /opt/lily-montering/backups/public-fixes-20261003T194113Z/,
source.tar.gz і попередній image-id.txt; DB не відновлювали/не імпортували.
Результати: .qa/public-fixes/results.json, .qa/online-public-fixes/results.json.

Дата: 2 жовтня 2026 року. Роботи виконано в наявному репозиторії без зміни фреймворку чи перебудови сайту з нуля. Новий PHP-застосунок працює локально; розгортання на зовнішньому cPanel не виконувалось.

## 1. Знайдена архітектура

Статичний HTML/CSS/vanilla JS, PHP-форма з SMTP/native mail, Apache .htaccess, SEO-файли та 10 комплектів реальних фотографій із JPEG/WebP/адаптивними варіантами й окремим OG-зображенням. Переглянуто всі виробничі фотографії. Варіанти форматів не є зайвими дублікатами; сирі оригінали виключені з розгортання. Наявні сторонні PPTX-файли не змінювались.

## 2. Створені файли

- Dockerfile, docker-compose.yml, .dockerignore, .env.example.
- index.php, tjenester.php, 404.php, sitemap.php, robots.php.
- app/bootstrap.php, auth.php, admin-layout.php, public-layout.php, content.php, crud.php, uploads.php, mail.php, .htaccess.
- admin/index.php, login.php, setup.php, logout.php, admin.css, admin.js.
- admin/services/index.php, categories/index.php, gallery/index.php, requests/index.php, settings/index.php.
- database/schema.sql, seed.sql, .htaccess; assets/uploads/.htaccess.
- tests/qa.cjs, extra.cjs, lint.php, .htaccess; цей звіт.

Локальний .env з випадковим setup-токеном і каталог .qa з результатами/скриншотами виключені з Git.

## 3. Змінені файли

README.md, .gitignore, .htaccess, config.example.php, contact.php, index.html, script.js, styles.css, robots.txt, sitemap.xml. Статичний index.html збережено для попереднього перегляду; на Apache він перенаправляється на PHP.

## 4. Docker

Web: PHP 8.2 Apache, фактично перевірено PHP 8.2.34. DB: MariaDB 10.11 із приватною мережею та постійним томом. Mailpit: локальний SMTP/inbox. Web і Mailpit доступні тільки через loopback порти 8080/8025. Підтверджено GD JPEG/PNG/WebP, PDO MySQL, mbstring, fileinfo, sessions і Apache rewrite/headers. Docker не потрібен на продакшні.

## 5. Схема бази

admins, service_categories, services, gallery_items, contact_requests, site_settings. utf8mb4, індекси, унікальні slug, timestamps, sort_order, active/featured; зовнішній ключ забороняє видалення використаної категорії. Seed переніс 6 послуг і 9 галерейних записів; адміністраторів і вигаданих цін немає.

## 6. Адмінпанель

Захищений вхід, одноразовий setup, dashboard, CRUD послуг/категорій/галереї, сортування, активність, featured, SEO, завантаження зображень, перегляд/пошук/фільтрація/статуси/архівування/видалення заявок і налаштування компанії. Мобільне меню згортається; таблиці прокручуються всередині власної області.

## 7. Публічний каталог

/tjenester і /tjenester/{slug}, фільтр категорій, повні описи, опційні ціна/тривалість, CTA, пов’язані послуги. Неактивні послуги й категорії приховані. Невідомі slug повертають 404. Є прямий fallback tjenester.php?slug=...; перемикач pretty_urls описано в README.

## 8. Дизайн

Збережено бренд, зелену палітру, корисні норвезькі тексти й реальні фото. H1 головної сторінки явно показує бренд. Оновлено картки послуг, галерею, сторінки каталогу/деталей, CTA, footer, форми й окремий стриманий інтерфейс адміністратора. Додано стабільні пропорції фото, перенесення довгого тексту й відступ для якірних посилань під sticky-header.

## 9. Виправлені проблеми

- Раніше заявка могла загубитися при відмові пошти: тепер спочатку зберігається в БД.
- Видалено example.com та видимі заглушки телефонів/email у PHP-шаблонах; SEO використовує налаштований домен.
- Поле started_at тепер має серверне значення, тому форма працює без JS.
- aria-invalid тепер має коректні true/false; серверні помилки пов’язані з полями та отримують фокус.
- Скориговано srcset: файли -900.webp фактично мають ширину 675 px.
- Виправлено два неточні підписи/alt наявних фотографій.
- Усунуто повторне підключення до недоступної БД протягом одного запиту та повторне читання каталогу на головній.
- Мобільні таблиці не стискають імена в короткі вертикальні фрагменти.

Початковий QA-запуск виявив, що після старту DB тест починався до готовності MariaDB. У тесті додано очікування healthcheck; наступні повні запуски пройшли. Автоматизована no-JS перевірка також потребувала reduced-motion для стабільного прокручування; реальне надсилання без JS після цього перевірено.

## 10. Безпека

Prepared PDO, escaping, password_hash/verify, HttpOnly/SameSite/Secure-cookie, регенерація session ID, CSRF усіх змін, POST logout/delete, idle timeout і login throttling. Одноразовий setup вимагає секретний токен і нуль адміністраторів; конкурентні запити серіалізує DB-lock.

Uploads: fileinfo, MIME whitelist, ліміт 5 MB/20 млн пікселів, GD re-encoding, випадкові імена, заборона виконання скриптів, видалення тільки невикористаних завантажень. Заблоковано app/database/tests, конфігурацію, dotfiles/.git і сирі фото. Адмінка noindex/no-store.

Для змінного JSON-LD PHP формує точний SHA-256-хеш у CSP; старий статичний хеш залишено лише для статичного HTML. unsafe-eval/unsafe-inline не додано. Публічні відповіді й прикладні логи не містять SQL, паролів або SMTP-відповідей.

Звірено API з офіційною документацією [PHP sessions](https://www.php.net/manual/en/function.session-set-cookie-params.php), [PDO](https://www.php.net/pdo.prepared-statements) і [Docker PHP](https://hub.docker.com/_/php).

## 11. Публічні перевірки

HTTP 200 головної/каталогу/деталей, CSS/JS/зображення, меню, Escape, фільтр, 404 невідомих/неактивних послуг, прямі query URL, sitemap, robots. Форма: обов’язкові поля, email, CSRF, honeypot, час заповнення, rate limit, запис у БД, Mailpit, успішний стан і серверні помилки. Окремо фактично надіслано форму з JavaScript вимкненим.

Примусова відмова mailpit: заявка збереглася, отримано підтвердження реєстрації. Відмова DB: головна повернула 200 з резервним вмістом, каталог/admin — контрольований 503; працювала доставка лише поштою. Подвійна відмова — 503. Сервіси відновлено.

## 12. Адміністративні перевірки

Setup із неправильним/правильним токеном і блокування після створення; невдалий/успішний вхід, session ID/cookie flags/persistence, logout, expiry і throttling. CSRF без токена/з неправильним токеном відхилено. Перевірено CRUD і duplicate slug, переназначення категорії, featured/order/activation, захист використаної категорії, JPEG/PNG/WebP, фальшивий MIME/PHP/oversize, alt/order/delete, заявки й налаштування.

Перевірено harmless SQL-injection, stored/reflected XSS та malicious filename; дані відхиляються, екрануються або отримують випадкове безпечне ім’я.

## 13. Виконані команди

Основні команди, які фактично виконувались:

```text
docker info --format '{{.ServerVersion}}'
docker compose up -d --build
docker compose up -d --build --quiet-build
docker compose ps
docker compose exec -T web sh -c "find . -name '*.php' -not -path './foto/*' -print0 | xargs -0 -n1 php -l"
docker compose exec -T web php tests/lint.php
node tests/qa.cjs
node tests/extra.cjs
docker compose stop db
docker compose stop mailpit
docker compose up -d --wait --wait-timeout 60
docker compose logs --tail=12 web
curl.exe -I http://localhost:8080/
git diff --check
git check-ignore .env config.php .qa/results.json
```

Також виконано curl-перевірки каталогу/404/.git, PDO-запити для контролю записів, перевірку GD/cookie параметрів та додатковий Playwright сценарій якірної навігації на 5 ширинах. Node працював лише як локальний QA-інструмент із bundled Playwright/Sharp; продакшн його не використовує.

## 14. Результати

28 груп основного QA + 5 додаткових — PASS у фінальних успішних запусках. Усі 25 PHP-файлів пройшли lint. Десять публічних/admin представлень перевірено на 320, 375, 768, 1024, 1440 px: немає горизонтального переповнення документа, зламаних зображень чи дубльованих H1. Скриншоти desktop/mobile і галереї переглянуто. JS runtime/CSP помилок не виявлено.

JSON-результати: .qa/results.json, .qa/extra-results.json. Наприкінці тестові акаунти, записи й uploads прибрано; у базі залишились 6 послуг, 9 фото, 0 адміністраторів і 0 заявок. Mailpit може містити локальні QA-листи для перегляду.

## 15. Незаповнені дані

Публічні телефон/email/домен, організаційний номер і соцмережі порожні. Østlandet перенесено з попереднього сайту і потребує підтвердження. Ціни/тривалість порожні. Продукційні DB/SMTP дані не задані. Випадковий локальний setup-токен міститься тільки в ігнорованому .env; пароль адміністратора не створено.

## 16. Розгортання

Детальні кроки й checklist — у README.md. У cPanel створити DB/user, імпортувати schema.sql/seed.sql, завантажити виробничі PHP/CSS/JS/assets/admin/app файли й приховані .htaccess, створити server-only config.php, увімкнути SSL, створити власного адміністратора та прибрати setup-токен. Заповнити підтверджені контакти й перевірити справжню SMTP-доставку. Docker, Node і SSH не потрібні.

## 17. Обмеження

Фактичний cPanel/LiteSpeed, публічний SSL, зовнішній SMTP із TLS/authentication та конкретний домен не тестувались: доступів не надано. Secure-cookie перевірено за серверним HTTPS-прапорцем, а не на реальному TLS-домені. Відсутність mod_rewrite не симулювалась, але прямі query URL перевірено.

Немає фонових mail-retry, публічного password reset чи розширеного керування адміністраторами. Архітектура відповідає невеликому бізнес-сайту; процеси зберігання/видалення старих персональних даних і резервного копіювання описані для власника.

Git commit/push і зовнішнє розгортання для цієї зміни не виконувались. GitHub Pages підтримує лише збережений статичний перегляд і не запускає PHP-адмінку/каталог/форму.

## 18. Оновлення 2026-10-04: ручне QA та пошта

Актуальний ручний прогін: MANUAL_QA.md. SMTP реалізація й активація:
MAIL_DELIVERY.md. PHPMailer 7.1.1, захищені test-email/admin retry, DB-first
і no-background-retry. Публічна форма після успіху більше не підсвічує
порожні поля червоним (script.js v4).

Успішні фінальні автоматичні прогони: qa 28 + extra 5 + seo-admin 7 +
mail-notifications 6 груп, окремо admin-media. Mail guards перевіряють
одночасний delivery lock, дублікати, spam, CSRF/GET/auth, TLS/config/header
validation, sender/recipient/Reply-To, UTF-8 та admin link.
Реальна Gmail доставка ще НЕ підключена: потрібен owner app password.
Preview 18081/owner admin, production і Git push не змінювалися.

## 19. VPS після публікації 2026-10-04

За прямим запитом власника опубліковано fiksitt branding, SEO, admin photos,
PHPMailer та захищені retry/test-mail. Реліз fiksitt-20261004.tar.gz, резервна
копія /opt/lily-montering/backups/fiksitt-20261004T063558Z/ (source, uploads,
private env, DB dump, old image ref). Full DB restore не тестували.
Перший smoke завершився curl/grep SIGPIPE й відкатом; після виправлення
повторний deploy успішний. PHP lint усіх 31 PHP-файлів, включно з vendor, PASS.

Перед тестовою заявкою fingerprints DB до/після співпали: admin/request/service/
category/gallery/settings; company_name єдиний виняток (exact-match rename).
Після тесту original request #1 fingerprint теж незмінний.
Автоматизовані онлайн smoke: 40 public responsive views + 16 admin responsive
views, 6 admin images, owner login/logout, setup404/public admin403, no overflow,
private files403/404, anchors, country selector, no JS/CSP errors.
Ручний CUA тест: одна public submission -> saved #2, cleared form і registered
confirmation; existing owner login -> #2 detail -> SMTP settings -> logout.
Screenshot .qa/production-20261004/test-request.jpg, machine results
.qa/online-public-fixes/results.json та .qa/production-admin/results.json.
Повний CRUD/outage/rate-limit прогін на production навмисно НЕ виконувався.

Gmail STARTTLS із PHP container PASS. Одноразова авторизація альтернативним
Gmail, наданим у приватному файлі, FAIL 535 5.7.8. Дані не записувались у VPS
env/образ/логи. Серверний SMTP_PASSWORD досі порожній, заявка #2 email_sent=0.
Реальний лист не надіслано й inbox не перевірено. Потрібен app password/OAuth2;
потім admin test-mail, resend #2 і власник підтверджує Gmail inbox/spam.
HTTP 8080 staging noindex; домен/HTTPS/Google indexing pending. Git push не було.
Фінальний read-only smoke: HTTP200, DB healthy, robots Disallow:/, XML sitemap,
alias index.html301, .env/app/mail.php/docs/admin403 навіть із підробленим
X-Forwarded-For, CSP без unsafe-eval. git diff --check PASS (лише CRLF warnings).
# Domain Routing Verification (2026-10-04)

Live `https://fiksitt.online/` now uses its own Nginx vhost and certificate,
not the Tezamed default. HTTP and www redirect to the canonical fiksitt domain.
Verified HTTP200/TLS verification0; Tezamed root/www still resolve to its own
application on the new VPS. Browser home/service navigation and photos PASS.
Secure session cookie PASS. Public `/admin`, encoded admin and `.env` return403;
legacy public8080 is unreachable, SSH-tunnel backend remains available.
Seven trusted-proxy scheme cases and changed PHP syntax PASS. Certbot renewal
dry-run including deploy hooks PASS; timer enabled/active. No DB/password/upload
changes or production form submission in this routing test. SMTP delivery and
indexing opt-in remain pending. See CODEX_HANDOFF section27 for backup/addresses.
# Mobile Layout Fix Verification (2026-10-04)

Confirmed before fix: process/audience mobile horizontal padding0; header sticky.
After fix: section side padding16px, children contained, mobile header scrolls
with the page; desktop sticky preserved. Mobile dropdown opens under header and
closes on section selection. Local responsive sizes320,375,390,430,768,844,1440
PASS, including landscape844x390. Live390x844 process/menu/anchor/cache-version
PASS, HTTP200/TLS0, changed PHP lint PASS. Only CSS and public-layout deployed;
backup `/opt/lily-montering/backups/mobile-safari-20261004`. Evidence:
`.qa/mobile-safari-20261004/results.json` and `live-process-390.jpg`.
Browser checks used in-app Chromium, not real Safari/WebKit. iOS toolbar,
notch/home indicator and physical-device scrolling require owner verification.

# Private Enquiry Photo Verification (2026-10-04)

Stage1 implemented and deployed with owner approval. Guests submit descriptions
and up to5 JPEG/PNG/WebP attachments, <=5MiB each; saved photos are private WebP.
HEIC is not supported. Reviews/slider/bulk gallery conversion not included.

Isolated HTTP/DB/GD/SMTP suite tests/request-photos.cjs:15 groups PASS. Includes
idempotent migration, MIME spoofing, malformed/nested fields,6files, >5MiB,
CSRF/consent, corrupt decoding,20MP budget, urlencoded/no-photo compatibility,
4formats/orientation scenarios, all8 EXIF orientations vs Sharp, metadata strip,
private auth-only previews/downloads, fixed managed paths, exact5x5MiB acceptance,
body limit, atomic rollback+cleanup, unwritable storage, SMTP failure with safe
storage, web recreation with persistent volume, status/archive and mail counts.
PHP syntax, JS syntax and trusted proxy/client-IP tests PASS.

CUA manual: actual guest submission with photo -> success/reset -> admin detail
with loaded private thumbnail and downloaded WebP. Selection/removal/reselection,
client size/count errors and error display AFTER a prior success verified.
Found/fixed photo-error node removal after submission. Responsive widths320/390/
844/1440 no horizontal overflow; mobile admin photo/controls contained. Browser
is Chromium, not real Safari. Proof in .qa/request-photos/.

Production: backup request-photos-20261004T091944Z, additive migration, web-only
rebuild and private volume, contact-only Nginx26m limit. nginx -t PASS. Actual
HTTPS1photo and5x5MiB accepted; all6files WebP43842bytes+thumbs, permissions0600.
CSRF403, invalid count/size422, body413, public admin403, private config403,
legacy8080 unreachable; fiksitt/Tezamed HTTP200 TLSverify0. Initial SSH metadata
check timed out; recovered and verified/cleaned in guarded server CLI pass.
Public icon403 from new parent folder permissions fixed on host + image; final
browser confirms loaded preview AND remove icon. All synthetic requests/photos
removed. Existing complete row hashes match pre-deploy snapshot; passwords,
content, indexing, SMTP config and Tezamed unchanged. Owner18081 also updated.

Local Mailpit notification passed. Live email_sent=0 for both synthetic requests:
real Gmail delivery remains blocked pending valid SMTP app password/OAuth2 and
owner inbox verification. Enquiries remain available in admin regardless of mail.

# Public Reviews Section Verification (2026-10-04, Local Only)

Previous release pushed to GitHub main at2e0f292. Stage2 changes are local and
not deployed to VPS. Read-only homepage section and schema foundation only;
verified invitations/submission/admin moderation remain stage3.

Isolated tests/reviews.php:7groups PASS after final long-text changes. Migration
twice preserves requests; empty state has no fabricated stars/counts; approved,
verified, completed and already-published filters plus newest6 order; public
columns only; XSS escaped; accessible ratings and native long-text disclosure;
rating range and one-review-per-request; missing-table unavailable state;
enquiry deletion cascades review. Retained browser fixtures cleaned, final suite
cleans all its own records. All PHP syntax and git diff --check PASS.

CUA Chromium: populated section at320/390/768/844landscape/1024/1440px has no
horizontal overflow; cards contained, all30 Lucide star images load. Long review
opens/closes on desktop and mobile without overflow; mobile menu closes on
Omtaler selection and heading is unobscured. Owner18081 empty section checked
320/390/1024, no fake ratings, CTA navigates to#kontakt, console warnings/errors
empty. Real iOS Safari/WebKit not available. All existing owner preview row
hashes unchanged after additive migration; backup .qa/reviews-preview-before.sql.

Evidence: .qa/reviews-results.json, reviews-responsive.json,
reviews-empty-responsive.json, reviews-desktop.jpg, reviews-mobile.jpg and
reviews-preview.jpg. Populated QA screenshots are TEST ONLY fixtures; owner's
preview has no fake reviews. QA containers stopped without removing volumes;
owner preview stays up. VPS, SMTP, DNS and Tezamed unchanged.

# Stage2 Deployment And Stage3 Workflow Verification (2026-10-04)

Stage2 now live at https://fiksitt.online/#omtaler. Complete backup
reviews-stage2-20261004T095713Z: source/config/uploads, DB, private photos, image
and pre/post hashes. First attempt stopped on a snapshot-helper table-name error
before modifications; corrected and used a new backup. Additive reviews table,
public files and web-only rebuild. All existing row hashes and env/compose hashes
match. Fiksitt/Tezamed HTTPS200 TLSverify0, public admin403, CSSv7, mobile390
no overflow. Stage3 remains local: production omtale.php404. Live proof
.qa/reviews-live.jpg. No Git push this turn.

Stage3 core tests/review-workflow.php:9groups PASS, plus9groups SMTP-failure
variant. HTTP tests/review-workflow-http.cjs:5groups PASS. Stage2 reader regression:
7groups PASS. Includes hashed random tokens, completed-only invitation,30-day
expiry/rotation/revocation, UTF-8 and scalar boundaries, explicit consent,
original-recipient Mailpit delivery/idempotence, transaction rollback on forced
token-update failure, pending-only submit, one-time/concurrent replay, approval
guards, unchanged low ratings/text, latest moderator/private notes, cascade,
CSRF/auth/body/method/rate limits, no PII or token access-log leak, public escaping
and withdrawal. Failed SMTP leaves valid invite and no acceptance flag. No actual
Gmail inbox delivery claimed. Final isolated requests/reviews/invitations/admins0.

Manual CUA: create/copy/send -> client form -> success -> pending moderation ->
approval -> public view -> rejection -> hidden. Status/search filters and logout
PASS. New invitation after an earlier response, same-page fragment change and
invalid token clearing old grant PASS. Customer widths320/390/768/844/1440 and
admin actual320/390/768/1440 have no body overflow; controls/icons load and fit.
Mobile table scrolls internally. Found/fixed quote CTA overlapping submit button
on review route, and scoped Apache referrer override. A stale/inactive tab's1280
measurements were discarded and responsive tests rerun on a fresh tab. Full-page
capture may misplace the offscreen fixed skip link; live DOM proves it is hidden.
Console warnings/errors empty. Chromium only; real iOS Safari not available.

Owner18081 migrated after DB backup; all original columns/rows unchanged, account
preserved, no fake reviews. Stage3 not uploaded to VPS or Git. PHP/JS syntax and
git diff --check PASS. QA services stopped without volume removal; owner preview
left running. Evidence in .qa/review-workflow-results.json,
review-workflow-mail-failure.json, review-workflow-http-results.json,
review-form-responsive.json, review-admin-responsive.json, screenshots of form/
moderation. TEST ONLY screenshot entries are disposable fixtures, not customers.

## Local Brand Redesign (2026-10-04)

Read-only tests/design-http.cjs PASS: home/catalog/detail/admin login/review/404,
versioned icons, manifest dimensions, asset byte budgets, dynamic and static CSP.
PHP/Node syntax checks PASS. User forms and database records untouched.
CUA manual: mobile menu and Contact anchor; gallery next/close/Escape; dialog
controls fit at 320/390; detail at390/admin login at320. Final public responsive
checks320/390/768/1024/1440 plus844x390: no body/field overflow, logo loads, next
section visible in first viewport. Header is non-sticky below980 as before.
Public CSSv15/admin CSSv7. Evidence .qa/design-responsive.json and
.qa/design-home-{desktop,mobile}.png. Real iPhone Safari unavailable; authenticated
admin workflows and full business regression not rerun for this styling-only
change. No server deployment, Git push, account changes or SMTP activation.
Uploaded artwork spelling fiksit retained pending owner confirmation; configured
company/domain remains fiksitt. Prompt/asset provenance: CODEX_HANDOFF section33.

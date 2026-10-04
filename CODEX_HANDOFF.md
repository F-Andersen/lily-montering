# fiksitt: контекст для Codex і дані запуску

Оновлено: 2026-10-04. Цей файл призначений для наступної роботи з проєктом:
прочитати його разом із README.md, перевірити фактичний стан і продовжити
налаштування без повторного збору відомої інформації.

Це документація, а не конфігурація застосунку. Зміна YAML нижче сама по собі
не змінює сайт. Файл не потрібно завантажувати у public_html.

## 1. Як заповнити

У YAML замінити потрібні null на підтверджені значення. null означає
«ще невідомо», false — «ще не виконано/не підтверджено». Рядки з адресами,
шляхами й номерами телефонів брати в лапки.

Не записувати сюди паролі, секретні токени, приватні SSH-ключі чи коди 2FA.
Для секретів указувати тільки місце зберігання або назву запису в менеджері
паролів. config.php і .env виключені з Git. Пароль адміністратора власник
задає сам у формі створення акаунта.

Першочергові дані: фінальний домен, провайдер/панель хостингу, шлях сайту,
можливість завантажувати файли, створювати/імпортувати базу, параметри DB
і SMTP, адреса отримувача заявок. Відомості про компанію заповнювати лише
після підтвердження власником.

## 2. Дані для запуску

```yaml
handoff_version: 1
updated_at: "2026-10-04"

project:
  name: "fiksitt"
  intended_domain: "fiksitt.online"
  domain_purchased: true # Owner confirmed domain-only purchase; DNS activation still pending.
  deployment_authorized: true # Only the explicitly requested 2026-10-04 VPS update, not future deployments.
  repository: "https://github.com/F-Andersen/lily-montering.git"
  local_workspace: "C:/Users/Anderson/Desktop/LILY"
  public_language: "nb"
  communication_language: "uk"

launch:
  # fresh_install або upgrade_existing; upgrade вимагає огляду і backup.
  installation_type: "upgrade_existing"
  # staging або production.
  target_environment: "production" # Runtime setting; public HTTP staging is not a final domain launch.
  public_domain: "https://fiksitt.online" # Canonical runtime APP_URL; DB public_domain remains empty.
  # Повна довірена HTTPS-адреса, включно з підкаталогом, якщо він є.
  app_url: "https://fiksitt.online"
  # "" для кореня домену; наприклад "/lily" для підкаталогу.
  installation_subpath: ""
  preferred_hostname: "fiksitt.online"
  existing_site_present: true
  existing_database_present: true
  existing_uploads_present: true
  desired_launch_window: null
  # Підтверджений спосіб зберегти старі URL, якщо вони вже використовуються.
  old_urls_or_redirects_required: null

hosting:
  provider: "Zomro"
  plan: "Optimal AMD | DE-1 v.3"
  panel_type: "Zomro cloud panel + SSH/Docker"
  panel_url: "https://cp.zomro.com/"
  web_server: "Apache 2.4.68 in Docker"
  php_version: "8.2"
  database_engine_and_version: "MariaDB 10.11"
  document_root: "/opt/lily-montering/app (container: /var/www/html)"
  file_manager_available: false
  phpmyadmin_available: false
  htaccess_supported: true
  rewrite_supported: true
  headers_supported: true
  gd_jpeg_png_webp_supported: true
  session_and_private_temp_writable: true
  outbound_smtp_allowed: true # Gmail TCP 587 + verified STARTTLS tested.
  tls_certificate_active: true
  php_receives_https_flag: true # Trusted NAT gateway scheme; Secure cookie verified.

access:
  # file_manager, ftps або sftp. SSH не є обов'язковим.
  upload_method: "sftp/ssh"
  upload_host: "188.137.232.136"
  upload_port: 22
  upload_username: "root"
  upload_remote_path: "/opt/lily-montering/app"
  upload_credentials_ref: "C:/Users/Anderson/.ssh/tezamed_zomro_ed25519"
  panel_access_available: true
  panel_credentials_ref: null
  database_import_access_available: true
  dns_provider: "HOSTiQ domain-only DNS (Openprovider authoritative zone responding; recursive caches pending)"
  owner_provided_nameservers: ["pdns1.hostiq.ua", "pdns2.hostiq.ua"]
  observed_zone_nameservers: ["ns1.openprovider.nl", "ns2.openprovider.be", "ns3.openprovider.eu"]
  dns_panel_url: null
  dns_access_available: false
  dns_credentials_ref: null
  # Власник завершує 2FA у панелі; одноразові коди тут не зберігати.
  panel_requires_owner_2fa: null

database:
  # На cPanel використовувати повні назви з префіксом облікового запису.
  host: "db"
  port: 3306
  name: "lily"
  username: "lily"
  password_ref: "/opt/lily-montering/app/.env: DB_PASSWORD"
  dedicated_user_created: true
  privileges_for_application_database_granted: true
  schema_imported: true
  initial_seed_imported: true
  # Заповнювати після фактичного огляду цільової бази.
  existing_admin_count: 1
  existing_data_must_be_preserved: true

mail:
  # smtp або native_mail; остаточний вибір залежить від хостингу.
  transport: "smtp"
  recipient_email: "masxpros@gmail.com"
  from_email: "masxpros@gmail.com" # Current inactive server profile; new sender pending successful auth.
  from_name: "fiksitt nettside"
  smtp_host: "smtp.gmail.com"
  smtp_port: 587
  # tls (STARTTLS) або ssl (implicit TLS) згідно з провайдером.
  smtp_encryption: "tls"
  smtp_username: "masxpros@gmail.com" # Current server profile has no password.
  smtp_password_ref: "C:/Users/Anderson/.codex/private/fiksitt-mail/gmail.smtp.env (local supplied account failed SMTP preflight; not activated)"
  sender_verified_by_provider: false
  spf_dkim_configured_if_required: null
  real_delivery_verified: false

business:
  company_name: "fiksitt"
  slogan: "Presis montering. Ryddig levert."
  public_phone: null
  public_email: null
  # Поточне значення перенесено зі старого сайту, його треба підтвердити.
  service_region: "Østlandet"
  service_region_confirmed: false
  organization_number: null
  social_url: null
  primary_cta: "Be om tilbud"
  # null залишає опційні значення порожніми; не вигадувати ціни.
  prices_or_duration_updates: null

admin:
  owner_email: "masxpros@gmail.com"
  password_set_by_owner: false
  # Генерувати новий production-токен; не копіювати локальний.
  setup_token_ref: "/opt/lily-montering/app/.env: SETUP_TOKEN"
  first_admin_created: true
  setup_token_removed_after_creation: true

backups:
  owner_or_responsible_person: null
  schedule: null
  files_backup_ref: "/opt/lily-montering/backups/fiksitt-20261004T063558Z/source.tar.gz"
  database_backup_ref: "/opt/lily-montering/backups/fiksitt-20261004T063558Z/database.sql"
  config_and_uploads_included: true
  backup_timestamp: "2026-10-04T06:35:58Z"
  restore_verified: false
  rollback_window: null
  customer_request_retention_policy: null

deployment:
  # Заповнювати фактичними значеннями, а не поточним припущенням.
  deployed_git_revision_or_package_ref: "/opt/lily-montering/releases/fiksitt-20261004.tar.gz (working-tree source; no Git commit/push)"
  deployed_at: "2026-10-04T06:36Z"
  deployed_by: "Codex"
  production_config_created: true
  uploads_writable: true
  https_redirect_verified: true
  smoke_tests_passed: true
  enquiry_visible_in_admin: true
  enquiry_email_received: false
  sitemap_and_canonical_verified: true
  notes: "fiksitt.online HTTPS activated 2026-10-04; HTTP/www canonical redirects verified. Backend 8080 now loopback-only, bridge ingress18080. Indexing remains off pending owner opt-in. Admin SSH-only, password/DB/uploads unchanged. SMTP auth and inbox confirmation pending. Domain change backup: /opt/lily-montering/backups/domain-20261004. No Git push."
```

## 3. Зафіксований стан і що перевіряти повторно

- Поточна локальна гілка на момент запису: main.
- Останній commit на момент запису: 5ed0482, Disable Jekyll for GitHub Pages.
- PHP-каталог/адмінка та їх документація є локальними незакоміченими змінами;
  Git commit/push цієї реалізації ще не виконувалися.
- У робочій папці є сторонні PPTX-файли. Не включати їх до commit або deploy
  сайту й не видаляти під час прибирання.
- Production config.php у локальній папці не створено.
- Локальний .env містить випадковий setup-токен. Його значення тут не наведено.
- Локальна адреса: http://localhost:8080; Mailpit: http://localhost:8025.
- Після QA 2026-10-02 були 6 послуг, 9 фото, 0 адміністраторів і 0 заявок.
  Це історичний результат: не вважати його поточним без огляду бази.
- Локально перевірено 28 основних і 5 додаткових груп QA та lint 25 PHP-файлів.
  Деталі: QA_REPORT.md; машинні результати/скриншоти: .qa/.
- Зовнішній cPanel/LiteSpeed, production SSL і реальний SMTP із TLS/auth
  ще не перевірені.

Перед новою роботою перевірити git status, поточні файли, доступність Docker,
наявність серверного config.php, стан цільових файлів/бази й датовані результати
QA. Не відкидати чужі зміни і не перезаписувати заповнені налаштування.

## 4. Архітектура і межі змін

Зберігати наявну легку архітектуру: HTML/CSS, vanilla JavaScript, native PHP,
PDO MySQL, Apache/LiteSpeed. Не переносити на Laravel/WordPress/React/Node
або інший фреймворк без окремого запиту власника.

| Компонент | Файл/каталог | Призначення |
| --- | --- | --- |
| Головна | index.php | Featured послуги, галерея, контакти, форма |
| Каталог/деталі | tjenester.php | Активні послуги й категорії, slug, фільтр |
| Форма | contact.php | Валідація, CSRF, rate limit, DB, пошта |
| Загальна логіка | app/bootstrap.php, content.php | Конфігурація, PDO, сесії, дані |
| Публічні шаблони | app/public-layout.php | Metadata, schema, CSP, header/footer |
| Адмінка | admin/ і app/auth.php, crud.php, admin-layout.php | Login/setup, CRUD, заявки, settings |
| Зображення | app/uploads.php, assets/uploads/ | Перевірка, re-encoding, random filenames |
| Пошта | app/mail.php | Наявний SMTP/native mail |
| SEO/404 | sitemap.php, robots.php, 404.php | Динамічні SEO endpoints і помилки |
| База | database/schema.sql, seed.sql | Схема та початковий контент |
| Вебсервер | .htaccess | Rewrite, cache, заборона доступу, 404 |
| Статичний перегляд | index.html | Сумісність зі старим preview |
| Локальна інфраструктура | Dockerfile, docker-compose.yml | PHP Apache + MariaDB + Mailpit |
| Перевірки | tests/ | Виключно development QA |

Таблиці: admins, service_categories, services, gallery_items,
contact_requests, site_settings. Кодування utf8mb4. Запити через prepared PDO;
імена таблиць у CRUD беруться лише з внутрішнього whitelist.

Структурні норвезькі тексти лишаються у шаблонах. Адмінка керує послугами,
галереєю й невеликим набором налаштувань. Справжні фотографії зберігати.
JPEG/WebP/-900.webp — навмисні варіанти, а не випадкові дублікати.

## 5. Вимоги до production

- PHP 8.2+; PDO/pdo_mysql, mbstring, fileinfo, GD із JPEG/PNG/WebP,
  sessions, OpenSSL для TLS-пошти.
- MySQL або MariaDB з InnoDB/utf8mb4; окрема application database/user.
- Apache або сумісний LiteSpeed; підтримка .htaccess, mod_rewrite і headers.
- Дійсний TLS-сертифікат і HTTPS-прапорець, який отримує PHP.
- Запис у assets/uploads/, PHP session storage і приватний temporary directory.
- Upload limit застосунку — 5 MB. Рекомендовані налаштування PHP:
  upload_max_filesize не менше 6M, post_max_size не менше 8M.
- display_errors=Off, log_errors=On, expose_php=Off; приватний error log.
- Доступ до SMTP з потрібним портом/TLS або підтверджена робота native mail.
- Завантаження файлів і імпорт SQL через cPanel/File Manager/phpMyAdmin
  або доступний захищений спосіб передавання файлів.

Docker, Node.js, npm, Redis, worker, Composer daemon, cron чи SSH для базової
роботи на production не потрібні. GitHub Pages не запускає PHP-каталог,
адмінку або контактну форму.

Коли rewrite недоступний: pretty_urls=false, прямі tjenester.php?slug=...,
sitemap.php і robots.php. Підтримку fallback треба перевірити на тому
хостингу; самі query URL перевірено локально.

## 6. Де зберігаються налаштування

config.example.php дає defaults; server-only config.php накладає значення
через array_replace_recursive. Ключі виробничого config.php:

| Дані з YAML | Ключ застосунку |
| --- | --- |
| launch.app_url | app_url |
| Production environment | app_env = production |
| hosting.rewrite_supported | pretty_urls |
| database.host/port/name/username | database.host/port/name/user |
| Секрет DB | database.password |
| mail.recipient_email/from_email/from_name | recipient_email/from_email/from_name |
| mail.transport | smtp.enabled = true для smtp; false для native_mail |
| SMTP endpoint/auth | smtp.host/port/username/password/encryption |
| Секрет першого setup | setup_token |

На звичайному cPanel .env не читається автоматично застосунком. Локально
його читає Docker Compose й передає environment variables. Production
параметри слід явно задати в config.php або середовищі PHP, якщо хостинг
це підтримує. Не переносити local-development-only паролі на сервер.

Через admin/settings задати company_name, slogan, phone, email,
service_region, public_domain, organization_number, primary_cta, social_url.
Публічний email у settings не замінює SMTP/from/recipient у config.php.
APP_URL і public_domain мають описувати одну фінальну адресу встановлення.

## 7. Список виробничих файлів

Завантажити:

- index.php, tjenester.php, contact.php, 404.php, sitemap.php, robots.php.
- styles.css, script.js, site.webmanifest, .htaccess, config.example.php.
- app/, admin/, assets/, включно з їх прихованими .htaccess.
- Створити production config.php окремо; не брати його з Git.

SQL-файли передати для імпорту через phpMyAdmin; зберігати відкриту SQL-копію
у public_html не потрібно. Статичний index.html опційний: Apache
перенаправляє старий URL на index.php; головною є DirectoryIndex index.php.
Статичні robots.txt/sitemap.xml не є джерелом SEO-даних при робочому rewrite.

Не завантажувати .git/, .env, Docker-файли, tests/, .qa/, README.md,
QA_REPORT.md, CODEX_HANDOFF.md, node_modules/, foto/, foto.zip, PPTX чи backup.
Не перезаписувати наявні production uploads або config.php локальними
копіями при оновленні.

## 8. Порядок запуску

1. Заповнити невідомі потрібні поля YAML і оглянути цільовий сервер.
2. Визначити fresh install чи upgrade; перевірити існуючі файли, записи,
   uploads, домен, DNS та потрібні старі URL.
3. Створити backup файлів, config, uploads і бази; зафіксувати спосіб відкату.
4. Увімкнути PHP/extensions і SSL; перевірити, що PHP правильно бачить HTTPS.
5. Створити DB/user з правами на конкретну application database.
6. Для нової бази імпортувати schema.sql, потім seed.sql.
   Для наявної бази спершу порівняти схему й дані; не імпортувати seed навмання.
7. Завантажити виробничі файли; налаштувати власника/доступ до uploads.
8. Створити config.php з production URL, DB, поштою і новим setup-токеном.
9. Налаштувати rewrite/404. Для підкаталогу включити його в APP_URL,
   перевірити cookie path і змінити ErrorDocument 404 на відповідний шлях.
10. Увімкнути HTTPS redirect лише після перевірки сертифіката й HTTPS.
11. Якщо admins порожня, відкрити /admin/setup.php через HTTPS,
    створити власний акаунт; прибрати setup_token із config після створення.
    Наявну адмінку не скидати заради повторного setup.
12. Заповнити business settings і перевірити canonical/sitemap/контакти.
13. Виконати перевірки нижче; записати revision/package, дату й результати YAML.

schema.sql створює відсутні таблиці, але не є автоматичною міграцією
існуючих колонок. seed.sql використовує стабільні ID/INSERT IGNORE і
призначений для первинного заповнення, а не регулярного production update.

## 9. Що перевірити перед завершенням запуску

- Головна, каталог і активна послуга — HTTP 200; CSS/JS/images завантажуються.
- Невідомий/неактивний slug — 404; загальна невідома сторінка — 404.
- Меню, якірні CTA, форми й таблиці працюють на мобільному та з клавіатури.
- Домен, canonical/OG, robots і sitemap відповідають фінальному URL.
- Без входу admin недоступна; login/logout/setup/CSRF працюють коректно.
- Після setup endpoint недоступний; setup-токен прибрано.
- Реальна заявка є в admin, її лист фактично надійшов отримувачу.
- Телефон/email ведуть на правильні tel/mailto; порожні поля не дають fake links.
- Тестове зображення можна завантажити/видалити; воно має випадкове ім'я.
- /config.php, /config.example.php, /.env, /.git/config і /app/... недоступні.
- CSP не дає помилок; unsafe-eval/unsafe-inline не додано; cookie Secure
  перевірено саме на HTTPS-адресі цільового сервера.
- Production logs не показуються клієнту; backup доступний поза public_html.

Відмови DB/SMTP симулювати тільки в ізольованому staging/local середовищі.
Production не зупиняти заради автоматизованих QA-сценаріїв.

## 10. Локальні команди і межі QA

```powershell
docker compose up -d --build
docker compose ps
docker compose exec -T web php tests/lint.php
docker compose logs --tail=50 web
curl.exe -I http://localhost:8080/
curl.exe -I http://localhost:8080/tjenester
```

Для браузерних тестів потрібні development-only Playwright, Sharp і Edge.
Залежності можна взяти з bundled runtime або встановити локально згідно
з README; шлях bundled runtime знайти через load_workspace_dependencies,
а не припускати, що він однаковий на іншому ПК.

```powershell
node tests/qa.cjs
node tests/extra.cjs
```

Встановити NODE_PATH до каталогу залежностей, якщо вони bundled.
Основний QA тимчасово зупиняє локальні db/mailpit, створює тестовий контент,
прибирає його й очищає rate limits; тестувати тільки окремий local stack.
Не запускати такі тести паралельно. JSON/скриншоти зберігаються в .qa/.
Новий результат тесту записувати з датою; старий PASS не доводить поточний
стан production.

## 11. Поведінка, яку треба зберегти

- Заявка спочатку записується в DB, потім надсилається email.
- DB успішна, email ні: підтвердження реєстрації; admin бачить email_sent=0.
- DB ні, email успішний: заявка доставлена поштою, у базі її немає.
- Обидва канали відмовили: HTTP 503 і повідомлення клієнту; JS не очищає форму.
- При відмові DB головна має резервний контент; каталог/admin дають
  контрольований 503 без SQL/credentials.
- Немає автоматичного mail retry worker. Недоставлені листи потребують
  перегляду збережених заявок адміністратором.
- Сесії/CSRF/rate limits, password_hash/verify, output escaping і prepared
  SQL не послаблювати.
- JSON-LD має точний динамічний CSP hash; не замінювати його unsafe-inline.
- Upload: лише JPEG/PNG/WebP, до 5 MB, GD re-encoding; жодних SVG/PHP/HTML.
- Видалення uploads не має видаляти оригінальні фото чи файли,
  які ще використовуються іншими записами.
- Не вигадувати бізнес-контакти, адресу, відгуки, рейтинги, ліцензії або ціни.

## 12. Оновлення, відкат і подальша підтримка

Перед оновленням записати deployment revision/package й зберегти backup.
Відкат файлів та DB робити як узгоджену операцію; не відновлювати стару базу
поверх нових клієнтських заявок без збереження цих заявок.

Uploads/config зберігати при кожному релізі. При зміні CSS/JS оновлювати
версію asset URL, якщо потрібно уникнути старого browser cache.
Схему змінювати явним upgrade SQL; для змінених slug додати потрібні redirects.

Власник визначає строки зберігання заявок, резервних копій і серверних логів,
а також відповідального за перегляд admin та backup. Для лімітерів
використовується приватний temp storage; його обслуговування не потребує
обов'язкового cron для роботи сайту.

Публічного password reset і розширеного керування admin-акаунтами немає.
Відновлення доступу виконувати контрольовано через хостинг/DB із новим
password_hash, зберігаючи наявні записи та не видаляючи admins для setup.

## 13. Запис фактичного розгортання

Заповнити після роботи, без паролів:

| Поле | Значення |
| --- | --- |
| Дата/час і часовий пояс | 2026-10-03, оновлення почато 19:41:13 UTC / 22:41:13 Київ |
| Сервер/середовище | Zomro VPS 188.137.232.136, тимчасове HTTP staging, production Docker stack |
| Домен і фінальна APP_URL | http://188.137.232.136:8080/; фінальний HTTPS-домен ще не задано |
| Revision або назва пакета | public-fixes-20261003.tar.gz + admin-panel-20261003.tar.gz; файли ще не закомічено локально |
| Backup файлів/DB | /opt/lily-montering/backups/admin-20261003T202349Z/: source.tar.gz + image-id.txt + приватний environment.env (0600); копію DB не створювали |
| Зміни конфігурації/DNS | ADMIN_ALLOWED_IPS для SSH-тунелю, SETUP_TOKEN очищено; SMTP/DB/інші .env та uploads збережені; DNS без змін |
| Виконані перевірки та результат | Локальні фото/телефон/JS/no-JS/DB/Mailpit, PHP lint; онлайн 40 сторінок на 5 ширинах, якірні переходи, CSP, фото, 403 приватного PHP і 404: PASS |
| Невиконані перевірки | Реальна SMTP-доставка й HTTPS; справжню онлайн-заявку під час QA не надсилали |
| Залишкові питання | Email отримувача/відправника, SMTP, зміна тимчасового пароля власником, домен/TLS, backup/retention policy |
| Відповідальний/відкат | Codex виконав оновлення; попередній Docker image ID записано в image-id.txt; не відновлювати DB поверх нових заявок |

Після нового розгортання оновити дату й стан цього файла, щоб він лишався
актуальною передачею контексту, а не лише первинним планом.

## 14. Оновлення фото, сторінок і телефону 2026-10-03

- app/content.php: service_media() віддає задане фото, а якщо воно порожнє
  або відсутнє на диску, використовує фото відповідної категорії. Дані services
  не переписувались; завантажене адміністратором фото має пріоритет.
- Додано 4 реальні фото з архіву foto/: lys-trehylle-montert, veggskap-i-tre,
  kommoder-i-lyst-tre, lavt-skap-under-skratt-tak, кожне у JPEG/WebP/-900.webp.
- Картки мають стабільну область фото; сторінки послуг отримали зображення,
  прибрано дубль короткого опису, скориговано breadcrumbs і відступи,
  footer розташовується не вище нижнього краю короткої сторінки.
- JS враховує фактичну висоту header при переході на якір. Мобільна CTA
  ховається, коли видно контактну секцію; без JS ця зайва плаваюча CTA не показується.
- app/phone.php + phone_country: NO/SE/DK/FI/UA/PL/DE/GB/OTHER; код +47
  за замовчуванням, нормалізація номера перед DB/email, підтримка повного
  номера з + або 00. Це перевірка формату, не підтвердження дійсності номера.
- admin/settings показує отримувача/відправника/транспорт пошти, без паролів.
  На сервері MAIL_RECIPIENT/MAIL_FROM/SMTP_HOST порожні. Заявки йдуть лише
  в DB; існує 1 заявка, admins=0. Спочатку налаштувати безпечний доступ/admin
  і підтверджені поштові параметри. Settings.email не задає отримувача заявок.
- Схема БД незмінна. Fingerprint services/gallery/settings до й після:
  119154b6d17ffda495c67ea9b5e00effe21d4ceb544c883993eda877d50ec2bd.
- Виробничий код baked into image: недостатньо просто завантажити файли.
  Використовувати docker compose -f docker-compose.production.yml
  up -d --build --no-deps web; не запускати down -v і не імпортувати seed.
- Локальний старий Docker stack має невідповідність DB-пароля; його .env
  і DB не змінювались. Нові тести пройшли в окремому lily-qa-fixes
  на localhost:18081 з окремою базою та Mailpit; не вважати старий localhost:8080
  перевіреним поточним preview без виправлення локальної конфігурації.
- Нові regression tests: tests/public-fixes.cjs, tests/delivery-settings.cjs;
  результати/скриншоти в .qa/public-fixes/ і .qa/online-public-fixes/.

## 15. Активна панель власника 2026-10-03

Актуальний стан після розділу 14: admins=1, requests=1. Власник підтвердив
логін masxpros@gmail.com. Першого адміністратора створено через CLI/SSH
із password_hash, без перезаписування інших таблиць. Контентний fingerprint
і наявна заявка незмінні. Пароль випадковий, тимчасовий; власник ще має
змінити його в Konto. Не записувати пароль у цей файл чи чат.

Доступ на поточному ПК: http://127.0.0.1:18090/admin/.
Це SSH-тунель до виробничого сайту, не локальна копія застосунку.
Публічні /admin/login.php та /admin/account/ на IP:8080 повертають 403;
головна сайту лишається HTTP 200. На сервері ADMIN_ALLOWED_IPS:
127.0.0.1, ::1, 172.19.0.1 (Docker host gateway lily_default).
Застосунок використовує лише REMOTE_ADDR, не X-Forwarded-For.
Якщо Docker gateway зміниться, оновити whitelist через SSH. Не прибирати
захист до налаштування й перевірки HTTPS. Для інших хостингів порожній
admin_allowed_ips вимикає цей опційний whitelist.

Локальні приватні файли з ACL тільки для Anderson:

- Credentials reference: C:/Users/Anderson/.codex/private/lily-admin/access-2026-10-03T20-25-15-166Z.json.
- Launcher: C:/Users/Anderson/.codex/private/lily-admin/Start-LilyAdmin.ps1.
- SSH identity залишається C:/Users/Anderson/.ssh/tezamed_zomro_ed25519.

Launcher запускає прихований ssh -N із bind тільки 127.0.0.1:18090;
не відкриває порт для мережі, відмовляється використовувати чужий процес
на зайнятому порту. Після перезапуску ПК виконати:

```powershell
& "$env:USERPROFILE\.codex\private\lily-admin\Start-LilyAdmin.ps1"
```

Функціонал: огляд/лічильники/швидкі переходи, заявки з пошуком, фільтрами,
статусами/архівом/видаленням; CRUD послуг/категорій; uploads/галерея;
публічні контакти й налаштування; Konto для зміни власного пароля.
Це один повноправний власник, не багаторольова система для працівників.

Нові файли: admin/account/index.php, tests/admin-account.cjs.
Зміни auth: session auth_version = SHA-256 поточного password_hash;
після зміни пароля інші сесії відхиляються при наступному запиті.
Поточна сесія й CSRF регенеруються. Зміна пароля потребує старого пароля,
CSRF, підтвердження нового й rate limit. SQL update порівнює попередній hash,
щоб не перезаписати конкурентну зміну. DB schema не змінена.

SETUP_TOKEN очищено на сервері; compose тепер дозволяє порожній токен.
GET setup через тунель повертає 404. Не видаляти admins для повторного setup.
Якщо потрібне відновлення доступу, виконати контрольовану зміну hash через
довірений SSH, зберігаючи заявки й решту даних.

Перевірки: 28 основних + 5 додаткових QA PASS; новий account regression
PASS (35 responsive admin views, password change/CSRF/session invalidation/IP
policy). На production через тунель: успішний вхід власника, 14 admin views,
logout, noindex/no-store, setup 404, публічна адмінка 403, головна 200: PASS.
Онлайн заявки/контент не редагувались. Звіти: .qa/admin-account/results.json,
.qa/production-admin/results.json; попередні .qa/results.json/extra-results.json
оновились після повторного повного QA в окремому lily-qa-fixes stack.

Backup цього релізу: /opt/lily-montering/backups/admin-20261003T202349Z/.
environment.env містить секрети, root-only; не переносити його в Git/чат чи
public_html. image-id.txt відноситься до версії ДО активації панелі; відкат
на неї прибере IP-захист. Якщо відкат потрібен, спочатку закрити мережевий
доступ до admin, а не залишати активні credentials на відкритому HTTP.

## 16. Назва бренду: попередні кандидати, історія

Власник уточнив 2026-10-03: LILY є тимчасовою назвою файлу/проєкту,
а не остаточною назвою компанії. Ринок: Норвегія, монтаж меблів, шаф,
настінне кріплення та handyman-послуги. На сайті поки лишається LILY.
Не перейменовувати сайт, репозиторій або серверні шляхи до вибору власника.

Рекомендація: RettMontert, асоціація з "правильно змонтовано".
Можливий опис: "Møbelmontering og handyman".
Можливий слоган: "Rett montert. Ryddig levert."
Альтернативи: MonterKlar (складена брендова назва про монтаж і готовність),
Fiksro (коротка вигадана назва з асоціаціями fiks + ro).

Перевірено 2026-10-03 о 23:51 Europe/Kiev через офіційний Norid RDAP:

| Назва | Домен | Результат GET |
| --- | --- | --- |
| RettMontert | rettmontert.no | 404, "Domain is not registered" |
| MonterKlar | monterklar.no | 404, "Domain is not registered" |
| Fiksro | fiksro.no | 404, "Domain is not registered" |

Джерела: https://rdap.norid.no/domain/rettmontert.no,
https://rdap.norid.no/domain/monterklar.no,
https://rdap.norid.no/domain/fiksro.no.
Інтерпретація відповіді: https://teknisk.norid.no/en/integrere-mot-norid/rdap-tjenesten/.
Це стан на час перевірки, не бронювання і не гарантія майбутньої доступності.
Домен monterro.no вже зареєстрований, не пропонувати як вільний.

Попередні запити Brønnøysund API enheter?navn= для Rettmontert, Monterklar,
Fiksro дали totalElements=0. Це не юридична перевірка прав на бренд.
Торговельні марки ще не перевірені; перед реєстрацією перевірити
https://www.navnesok.no/ та виконати актуальну повторну перевірку домену.
Вимоги .no: https://teknisk.norid.no/en/administrere-domenenavn/generelle-krav/.
Для компанії потрібно перевірити норвезьку реєстрацію/org.nr та адресу.

Власник надав посилання на власний логотип:
https://chatgpt.com/s/m_6a42dbfe29348191bbbd5586d48fbe5b.
Публічний перегляд показав лише оболонку ChatGPT, не сам логотип.
Надіслано запит прикріпити PNG/SVG або скриншот. Не вважати логотип
переглянутим і не описувати його вигляд без вкладення.

Жоден домен не придбано/заброньовано. DNS, бренд у застосунку, логотип,
дані БД та production не змінювались у межах цього підбору назви.

## 17. Обраний бренд і заборона публікації

2026-10-04 власник обрав назву "fiksitt" (нижній регістр) і планований
домен "fiksitt.online". Попередній варіант fiksitt.store скасований.
Домен ще не придбаний. Доступність не перевірялась у межах цієї заміни.
Власник прямо попросив змінювати лише локально і повідомить, коли
пушити на сервер. НЕ виконувати git push, deploy, SSH-зміни, купівлю домену
або DNS/HTTPS-налаштування без нового прямого запиту власника.

Локально оновлено: початкові/резервні налаштування назви, статичний
preview/404, адмінпанель, листи, manifest, іконки та CSP-хеш JSON-LD.
Заголовки публічних PHP-сторінок, footer, hero та schema беруть назву з
site_settings.company_name. Для нової локальної БД seed містить fiksitt.
Існуюча БД має пріоритет над defaults: при майбутньому погодженому deploy
змінити company_name через адмінку або точковий UPDATE, не переімпортувати
seed.sql і не скидати заявки/адміністратора/контент.

Не ставити https://fiksitt.online у public_domain/APP_URL до фактичного
підключення DNS та перевірки HTTPS. Не вигадувати email на новому домені.
Папка LILY, Git remote lily-montering, Docker project/volume, DB identifiers,
cookie lily_session і технічні логи залишаються без перейменування.
assets/fiksitt-mark.svg і його PNG-похідні є тимчасовою F-монограмою,
не логотипом власника за недоступним посиланням ChatGPT.
Попередні L-іконки збережені, але активні шаблони їх не використовують.
Поточний онлайн-сайт і SSH-тунель 18090 продовжують показувати старий бренд.

Локальний preview запущено на http://127.0.0.1:18081/ в окремому Docker
project fiksitt-brand-preview, зі своєю БД/volume fiksitt-brand-preview_db_data.
Це не production і не тунель. Не використовувати production credentials.
Read-only regression: tests/branding.cjs з QA_URL=http://127.0.0.1:18081
та COMPOSE_PROJECT_NAME=fiksitt-brand-preview. Перевірено PHP lint,
public/admin/mail/static branding, manifest/іконки, точні CSP-хеші та
responsive viewports 320/375/768/1440. Скриншоти: .qa/branding/.
Повторний запуск: docker compose --env-file .qa/fiksitt-preview.env
-p fiksitt-brand-preview up -d. Файл .qa/fiksitt-preview.env виключений із
Git/Docker build і містить лише development-only дані нової локальної БД.
Не використовувати головний .env для перезапуску цього preview.

## 18. Локальна SEO та оптимізація адмінки, 2026-10-04

Нові файли: app/seo.php, admin/seo/index.php, SEO_LAUNCH.md,
tests/seo-admin.cjs, tests/performance.cjs. SEO_LAUNCH.md виключений із Docker
build і закритий HTTP-правилом .htaccess; production compose також закритий.
Розділ 17 із забороною deploy/push лишається чинним.

Налаштування home_seo_title/home_seo_description/seo_indexable зберігаються
в існуючій site_settings; міграція не потрібна, відсутні поля мають defaults.
ВАЖЛИВО: default seo_indexable=0. Індексація можлива лише при одночасних
APP_ENV=production, seo_indexable=1 та коректному HTTPS public_domain без
шляху/порту, localhost/test/private IP. Прапорець вмикати тільки після перевірки
DNS/сертифіката/фактичного сайту. Локальна копія завжди noindex. Ця перевірка
конфігурації не є мережевою перевіркою сертифіката або володіння доменом.

public_head генерує узгоджені meta/X-Robots-Tag, title/description, OG/Twitter,
реальні image dimensions, правильний responsive preload. 404 не отримує
canonical головної. Метадані послуги fallback містять послугу/region/brand.
Schema: LocalBusiness без вигаданих адрес/відгуків, Service + BreadcrumbList,
каталог ItemList видимих послуг. Canonical фільтра вказує на основний каталог;
noindex для консолідації фільтрів не застосовується окремо від site readiness.
Прямі PHP/trailing slash aliases дають 301 лише при pretty_urls=true для
каталогу/послуг; index.php/index.html -> root зі збереженням query.
REQUEST_URI slug має пріоритет над конфліктним ?slug= на pretty route.
Невідомі категорії тепер повертають 404; tests/extra.cjs оновлено.
robots/sitemap мають no-store; sitemap lastmod лишається реальним updated_at.
Contact confirmation/results завжди noindex. Статичний preview теж noindex.

Адмінка: SEO status/table, home metadata fields + indexing checkbox,
service search preview + Unicode character counters; CRUD literal search,
active filter, bounded pages, counts та selective list columns; requests list
не читає message bodies, detail має безпечні tel/mailto. Dashboard об'єднує
request counts в один aggregate. CSS/JS admin cache version=3.
Старі auth/session/CSRF/IP-політики не послаблювались.

image_dimensions кешує metadata лише в межах запиту. image_html має srcset
зі справжніми ширинами й sizes; маленькі варіанти з однаковою шириною
дедуплікуються. upload_image створює JPG + 320/900 WebP (коли GD підтримує),
remove_unused_upload прибирає також похідні лише після перевірки references.
Існуючі uploads не перегенеровувались. Не видаляти довільні файли uploads.

QA: 27 основних + 5 extra + 7 SEO/admin PASS, 20 нових responsive admin views,
PHP lint та branding PASS. Тести запису запускаються тільки в ізольованому
Docker fiksitt-seo-qa на 18082/18026, не на preview 18081 і не на тунелі 18090.
Тестові записи й uploads прибрано. Докладно: QA_REPORT.md та .qa/seo-admin/.
Performance samples у .qa/performance/results.json не Lighthouse/field score.
Жодної заяви про гарантовані позиції, 100 SEO score або реальний INP немає.

За прямим підтвердженням власника створено ОКРЕМИЙ локальний admin
masxpros@gmail.com тільки у fiksitt_preview (18081). Перед створенням
перевірені APP_ENV=local, APP_URL=http://127.0.0.1:18081 і DB_NAME=fiksitt_preview;
існуючий пароль не перезаписувався. Випадковий пароль лише в owner-only файлі
C:/Users/Anderson/.codex/private/fiksitt-local-admin/access-local-2026-10-04.json,
поза workspace/Git, папка має ACL тільки Anderson. У БД зберігається hash.
Вхід, dashboard/account/SEO на mobile/desktop, noindex та logout перевірені.
Пароль server admin НЕ змінено. Файл не друкувати в чат/логи, не копіювати
в Git чи public_html. Після власної зміни пароля приватний файл стане застарілим.
Скриншоти реальної локальної панелі: .qa/local-owner/.

Ізольовані fiksitt-seo-qa containers/network зупинені й прибрані; власний QA
volume збережено. Для повторного запуску: docker compose --env-file
.qa/fiksitt-seo-qa.env -p fiksitt-seo-qa up -d. Файл ignored development-only.
Основний fiksitt-brand-preview на 18081 лишається запущеним для власника.

## 19. Фото в адмінці, локальне виправлення 2026-10-04

Чотири services.image у початкових даних порожні. Публічний сайт уже
використовував service_media fallback, але admin list/editor його не застосовував.
crud_page тепер показує той самий service_media результат у таблиці/редакторі.
List query додає category_slug через indexed category lookup; редактор отримує
slug із вже завантажених категорій. Власне фото має пріоритет; стандартне фото
в редакторі позначається Standardbilde. Fallback не записується в DB і не
підмінює значення services.image; завантаження/видалення власного фото незмінні.
Адмін CSS v4: стабільна ширина image cell, кнопки дій не переносяться посеред слова.

tests/admin-media.cjs PASS у ізольованому fiksitt-seo-qa: усі 6 мініатюр,
editor/public photo equivalence, 375/947/1440, custom-slug category fallback,
invalid-path fallback, custom-image priority і незмінність service rows.
PHP lint і git diff --check PASS. Скриншоти: .qa/admin-media/.
Сервер/Git push/дані власника не змінювались.

## 20. Manual QA і поштові повідомлення, 2026-10-04

Нові локальні зміни: app/mail.php використовує vendored PHPMailer 7.1.1
(unmodified Exception/PHPMailer/SMTP + LGPL LICENSE, version/checksums у vendor README).
contact.php зберігає DB-first, після цього deliver_request(id) із named MySQL
lock. Захист від повтору email_sent=1/spam, шаблон із номером/UTC/admin URL,
клієнт лише Reply-To, не From. SMTP none тільки local без auth; TLS і CA
перевірка не вимикаються, debug=0, bounded SMTP timeouts.
Адмін settings додає POST/CSRF test_email 3/10 хв; requests додає send_email
10/10 хв тільки для ненадісланих заявок, той самий delivery lock.
SMTP acceptance не гарантує inbox; не обіцяти exactly-once при втраті ACK
або збою DB після acceptance. Немає automatic background mail retry.
Schema unchanged. MAIL_DELIVERY.md містить activation/limitations/contract.

Майбутній реальний recipient masxpros@gmail.com. Через непокуплений домен
вибрано початковий Gmail SMTP 587 STARTTLS із таким самим From.
Немає SMTP credential: Gmail НЕ активовано. Приватний незадіяний шаблон із
порожнім SMTP_PASSWORD: C:/Users/Anderson/.codex/private/fiksitt-mail/gmail.smtp.env.
ACL лише Anderson, поза workspace/Git. Власник вводить app password сам,
не в чаті; не друкувати файл. Не підключати приватні дані без необхідності.
Обидва local Docker stacks лишаються Mailpit-only. Production config untouched.

Ручні desktop/mobile UI перевірки, upload/category/service/gallery/settings/
request/status/archive/login/logout, outage/retry/test-email й Mailpit receipt
описані чесно в MANUAL_QA.md з неперевіреними станами й скриншотами.
Виправлено red-empty-fields після успішного reset; aria-invalid очистка,
перевірка на input лише touched field, JS v4. tests/qa.cjs має regression.
Його settings checkbox payload виправлено: unchecked seo_indexable omitted.
Результати: qa 28 + extra 5 + seo-admin 7 + mail-notifications 6 PASS;
admin-media PASS. Не замінювати цим реальний Gmail/production/Firefox/iOS QA.
Нові docs закриті .htaccess і виключені .dockerignore.
Сервер і Git push не дозволені до нового явного запиту власника.

## 21. Публікація VPS та SMTP preflight, 2026-10-04

Власник явно дозволив оновлення VPS і повторний тест. Реліз
/opt/lily-montering/releases/fiksitt-20261004.tar.gz опубліковано через SSH;
Git commit/push не виконувалися. Backup каталогу з YAML містить source.tar.gz,
uploads.tar.gz, environment.env (600), database.sql, image-id.txt і state-before/after.
Backup directory 700; SQL не виводити у чат і не імпортувати автоматично.
Перша спроба відкотилась через curl/grep SIGPIPE у smoke-команді, а не PHP bug;
команду виправлено, друга завершилась успішно. Відкат image/env виконаний,
повний DB restore не випробувано. DB volume/seed/schema не перестворювались.

Контрольні суми admins, requests, services, categories, gallery та settings
(крім company_name) збіглися до/після deploy. Єдиний content UPDATE:
company_name LILY Montering -> fiksitt за exact-match умови.
Автоматичний production QA: 40 public views (320/375/768/1024/1440),
16 admin views (320/1440), 6 admin photos, owner login/logout, setup 404,
public admin 403, private-file denial/404, no runtime/CSP errors.
Ручна public submission збережена як #2, TEST fiksitt deployment 2026-10-04,
email_sent=0; UI показує registered, не sent. Original request #1 hash незмінний.
Manual login/detail/settings/logout перевірені. Proof .qa/production-20261004/.

На сервері задані non-secret Gmail target параметри, SMTP_PASSWORD порожній.
Приватний файл власник заповнив іншим Gmail акаунтом. Виконано ОДИН ephemeral
AUTH LOGIN preflight з VPS через STARTTLS; Gmail 535 5.7.8, authenticated=false.
Надані дані не збережено в server .env/образі/логах. Не повторювати без зміни
credential. Потрібен app password (2FA, Google owner action) або OAuth2.
Для нового акаунта MAIL_FROM має дорівнювати SMTP_USER, MAIL_RECIPIENT лишається
masxpros@gmail.com. Ignored .qa/activate-production-mail.cjs перевіряє AUTH
до будь-якого запису серверної конфігурації; секрети не в command-line/output.
Після валідного preflight активує лише mail vars, робить окремий env backup
і recreates тільки web. Потрібні admin test mail + resend #2 + власник inbox/spam.
SMTP acceptance не називати підтвердженою inbox delivery.

## 22. Пояснення SMTP та очікування домену, 2026-10-04

Власник попросив спершу письмове пояснення, поки купує домен. MAIL_DELIVERY.md
доповнено user guide: DB-first flow, SMTP/TLS vs HTTPS, sender/recipient/Reply-To,
відсутність client autoresponse/background queue, DNS A/www/MX/SPF/DKIM/DMARC,
post-purchase input checklist і criteria доставки. Власник показав Google
App Passwords unavailable. Не припускати точну причину зі скриншота; Google
вказує 2FA prerequisite та можливі account/security restrictions.
Не вимикати захист і не повторювати відхилений AUTH без нових даних.
Доменний SMTP запропонований як альтернатива, не обраний/не куплений;
Gmail OAuth2 не реалізований. Після купівлі потрібні точний домен, реєстратор,
DNS host, existing records/mail, provider docs і погодження зміни DNS.
Лише локальна документація; нового server deploy/DNS/config change не було.

## 23. Домен надано, DNS inspection 2026-10-04

Власник надав fiksitt.online та pdns1.hostiq.ua/pdns2.hostiq.ua. Це не є
підтвердженою active delegation. Read-only Resolve-DnsName: 1.1.1.1 та
8.8.8.8 повертають NXDOMAIN; direct SOA query pdns1.hostiq.ua -> REFUSED.
Не вважати цей результат доказом, що домен не куплений; реєстрація/активація
або зона можуть ще не бути готові. HOSTiQ офіційно описує pdns1/pdns2 для
їхнього VPS/SolusVM з попереднім створенням DNS zone. Для domain-only акаунта
є окрема опція NS для керування DNS без хостингу в панелі домену.
Джерело: https://hostiq.ua/wiki/ukr/faq-how-to-change-dns-records/.
Запитано тип покупки і скриншот domain DNS management без секретів.
Плановані записи після огляду зони: A @ -> 188.137.232.136,
CNAME www -> fiksitt.online. Не змінювати MX/TXT/CAA/AAAA без огляду.

VPS ss/docker ps: 80/443 вже займає app-nginx-1 (nginx:alpine), інший Compose
проєкт app із API/Postgres/admin/web. Lily web досі 8080. Не запускати другу
reverse proxy на зайнятих портах, не зупиняти app stack. Потрібен огляд і
окремий nginx vhost/certificate без зміни чужих routes. Через proxy PHP
REMOTE_ADDR може стати внутрішнім IP; не розширювати ADMIN_ALLOWED_IPS так,
щоб /admin став public. HTTPS flag передавати через довірену конфігурацію,
а не сліпо довіряти клієнтському X-Forwarded-Proto.
DNS/VPS/proxy/SMTP не змінювались у цій перевірці. Indexing залишається disabled.
Власник підтвердив: у HOSTiQ куплено лише домен, не хостинг. Наступний крок:
domain-only DNS management у HOSTiQ; новий web hosting не потрібен, чинний VPS
Zomro зберігається. Скріншот actual DNS zone ще не надано.

## 24. DNS записи збережені власником, 2026-10-04

Власник перейшов у domain-only HOSTiQ DNS management та показав success banner:
root host empty, A 188.137.232.136; www CNAME fiksitt.online. Скриншот панелі
попереджає про 2-48 годин оновлення; це оцінка провайдера, не guaranteed ETA.
Authoritative ns1.openprovider.nl відповідає: root A 188.137.232.136 TTL86400,
www CNAME fiksitt.online TTL86400 з тим самим A. Google DoH NS query показує
ns1.openprovider.nl/ns2.openprovider.be/ns3.openprovider.eu. На момент огляду
recursive 1.1.1.1/8.8.8.8 ще SERVFAIL; Google A query коментує cached delegation
на pdns1/pdns2, які відмовляють запиту. Не змінювати правильні A/CNAME вдруге;
повторно перевірити propagation перед certificate issuance.

Read-only nginx inspection: /opt/tezamed/app/nginx/nginx.conf монтований
single-file ro в app-nginx-1, certificate directory ./nginx/ssl і shared ACME
webroot ./nginx/certbot. Немає conf.d include в чинному monolithic http block.
Чинні server_names tezamed.com/www/IP/localhost; тому DNS pointing сам по собі
не підключає fiksitt і може потрапити в default redirect на tezamed.
Контейнер app-nginx-1 на app_default, IP172.18.0.3, gateway172.18.0.1.
Потрібні guarded config backup/change + nginx -t + reload для нового vhost,
окремий certificate та renew flow. Не використовувати сертифікат tezamed для
fiksitt. TLS/APP_URL/public_domain/Secure cookie/admin isolation мають бути
перевірені разом. Нічого на VPS/сертифікатах/config/DNS не змінено цим агентом.

## 25. Tezamed і fiksitt на спільному новому VPS, 2026-10-04

Власник прямо уточнив: ОБИДВА сайти переводяться на новий IP188.137.232.136.
Public DNS Tezamed поки root/www A193.111.63.229; NS pdns1/pdns2.hostiq.ua.
Попереднє припущення, що активний Tezamed залишиться на іншому VPS, скасовано.
New-IP smoke із curl --resolve (без insecure): https://tezamed.com/ -> /ua,
HTTP200, TLS verify0; www HTTPS -> tezamed.com HTTP301, TLS verify0.
app-postgres і lily-db healthy, обидва compose stacks running. Ця перевірка
не підтверджує full DB/media synchronization, backup restore або бізнес-флоу
Tezamed; перенесення власник описує як уже виконане, потрібно звірити стан.

Тепер app-nginx-1 є чинним shared ingress для двох production сайтів: routes
Tezamed зберігати; fiksitt додавати окремим vhost, не замінювати default/site
Tezamed і не використовувати його certificate для fiksitt. Public Tezamed DNS
потрібно перевести на188 тільки після звірки актуальності перенесених даних.
Спочатку оглянути existing Tezamed DNS zone; змінити root/www A або зберегти
www CNAME, якщо він існує. Не чіпати MX/TXT/NS без потреби. Якщо старий HOSTiQ
VPS plan буде скасовано, перевірити з provider, чи продовжить працювати pdns
zone; перед зміною NS перенести всі записи. Не вимикати старий сервер лише
за фактом HTTP200 на новому: врахувати propagation, uploads і split DB writes.
У цій перевірці DNS і серверні конфігурації не змінено.

## 26. Tezamed DNS збережено агентом, 2026-10-04

Власник відкрив авторизовану сторінку HOSTiQ для tezamed.com і доручив
продовжити перенесення на188.137.232.136. На момент відкриття вже вибрано
domain-only DNS management з NS ns1.openprovider.nl/ns2.openprovider.be/
ns3.openprovider.eu; агент NS не змінював. Нова зона була порожня.
Створено і збережено лише два записи: empty/root A188.137.232.136 та
www CNAME tezamed.com. Панель підтвердила успішне збереження обох записів.
Не змінювалися MX/TXT, NS, domain lock, автопродовження чи серверні конфіги.

Direct Resolve-DnsName через ns1.openprovider.nl після збереження:
tezamed.com A188.137.232.136 TTL86400; www CNAME tezamed.com TTL86400,
який резолвиться в той самий новий IP. Це authoritative zone verification,
не підтвердження завершення global propagation. До зміни recursive DNS
ще містив старий IP193.111.63.229; кеш може тимчасово спрямовувати туди.
Провайдер показує 2-48 годин як орієнтовний час оновлення.

Повторний HTTPS smoke на новому IP через curl --resolve без -k:
https://tezamed.com/ -> /ua HTTP200, ssl_verify_result0. Повна перевірка
перенесення DB/media і business flows Tezamed цим не виконана. Старий VPS
не вимикати до propagation і перевірки актуальності даних/split writes.
fiksitt domain vhost/HTTPS та SMTP залишаються окремими незавершеними кроками.

Локальні докази, не для Git/deploy: .qa/dns-tezamed-20261004/before.json,
after.json і after.jpg. Screenshot after.jpg показує домен, success banner
та обидва записи без приватного Support PIN. Таб користувача залишено відкритим.

## 27. fiksitt HTTPS і окремий vhost, 2026-10-04

Власник повідомив про fiksitt.online -> tezamed.com. DNS root/www через
8.8.8.8 уже відповідав188.137.232.136; причина була в default Nginx vhost,
не в однакових NS. На VPS додано include /etc/nginx/ssl/fiksitt-vhost.conf
в наявний http block. Решта routes Tezamed не змінена. Host source vhost:
/opt/tezamed/app/nginx/ssl/fiksitt-vhost.conf, доступний через existing ro mount.
HTTP root/www -> https://fiksitt.online; HTTPS www -> canonical root;
ACME HTTP challenge має окремий webroot route без canonical redirect.

Окремий Let's Encrypt certificate fiksitt.online + www.fiksitt.online:
/etc/letsencrypt/live/fiksitt.online, expires2027-01-02. Копії для Nginx:
/opt/tezamed/app/nginx/ssl/fiksitt/{fullchain.pem,privkey.pem}, key mode600.
certbot.timer enabled/active; deploy hook fiksitt-nginx.sh копіює cert/key,
перевіряє nginx -t та reload. Existing tezamed-nginx.sh отримав лише lineage
guard, щоб не рестартувати Tezamed при renew іншого сертифіката; ручний запуск
без RENEWED_LINEAGE залишається сумісним. Dry-run renewal + deploy hooks PASS.

Backend isolation є ОБОВ'ЯЗКОВОЮ частиною схеми: public188:8080 закрито;
Docker binds127.0.0.1:8080 для існуючого SSH tunnel та172.18.0.1:18080 для
app-nginx. Останній proxy_pass використовує http://172.18.0.1:18080.
NAT робить PHP REMOTE_ADDR=172.19.0.1, тому TRUSTED_PROXIES саме172.19.0.1,
не container IP172.18.0.3. Не відкривати backend публічно з таким trust list.
PHP https() приймає X-Forwarded-Proto=https лише від exact configured proxy;
Nginx переписує цей header і блокує location /admin. ADMIN_ALLOWED_IPS не
змінено; panel через SSH tunnel досі працює. Після зміни Docker network
повторно звірити bridge IPs, private binds, public403 і Secure cookie.

Server .env APP_URL=https://fiksitt.online. DB public_domain порожній,
seo_indexable0: canonical використовує APP_URL, indexing навмисно не включено.
SMTP credentials не змінено; доставка все ще pending. DB/uploads/admin
password не змінювалися. Rebuild/recreate лише lily-web, DB не перезапускалася.
Локальні source updates: bootstrap https(), config trusted_proxies,
production compose private bindings/environment, .env.example і PHP test.

Перед змінами backup /opt/lily-montering/backups/domain-20261004 mode700:
nginx.conf, private env600, bootstrap/config/compose, previous web-image ID,
existing Tezamed renew hook. Не відновлювати старий public bind без аудиту.
Earlier full source/uploads/DB backup з розділу20 також зберігається.
Немає Git commit/push. Локальні scripts/proof: .qa/domain-fiksitt-20261004;
це історія виконаних етапів, НЕ запускати старі prepare/activate scripts повторно.

Verification: TLS verify0 і HTTP200 для root/www; HTTP/www canonical redirects;
Tezamed root/www на новому IP -> /ua HTTP200 TLS0; /admin та encoded /%61dmin
403, /.env403; old public8080 connection refused; private admin303 -> login;
Secure/HttpOnly/SameSite=Lax cookie; PHP syntax і7 proxy scheme cases PASS.
Browser: правильний fiksitt heading, hero image та service detail image loaded,
URL лишається fiksitt.online. Screenshot .qa/domain-fiksitt-20261004/live.jpg.

## 28. Mobile gutters і шапка, 2026-10-04

Власник надав iPhone Safari screenshot: process section притиснута до краю,
sticky header перекриває контент під browser chrome. Відтворено root cause
на локальному18081: mobile .section padding:4rem0 перекривав horizontal
padding full-width .process-section/.audience-section, computed sides0px.
Змінено лише top/bottom padding у mobile override: бокові16px збережені.

До980px header тепер position:relative, opaque без backdrop-filter; він у
нормальному потоці і прокручується разом із сторінкою. Desktop sticky
залишається. Mobile menu absolute під header, з обмеженою viewport height і
власним scroll; anchors мають16px scroll-padding без відступу sticky header.
CTA/footer враховують env(safe-area-inset-bottom/right) з fallback0.
Viewport-fit не змінено: сайт не бере на себе fullscreen safe-area layout.
Рекомендація safe-area: https://webkit.org/blog/7929/designing-websites-for-iphone-x/.

public-layout.php stylesheet cache key v3->v4; script.js лишився v4 без змін.
Деплой ТІЛЬКИ styles.css і app/public-layout.php, після звірки server/local
diff та guarded hashes. Backup /opt/lily-montering/backups/mobile-safari-20261004
mode700 містить попередні2 файли й web image ID. Rebuild/recreate тільки
lily-web; DB, uploads, env, SMTP, nginx, DNS та Tezamed не змінено. Git не пушили.

QA: локально320x568,375x667,390x844,430x932,844x390,768x1024,1440x900.
Немає horizontal overflow; process/audience headings і grids усередині
бічних відступів, mobile header relative, desktop sticky. Menu open/close і
anchor Prosess PASS. На production390x844 /styles.css?v=4 активний,
heading не накритий header, sides16px, menu closed після переходу. HTTP200,
TLS verify0, PHP syntax і git diff --check PASS. Це Chromium responsive
перевірка, НЕ справжній iOS Safari/WebKit або емуляція його browser chrome;
реальний iPhone треба звірити власнику після reload. Temporary viewport reset,
agent test tab closed, user tabs збережені. Proof/results у
.qa/mobile-safari-20261004/{results.json,live-process-390.jpg}.

## 29. Guest Enquiries With Private Photos, 2026-10-04

Scope: stage1 only, approved production deployment after local tests. No client
registration, reviews, verified-review links, slider or bulk photo conversion.
Optional5 JPEG/PNG/WebP photos, <=5MiB each, actual MIME/decoder validation,
<=20MP and <=12000px per input dimension. HEIC unsupported. Body limit26MiB.
PHP limits5M/28M, max_file_uploads6 so >5 is rejected rather than silently
truncated, memory256M. GD WebP and EXIF are installed in Docker.

app/request-photos.php decodes, applies all8 EXIF orientations, strips metadata,
creates WebPquality78/max1600px +320px-wide thumbs. Original filenames discarded,
random40hex.webp names. Directory0700/files0600. Private volume
lily_request_photos mounted /var/lib/fiksitt/request-photos, outside docroot.
request_photos additive table/FK cascade -> contact_requests. Upgrade SQL:
database/migrations/20261004_request_photos.sql. NEVER import seed on upgrade.
Request+metadata transactional; any photo failure cleans staged files and returns
422/503. SMTP failure leaves request/photos saved. Photo count in notification,
no email attachments/public file paths. Owner account/password unchanged.

admin/requests/photo.php requires admin session, GET/HEAD, validated integerID,
fixed managed filenames only, no-store/noindex. Full view/download and thumbs
in enquiry detail; list counts. Admin delete collects names, deletes transaction,
then removes files. Archive/status preserve attachments. HTTP/admin403 at shared
Nginx remains; use owner's SSH tunnel18090. Admin IP allowlist is NOT based on
forwarded client IP. Only contact rate limiting now uses trusted X-Real-IP from
172.19.0.1; untrusted/invalid/spoofed headers cannot change identity.

Form uses multipart/FormData with browser boundary, Blob previews via img-src
blob: ONLY on home. No unsafe-eval. Success resets previews; errors retain data.
QA caught and fixed permanent photo-error node being removed on submit; selector
excludes [data-photo-error]. Public CSSv5/scriptv6, adminCSSv5/scriptv3.
Lucide X icon + license in assets/icons. Public source directory755/files644;
the initial restricted tar parent permissions caused icon403, fixed persistently
on host and rebuilt image. Do not apply private0700 to public icon directories.

Production deployed to188.137.232.136, /opt/lily-montering/app. Backup700:
/opt/lily-montering/backups/request-photos-20261004T091944Z contains source/uploads/
private env, DBdump, old imageID and fiksitt vhost. Old image tag
lily-web:before-request-photos. Migration applied before web replacement;
DB untouched apart from new empty table. New exact /contact.php Nginx location
with client_max_body_size26m and same trusted proxy headers. Other locations,
Tezamed, TLS/cert renewal, DNS, private backend bindings unchanged. nginx -t PASS.
Database hashes for all pre-existing rows before/after deploy and after synthetic
probe cleanup matched (admins, requests, services, categories, gallery, settings).
No Git push/commit. Back up DB + private photo volume TOGETHER for future backups;
pre-feature backup has no customer attachments. Never delete live Docker volumes.
Rollback: restore backed-up source/compose and old image; leave new table/volume
intact, especially after real requests. Do not restore old DB over new enquiries.

QA project fiksitt-request-qa at18083/18027, own DB fiksitt_request_qa.
tests/request-photos.cjs:15 groups PASS including rollback, private storage failure,
SMTP outage, container recreation persistence, spoofing and all8 EXIF orientations.
PHP lint, JS syntax and proxy tests PASS. CUA browser: actual guest upload ->
admin private preview/download, remove/reselect, size/count limits, repeated form
use after success, responsive320/390/844/1440 no overflow. Real iOS Safari not
available; browser test is Chromium. Owner preview18081 migrated/rebuilt; account
preserved. Old localhost8080 has pre-existing DB credential mismatch and is not
the current preview. QA containers stopped after own test records cleaned.

Live HTTPS probe:1-photo +5exact5MiB uploads accepted;6private WebPs+thumbs,
each full43842bytes (fixture-specific), mode0600. Wrong CSRF403,6files/>5MiB422,
27MiB body413 at Nginx, public admin403, .env403, old8080 closed. All2synthetic
requests/files removed; existing row hashes unchanged. First SSH-based final
check timed out; connection recovered, verified all files and cleanup in a single
guarded CLI pass. Live email_sent=[0,0]: real SMTP is STILL NOT ACTIVATED; local
Mailpit notification tests passed, but no Gmail inbox delivery claimed.
Evidence: .qa/request-photos/results.json, production-final.json, live-form.jpg,
admin-mobile-photo.jpg. Local credentials only in ignored UI fixture JSON.

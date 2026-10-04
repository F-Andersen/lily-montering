# Заявки та пошта fiksitt

Оновлено 2026-10-04. Реалізація опублікована на VPS за прямим запитом власника.

## Пояснення для власника

### 1. З чого складається система

Це чотири окремі частини:

| Частина | Що робить | Поточний стан |
| --- | --- | --- |
| Сайт і форма | Приймають запит клієнта | Працюють на VPS |
| База та адмінпанель | Зберігають заявку і дозволяють її обробити | Працюють |
| SMTP-відправник | Передає сповіщення поштовому провайдеру | Код готовий, авторизація ще не працює |
| Скринька адміністратора | Отримує сповіщення | Запланована masxpros@gmail.com |

Домен є адресою, а не сервером і не поштовою скринькою. Купівля домену
сама по собі не створює SMTP, HTTPS чи пошту на домені. Провайдер може
продавати це разом, але підключення кожної частини потрібно перевірити окремо.

### 2. Що відбувається після натискання кнопки форми

```text
Клієнт заповнює форму
        |
        v
PHP перевіряє поля, згоду, CSRF і антиспам
        |
        v
MariaDB зберігає заявку та присвоює їй номер
        |
        v
PHPMailer формує лист для адміністратора
        |
        v
SMTP з'єднання із поштовим провайдером через TLS
        |
        v
Авторизація SMTP і передача листа
        |
        v
Провайдер пересилає лист до скриньки адміністратора
```

База є основним місцем зберігання. Лист є сповіщенням, а не єдиною копією
заявки. Якщо SMTP недоступний, збережена заявка залишається в адмінпанелі.
Клієнт бачить повідомлення про реєстрацію, а не неправдиве повідомлення,
що лист доставлено. Якщо не працюють і база, і пошта, форма повідомляє про
помилку, щоб клієнт міг повторити спробу.

Зараз немає автоматичного листа-підтвердження клієнту: адреса клієнта
необов'язкова і використовується тільки для Reply-To. Фото форма не приймає.
Також немає автоматичної фонової черги: невідправлені заявки потрібно
переглядати в адмінці та надсилати вручну після відновлення SMTP.

### 3. Що саме робить SMTP

SMTP означає Simple Mail Transfer Protocol: протокол передачі листів.
Наш PHP-застосунок є SMTP-клієнтом, а Gmail або інший провайдер є SMTP-сервером.
Окремий власний поштовий сервер на VPS для цього створювати не потрібно.

Для поточного Gmail-підключення сайт під'єднується до smtp.gmail.com:587,
узгоджує STARTTLS, перевіряє сертифікат, авторизує відправника і передає лист.
Після прийняття листа провайдер виконує подальшу доставку. Отримувач може
користуватися іншим сервісом: відправник і отримувач не зобов'язані бути однією
людиною або одним акаунтом. [Параметри Gmail SMTP](https://support.google.com/mail/answer/7104828?hl=en).

TLS для SMTP захищає з'єднання VPS -> поштовий провайдер. HTTPS захищає інше
з'єднання: браузер клієнта -> сайт. Наявність SMTP TLS не робить нинішній
HTTP-сайт безпечним для реальних персональних даних. До приймання реальних
заявок потрібно підключити HTTPS-домен.

### 4. Відправник, отримувач та відповідь клієнту

Майбутній приклад, який ще не є створеною поштою:

```text
From:     fiksitt <notifications@fiksitt.online>
To:       masxpros@gmail.com
Reply-To: адреса клієнта, якщо він її вказав
Subject:  Ny forespørsel #2 fra fiksitt
```

Адресу notifications@fiksitt.online можна використовувати тільки після
підтвердження домену/відправника в обраному поштовому сервісі. Проста зміна
From у коді не створює скриньку та не підтверджує право надсилати з домену.
Для отримання відповідей на цю адресу потрібна скринька або налаштована
переадресація; для надсилання через transactional-провайдера повна скринька
може не вимагатися, але sender/domain verification усе одно потрібна.

Кнопка Reply у листі використовує Reply-To клієнта. Якщо клієнт не вказав
email, зв'язуватися з ним потрібно за телефоном. Адреса клієнта не підставляється
в From, інакше сайт намагався б надсилати лист від чужого домену.

Лист містить номер заявки, ім'я, телефон, email за наявності, послугу, місто,
опис, час і посилання на заявку. Адмінпанель наразі доступна лише через
SSH-тунель: пряме public-посилання в листі не обходить IP-захист або вхід.

### 5. Які параметри потрібно налаштувати

| Параметр | Значення |
| --- | --- |
| SMTP_HOST | Сервер відправки, який надає поштовий провайдер |
| SMTP_PORT | Порт провайдера; часто 587 для STARTTLS або 465 для implicit TLS |
| SMTP_ENCRYPTION | tls для STARTTLS або ssl для implicit TLS, згідно з провайдером |
| SMTP_USER | SMTP-логін; у Gmail повна адреса акаунта, в іншого провайдера може бути службовий логін |
| SMTP_PASSWORD | Пароль застосунку або SMTP-ключ, не пароль адміністратора сайту |
| MAIL_FROM | Підтверджена адреса відправника |
| MAIL_RECIPIENT | Скринька, яка отримує заявки: masxpros@gmail.com |

Секрети зберігаються у приватній серверній конфігурації, а не в JS/HTML,
браузері чи Git. Адмінпанель показує стан доставки та дозволяє тест/повтор,
але не показує і не редагує SMTP-пароль. Зміна публічного email у налаштуваннях
сайту не змінює автоматично MAIL_RECIPIENT.

### 6. Чому Google показує недоступність паролів застосунків

На наданому скриншоті функція недоступна, але точну причину зі скриншота
визначити неможливо. Google вказує: для app passwords необхідна ввімкнена
2FA; навіть з 2FA опція може бути недоступна для акаунта організації,
Advanced Protection або 2FA лише з security keys.
[Офіційне пояснення Google](https://support.google.com/accounts/answer/185833?hl=en).

Не потрібно вимикати 2FA, Advanced Protection чи інші захисні механізми.
Звичайний пароль Google не є заміною app password для поточної схеми.
Наш одноразовий SMTP AUTH тест отримав 535 5.7.8: TLS працює, але Gmail
відхилив облікові дані. Цей код сам по собі не встановлює причину обмеження
сторінки app passwords.

Альтернативи: Gmail OAuth2 або SMTP іншого провайдера з підтвердженим доменом.
OAuth2 потребує окремого налаштування consent, token storage/refresh і mailer;
у нашому застосунку воно ще не реалізоване. Для доменного запуску пропоную
SMTP поштового хостингу або transactional-провайдера із власним SMTP-ключем.
Конкретний сервіс, вартість і доступні умови потрібно погодити після вибору
реєстратора/поштового плану, нічого не купувати автоматично.

### 7. Що налаштовується після купівлі домену

| DNS/налаштування | Призначення |
| --- | --- |
| A для кореня домену | Направляє сайт на IPv4 VPS 188.137.232.136 |
| www | Окремий запис і перенаправлення на обрану основну адресу |
| HTTPS | Сертифікат, reverse proxy/порти 80 і 443, перенаправлення HTTP -> HTTPS |
| MX | Направляє вхідну доменну пошту до поштового сервісу; сам не активує вихідний SMTP |
| SPF | Дозволяє визначеним поштовим серверам надсилати від домену |
| DKIM | Публікує ключ перевірки криптографічного підпису листів |
| DMARC | Задає політику перевірки узгодженості домену From із SPF/DKIM та обробки невдач |
| Domain verification | Підтверджує власність домену в поштовому сервісі |

SPF/DKIM/verification записи беремо від обраного поштового провайдера,
а не вигадуємо. Не створювати кілька SPF-записів для одного hostname.
DMARC вводити обережно після перевірки всіх легітимних відправників.
Автентифікація покращує довіру, але не гарантує inbox.
[Рекомендації Gmail для відправників](https://support.google.com/a/answer/81126?hl=en).

Для сайту і пошти не обов'язково використовувати одного провайдера. MX можна
налаштувати для скриньок одного сервісу, а SMTP для повідомлень сайту другого,
якщо правильно підтвердити домен і узгодити DNS-автентифікацію.

### 8. Що передати після купівлі домену

1. Точну назву купленого домену, не лише назву бренду.
2. Назву реєстратора та сервісу, де керуються DNS (якщо це інший сервіс).
3. Чи є поштовий тариф/SMTP у комплекті, і посилання на його документацію.
4. Чи залишаємо отримувача masxpros@gmail.com, і бажану адресу відправника.
5. Чи є вже DNS-записи, чинна пошта або сайт, які не можна пошкодити.

Паролі від реєстратора, пошти, 2FA чи приватні API-ключі в чат не надсилати.
Для секретів погодимо приватний файл або обмежений доступ; якщо потрібен
вхід у панель/2FA, його виконує власник. Сам факт купівлі домену не є дозволом
перезаписувати DNS, купувати поштовий тариф чи відкривати адмінку публічно.

### 9. Коли вважати запуск пошти завершеним

Потрібні всі перевірки: підтверджений sender/domain, SMTP TLS, успішна
авторизація, тестовий лист із панелі, тестова заявка з HTTPS-сайту, її запис
у базі, фактичний лист у inbox/spam адміністратора, правильні From/To/Reply-To
і перевірка SPF/DKIM/DMARC у заголовках доменного листа.
Успішна HTTP-відповідь форми чи SMTP acceptance окремо не доводять inbox delivery.

Не надсилати старі заявки масово. Спочатку звірити листи за ID; тестова
заявка #2 є зручною для контрольного повтору після активації SMTP.

## Стан

- Отримувач для реального запуску: `masxpros@gmail.com`.
- Зараз локальні Docker-середовища використовують тільки Mailpit; це НЕ Gmail.
- Серверні Gmail target-параметри задані, але SMTP_PASSWORD на VPS порожній.
- Власник надав у приватному файлі інший Gmail акаунт. Одноразовий SMTP AUTH
  preflight з VPS: TLS успішний, Google відмовив `535 5.7.8`.
  Ці дані НЕ записано в серверний .env; реальну доставку не активовано.
- На сервері без робочого SMTP заявки залишаються в базі; опублікована реалізація додає
  ручне повторне надсилання та тестове повідомлення в адмінці.

## Попередній Gmail-варіант

Поки fiksitt.online не придбаний, не підставляти вигаданий доменний From.
Використати існуючий Gmail: `smtp.gmail.com`, порт `587`, STARTTLS (`tls`),
отримувач `masxpros@gmail.com`; From повинен відповідати акаунту SMTP_USER.
Для альтернативного Gmail, наданого власником, спершу потрібен валідний app password.
Для невеликого початкового обсягу це практичний варіант, не масова розсилка.
Пізніше замінити на SMTP підтвердженого домену із SPF/DKIM/DMARC;
отримувач може залишитися Gmail, адреса From має належати провайдеру/домену.

Google вимагає двоетапну перевірку для паролів застосунків. Функція може бути
недоступною для окремих типів акаунтів; тоді потрібні OAuth2 або інший SMTP.
Звичайний пароль Gmail не використовувати. Створення пароля виконує власник.
Після скриншота про недоступність app passwords не вважати цей спосіб
гарантовано доступним. Для нового домену розглядаємо доменний SMTP;
провайдер і тариф ще не обрані, OAuth2 ще не реалізоване.

Офіційні джерела:
- https://support.google.com/mail/answer/7126229?hl=en
- https://support.google.com/accounts/answer/185833?hl=en
- https://github.com/PHPMailer/PHPMailer/tree/v7.1.1

## Приватне налаштування

Шаблон поза репозиторієм, ACL тільки для власника Windows:
`C:/Users/Anderson/.codex/private/fiksitt-mail/gmail.smtp.env`.
Файл заповнений власником; останні дані відхилені Gmail AUTH і не активовані.
Власник заповнює його локально, не в чаті. Не друкувати файл у логах,
не класти в Git, public_html, архів із вихідним кодом або скриншот.

Звичайний пароль входу Google замінити паролем застосунку, створеним власником
для цього Gmail після ввімкнення 2FA. SMTP_USER = адреса цього акаунта,
MAIL_FROM = та сама адреса, MAIL_RECIPIENT = masxpros@gmail.com.
Ізольований preflight не зберігає credential до успішного SMTP AUTH.
Повторювати failed AUTH лише після виправлення даних, не циклічно.

## Як працює SMTP

Форма -> PHP validation -> запис MariaDB -> TLS connection до smtp.gmail.com:587 ->
SMTP authentication -> передача листа Gmail -> доставка отримувачу.
Локальний Mailpit заміняє останні кроки тестовою локальною скринькою і не
надсилає листи в реальний Gmail. SMTP_USER/PASSWORD авторизують відправника;
MAIL_RECIPIENT визначає отримувача. From є адресою відправника, Reply-To є
необов'язковою адресою клієнта для відповіді. Прийнятий Gmail лист може потрапити
у spam або відхилитися пізніше; inbox підтверджує власник, а не HTTP 200 форми.

Онлайн тестова заявка #2 зареєстрована, email_sent=0. Після активації SMTP
надіслати її кнопкою адміністратора й перевірити inbox/spam за номером #2.

Після дозволу на публікацію:
1. Зробити резервну копію production DB, uploads, образу й конфігурації.
2. Перенести лише поштові змінні з приватного профілю в server-only `.env`
   (права 600, поза web root) або `config.php`. Не замінювати весь production
   `.env` локальним файлом: DB/IP/setup та інші значення мають зберегтися.
3. Зібрати оновлений production web image із `app/vendor/phpmailer/` і
   перезапустити web з чинним production Compose. Локальний Compose навмисно
   фіксує Mailpit і не підходить для реальної пошти.
4. Перевірити HTTPS, вихідний TCP 587, PHP OpenSSL, довірені CA сертифікати.
   Не вимикати перевірку TLS-сертифікатів і не додавати `unsafe-eval`.
5. У налаштуваннях адмінки перевірити отримувача/відправника/SMTP, надіслати
   тестовий лист, вручну звірити Gmail inbox і spam.
6. Створити одну позначену тестову заявку з сайту, перевірити DB, номер,
   UTF-8, From/To/Reply-To, отриманий лист і посилання на адмінку.
7. Заявки зі статусом ненадіслано можна надіслати з їхньої сторінки;
   не робити масове повторне надсилання старих заявок без перевірки.

## Контракт доставки

`contact.php` спершу зберігає заявку в MariaDB. `deliver_request()` серіалізує
початкову доставку й повтори через MySQL GET_LOCK на ID заявки, читає
email_sent, не надсилає вже прийняті листи або spam. Успіх позначає email_sent=1.
Лист містить номер заявки, контакти, послугу, місце, опис, час UTC і посилання
на адмінку з довіреного APP_URL; доступ до сторінки вимагає входу.
Reply-To = валідна адреса клієнта; клієнт ніколи не підставляється в From.

Якщо пошта недоступна, збережена заявка залишається доступною адміну.
Якщо DB недоступна, чинний fallback пробує email-only; якщо обидва канали
недоступні, форма повертає 503 і не показує помилкового успіху.

SMTP acceptance не гарантує появу листа в inbox. `email_sent` означає прийняття
поштовим сервером/локальною чергою, а не прочитання або остаточну доставку.
Без webhook/bounce tracking стан доставки до скриньки невідомий.
У разі обриву після SMTP DATA або збою DB після прийняття листа результат
може бути неоднозначним: перед повтором звірити пошту за номером заявки.
Гарантії exactly-once для зовнішнього SMTP немає. Автоматичної cron-черги немає.

Транспорт: незмінені runtime-файли PHPMailer 7.1.1 з LGPL LICENSE.
SMTP login вимагає шифрування; `none` дозволено лише для local без auth.
Діагностичні логи не містять описів заявок, адрес чи паролів.
Тестовий лист і повтори вимагають admin session + POST + CSRF, мають rate limit
3/10 хвилин та 10/10 хвилин відповідно; отримувач не задається через форму.

## Перевірка

`node tests/mail-notifications.cjs` запускається лише в ізольованому
fiksitt-seo-qa (18082 / Mailpit 18026), перевіряє точний local env/DB/SMTP.
Створює та прибирає власні тестові записи. Не запускається паралельно з QA,
що зупиняє DB/Mailpit. Ручні сценарії й обмеження: `MANUAL_QA.md`.

## Production SMTP: 2026-10-04

Read-only audit confirmed `smtp.gmail.com:587`, TLS, configured SMTP username,
but **no SMTP password**. Recipient is `masxpros@gmail.com`. Real notification
delivery is not enabled. Local Mailpit tests do not deliver to Gmail.

Recommended next connection: a transactional SMTP service such as Brevo.
Buying the domain alone does not create a mailbox or an SMTP account.

1. The owner creates a Brevo account and enables transactional sending.
2. Add `fiksitt.online` in Senders / Domains. Copy the exact verification,
   DKIM and DMARC records shown by the provider into the current DNS zone.
   Do not replace NS, website A/CNAME records, or Tezamed records. Do not
   invent SPF records or add a second SPF policy; follow the provider's
   instructions and merge existing policies when needed. MX is required
   for receiving mail in a real domain mailbox, not simply for SMTP relay.
3. Verify a sender such as `notifications@fiksitt.online` in the provider.
4. Generate an **SMTP key**, not an API key, and obtain the SMTP login.
   Fill the private local file
   `C:/Users/Anderson/.codex/private/fiksitt-mail/brevo.smtp.env`.
   This file is outside the repository and restricted to the owner/SYSTEM.
   Never send the key in chat, screenshots or Git.
5. After confirmation, transfer the credentials over SSH into the existing
   protected production environment. Use `smtp-relay.brevo.com`, port 587,
   explicit STARTTLS, the provider's SMTP login and SMTP key. Recreate only
   the Fiksitt web service. Do not reset administrator credentials or DB.
6. Send one labeled test to the administrator, check SMTP acceptance,
   provider logs and the recipient's inbox/spam folder. Then test one form
   submission and clean only its own test fixture. Never bulk-resend old
   requests without reviewing which emails were already accepted.

Current logic: customer submits form -> validation/CSRF/rate limits ->
request and private photos saved -> SMTP notification attempted -> saved
request remains available if SMTP fails. Email contains the description,
contact details, photo count and an authenticated admin link; private
photos are not published or attached to outbound emails. Customer email
is Reply-To, never an unverified From address. Review invitations also
use SMTP, but are sent only by an authenticated administrator for completed
jobs with a customer email address.

Provider documentation:
- https://help.brevo.com/hc/en-us/articles/7924908994450-Send-transactional-emails-using-Brevo-SMTP
- https://help.brevo.com/hc/en-us/articles/7959631848850-Create-and-manage-your-SMTP-keys

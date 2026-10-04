# fiksitt: SEO та запуск

Оновлено 2026-10-05. Домен fiksitt.online придбаний, HTTPS працює на VPS
188.137.232.136. Власник прямо дозволив Git push і публікацію цього SEO-релізу.
Перед релізом сервер мав seo_indexable=0 і порожній public_domain: індексація
була заблокована. Після backup і перевірки встановити public_domain=https://fiksitt.online
та seo_indexable=1; перевірити robots.txt і X-Robots-Tag зовнішнім HTTP-тестом.

Результат релізу: код `1e83d50` опублікований на GitHub main і VPS.
Індексацію ввімкнено; зовнішній tests/seo-public-http.cjs пройшов для 8 URL.
Попередні дані й SMTP збережено. Backup-пакети та старий образ: CODEX_HANDOFF.md.
Search Console, Business Profile і підтвердження реальних бізнес-контактів
залишаються окремими завданнями; польові Core Web Vitals ще не виміряні.

## Що реалізовано

- Норвезькі title/description для головної, каталогу та кожної послуги.
- Редагування метаданих головної й послуг, лічильники та preview в адмінці.
- Абсолютні canonical без рекламних параметрів; фільтри каталогу посилаються
  на основний каталог, а не створюють індексовані сторінки для кожного фільтра.
- 301 з /index.php, /tjenester.php та варіантів із завершальним / на основні
  маршрути при pretty_urls=true; при false прямі PHP-посилання лишаються.
- Невідомі/неактивні послуги й невідомі категорії повертають 404.
- Сторінки помилок не мають canonical на головну.
- LocalBusiness, WebSite, WebPage, Service, BreadcrumbList та ItemList із
  фактичними даними. OfferCatalog містить чотири послуги замовника без вигаданих цін.
- Власні Open Graph/Twitter-зображення для послуг із реальними розмірами/alt.
- Sitemap містить лише активні послуги активних категорій та основні сторінки.
  lastmod послуг походить із updated_at; час поточного запиту не підставляється.
- /sitemap.php перенаправляє 301 на /sitemap.xml. Sitemap не є пунктом меню.
  Пошукові роботи знаходять карту через robots.txt; каталог має звичайне
  HTML-посилання Alle tjenester у футері, усі послуги доступні через каталог.
- Фільтр каталогу noindex,follow з canonical на повний каталог. Адмінський
  нестандартний шлях, app/database/tests/tools закриті в robots.txt і сервером.
- Meta robots + X-Robots-Tag; адмінка та результати форми завжди noindex.
- Локальне середовище й неввімкнена індексація мають robots Disallow: /.
- Responsive preload головного фото, WebP/srcset та зарезервовані розміри.
- Нові uploads мають 320/900px WebP-похідні; таблиці адмінки вибирають thumbnail.
- Розділ /adfiksittmin/seo/: конфігурація запуску та фактичні метадані послуг.

Гарантій позицій у Google або появи rich results немає. Адресу, ціни, години,
рейтинг/відгуки, сертифікації та населені пункти не вигадували. Без підтвердженої
адреси LocalBusiness не задовольняє всі вимоги Google до розширеного результату.
Логотип і favicon надані власником та підключені. Демонстраційний відгук не
включається в JSON-LD; aggregateRating для власного бізнесу не додається.

## Дані від власника

- Домен уже підтверджений; DNS і HTTPS налаштовані.
- Реальна назва/організаційний номер, публічні телефон та email.
- Фактична зона виїзду, перелік робіт, реальні фото й право їх публікувати.
- Адреса/години роботи лише якщо їх можна публікувати.
- Доступ до Search Console та Business Profile власника, якщо профіль існує.

## Порядок запуску після дозволу

1. Зберегти резервну копію коду/БД та поточний Docker image. Не скидати БД,
   не переімпортувати seed.sql і не перейменовувати Docker volumes.
2. Підключити DNS і перевірити HTTPS для fiksitt.online, узгодити www/non-www.
   Не вмикати HTTPS/host redirect до справного сертифіката. При reverse proxy
   спершу правильно налаштувати довірене визначення HTTPS, не довіряти довільним
   клієнтським X-Forwarded-* заголовкам. Перевірити Secure session cookie.
3. Встановити production APP_URL і public_domain на підтверджену HTTPS-адресу.
   Змінити company_name існуючої БД на fiksitt, зберігши заявки й контент.
4. Перевірити публічні сторінки, форму, robots, sitemap, canonical, картинки,
   недоступність приватних файлів та захист адмінки. Підключити SMTP окремо.
5. У /adfiksittmin/settings/ увімкнути індексацію лише після цих перевірок.
   Потрібні одночасно APP_ENV=production, seo_indexable=1 та HTTPS-domain
   без шляху/порту. Локальна копія завжди noindex навіть із прапорцем.
6. Перевірити robots.txt: Allow: / та правильна адреса Sitemap. Перевірити
   meta robots/X-Robots-Tag на публічних сторінках, sitemap URL мають давати 200.
7. Підтвердити володіння доменом у Search Console й подати /sitemap.xml;
   перевірити URL Inspection та Rich Results Test. Це зовнішні дії, ще не виконані.
8. Заміряти PageSpeed/Core Web Vitals на production/mobile, повторити після
   накопичення польових даних. Локальні тести не прогнозують реальний INP.
9. Якщо власник обслуговує клієнтів на виїзді, заповнювати Business Profile
   фактичними даними згідно з правилами Google, без вигаданої публічної адреси.

Не змінювати slug вже опублікованої послуги без плану 301 зі старого URL.
Історія перейменувань slug не зберігається автоматично в цьому застосунку.

## Перевірки локально

Основна копія: http://127.0.0.1:18081/. Ізольований QA використовує 18082,
Docker project fiksitt-seo-qa; production SSH-тунель 18090 НЕ використовувати
для автоматичних тестів із записом. tests/seo-admin.cjs обмежений QA-project
і перевіряє APP_ENV=local та відповідність APP_URL.

SEO-прапорці зберігаються в існуючій таблиці site_settings; SQL-міграція не
потрібна. На старій БД відсутній seo_indexable означає вимкнену індексацію.
Після погодженого оновлення це потрібно явно перевірити, не забути noindex.

Основні джерела:

Read-only тест: `node tests/seo-public-http.cjs` (локальна копія), або
`QA_URL=https://fiksitt.online node tests/seo-public-http.cjs` (production).
Потрібен xml-js із workspace Node dependencies. Перевіряє 8 sitemap URL,
унікальні метадані, canonical/robots/schema/CSP, WebP, 404 і приватні сторінки.
Він нічого не записує та не надсилає листи. Польові Core Web Vitals і позиції
залежать від реальних відвідувань, їх не можна підтвердити цим smoke-тестом.

- https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls
- https://developers.google.com/search/docs/crawling-indexing/block-indexing
- https://developers.google.com/search/docs/appearance/structured-data/breadcrumb
- https://developers.google.com/search/docs/appearance/structured-data/local-business
- https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap
- https://web.dev/articles/browser-level-image-lazy-loading
- https://web.dev/articles/optimize-lcp

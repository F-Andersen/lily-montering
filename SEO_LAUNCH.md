# fiksitt: SEO та запуск

Стан на 2026-10-04: зміни тільки локальні. fiksitt.online ще не придбаний.
Не виконувати push/deploy до прямого дозволу власника.

## Що реалізовано

- Норвезькі title/description для головної, каталогу та кожної послуги.
- Редагування метаданих головної й послуг, лічильники та preview в адмінці.
- Абсолютні canonical без рекламних параметрів; фільтри каталогу посилаються
  на основний каталог, а не створюють індексовані сторінки для кожного фільтра.
- 301 з /index.php, /tjenester.php та варіантів із завершальним / на основні
  маршрути при pretty_urls=true; при false прямі PHP-посилання лишаються.
- Невідомі/неактивні послуги й невідомі категорії повертають 404.
- Сторінки помилок не мають canonical на головну.
- LocalBusiness, Service, BreadcrumbList та ItemList із фактичними даними.
- Власні Open Graph/Twitter-зображення для послуг із реальними розмірами/alt.
- Sitemap містить лише активні послуги активних категорій та основні сторінки.
  lastmod послуг походить із updated_at; час поточного запиту не підставляється.
- Meta robots + X-Robots-Tag; адмінка та результати форми завжди noindex.
- Локальне середовище й неввімкнена індексація мають robots Disallow: /.
- Responsive preload головного фото, WebP/srcset та зарезервовані розміри.
- Нові uploads мають 320/900px WebP-похідні; таблиці адмінки вибирають thumbnail.
- Розділ /admin/seo/: конфігурація запуску та фактичні метадані послуг.

Гарантій позицій у Google або появи rich results немає. Адресу, ціни, години,
рейтинг/відгуки, сертифікації та населені пункти не вигадували. Без підтвердженої
адреси LocalBusiness не задовольняє всі вимоги Google до розширеного результату.
Реальний логотип ще потрібен; F-монограма тимчасова.

## Дані від власника

- Підтверджений куплений домен та доступ до його DNS.
- Реальна назва/організаційний номер, публічні телефон та email.
- Фактична зона виїзду, перелік робіт, реальні фото й право їх публікувати.
- Остаточний логотип. Адреса/години роботи лише якщо їх можна публікувати.
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
5. У /admin/settings/ увімкнути індексацію лише після цих перевірок.
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

- https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls
- https://developers.google.com/search/docs/crawling-indexing/block-indexing
- https://developers.google.com/search/docs/appearance/structured-data/breadcrumb
- https://developers.google.com/search/docs/appearance/structured-data/local-business
- https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap
- https://web.dev/articles/browser-level-image-lazy-loading
- https://web.dev/articles/optimize-lcp

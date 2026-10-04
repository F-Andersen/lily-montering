<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function fallback_content(): array
{
    return json_decode(<<<'JSON'
{"services":[{"title":"Kontor, skole og næring","short":"Kontorpulter, skap, reoler, møtebord og inventar for lokaler som skal raskt i bruk.","id":1,"slug":"kontor-og-naering","short_description":"Kontorpulter, skap, reoler, møtebord og inventar for lokaler som skal raskt i bruk.","full_description":"Kontorpulter, skap, reoler, møtebord og inventar for lokaler som skal raskt i bruk.\n\nVi hjelper med kontorpulter, skap, reoler og møtebord. Antall møbler, adkomst og tidspunkt avklares før oppstart, slik at lokalene kan tas i bruk med minst mulig avbrudd.","image":"","category_id":1,"category_title":"Kontor og næring","category_slug":"kontor-og-naering","featured":1,"active":1,"sort_order":0,"price_text":"","duration_text":"","seo_title":"","seo_description":""},{"title":"Garderober og skyvedører","short":"Skap, skinner, fronter, kurver og innredning monteres med fokus på rette linjer og stabil bruk.","id":2,"slug":"garderober-og-skyvedorer","short_description":"Skap, skinner, fronter, kurver og innredning monteres med fokus på rette linjer og stabil bruk.","full_description":"Skap, skinner, fronter, kurver og innredning monteres med fokus på rette linjer og stabil bruk.\n\nSkap, skinner og innredning monteres og justeres nøye. Ved skråtak eller trange rom avklarer vi løsningen ut fra mål og bilder før arbeidet starter.","image":"assets/images/garderobe-under-skratt-tak.jpg","category_id":2,"category_title":"Garderober og skyvedører","category_slug":"garderober-og-skyvedorer","featured":1,"active":1,"sort_order":1,"price_text":"","duration_text":"","seo_title":"","seo_description":""},{"title":"Kjøkken og krevende skap","short":"Hjelp med hjørneskap, uttrekk, skyvesystemer og løsninger under skråtak eller mansard.","id":3,"slug":"kjokkenmontering","short_description":"Hjelp med hjørneskap, uttrekk, skyvesystemer og løsninger under skråtak eller mansard.","full_description":"Hjelp med hjørneskap, uttrekk, skyvesystemer og løsninger under skråtak eller mansard.\n\nVi hjelper med kjøkkenmoduler, fronter og uttrekksløsninger. Omfang og eventuell tilpasning avklares på forhånd. Elektriske og rørtekniske arbeider avtales med riktig fagperson.","image":"","category_id":3,"category_title":"Kjøkkenmontering","category_slug":"kjokkenmontering","featured":1,"active":1,"sort_order":2,"price_text":"","duration_text":"","seo_title":"","seo_description":""},{"title":"Møbler til private hjem","short":"Senger, kommoder, spisebord, skap, reoler og flatpakkede møbler monteres stabilt.","id":4,"slug":"mobelmontering-hjemme","short_description":"Senger, kommoder, spisebord, skap, reoler og flatpakkede møbler monteres stabilt.","full_description":"Senger, kommoder, spisebord, skap, reoler og flatpakkede møbler monteres stabilt.\n\nSenger, kommoder, spisebord og flatpakkede møbler monteres stabilt. Send produktnavn, antall møbler og område, så avklarer vi hva oppdraget innebærer.","image":"","category_id":4,"category_title":"Møbelmontering hjemme","category_slug":"mobelmontering-hjemme","featured":1,"active":1,"sort_order":3,"price_text":"","duration_text":"","seo_title":"","seo_description":""},{"title":"Veggmontering","short":"Bilder, speil, hyller, TV-braketter, gardinstenger og andre detaljer henges opp pent.","id":5,"slug":"veggmontering","short_description":"Bilder, speil, hyller, TV-braketter, gardinstenger og andre detaljer henges opp pent.","full_description":"Bilder, speil, hyller, TV-braketter, gardinstenger og andre detaljer henges opp pent.\n\nVi monterer hyller, speil, gardinstenger og TV-fester. Veggtype, underlag og egnet innfesting vurderes før montering.","image":"assets/images/tv-og-oppbevaringsvegg.jpg","category_id":5,"category_title":"Veggmontering","category_slug":"veggmontering","featured":1,"active":1,"sort_order":4,"price_text":"","duration_text":"","seo_title":"","seo_description":""},{"title":"Små handyman-jobber","short":"Små fikser hjemme eller på kontoret tas seriøst. Ingen jobb er for liten når den må gjøres ordentlig.","id":6,"slug":"handyman-tjenester","short_description":"Små fikser hjemme eller på kontoret tas seriøst. Ingen jobb er for liten når den må gjøres ordentlig.","full_description":"Små fikser hjemme eller på kontoret tas seriøst. Ingen jobb er for liten når den må gjøres ordentlig.\n\nSmå praktiske oppdrag hjemme eller på kontoret avklares ut fra behov og bilder. Vi vurderer om arbeidet passer vårt tjenestetilbud før en avtale inngås.","image":"","category_id":6,"category_title":"Handyman-tjenester","category_slug":"handyman-tjenester","featured":1,"active":1,"sort_order":5,"price_text":"","duration_text":"","seo_title":"","seo_description":""}],"gallery":[{"image":"assets/images/garderobe-under-skratt-tak.jpg","alt":"Sort garderobe montert under skråtak med presis tilpasning mot lav takhøyde","caption":"Garderobe under skråtak","sort":0,"id":1,"alt_text":"Sort garderobe montert under skråtak med presis tilpasning mot lav takhøyde","sort_order":0,"active":1,"featured":1},{"image":"assets/images/apent-garderobesystem-med-kurver.jpg","alt":"Åpent hvitt garderobesystem med skuffer, kurver og hengestenger","caption":"Åpent garderobesystem","sort":1,"id":2,"alt_text":"Åpent hvitt garderobesystem med skuffer, kurver og hengestenger","sort_order":1,"active":1,"featured":1},{"image":"assets/images/tv-og-oppbevaringsvegg.jpg","alt":"Hvit oppbevaringsvegg bygget rundt TV med hyller og skyvedørsskinne","caption":"TV- og oppbevaringsvegg","sort":2,"id":3,"alt_text":"Hvit oppbevaringsvegg bygget rundt TV med hyller og skyvedørsskinne","sort_order":2,"active":1,"featured":1},{"image":"assets/images/skyvedorsgarderobe-ved-trapp.jpg","alt":"Skyvedørsgarderobe med speil montert ved trapp og skrå vegg","caption":"Skyvedører ved trapp","sort":3,"id":4,"alt_text":"Skyvedørsgarderobe med speil montert ved trapp og skrå vegg","sort_order":3,"active":1,"featured":1},{"image":"assets/images/garderobe-med-varm-trebelysning.jpg","alt":"Garderobe i mørkt tre med skuffer og hyller","caption":"Garderobe i mørkt tre","sort":4,"id":5,"alt_text":"Garderobe i mørkt tre med skuffer og hyller","sort_order":4,"active":1,"featured":1},{"image":"assets/images/brun-garderobe-innredning.jpg","alt":"Brun garderobeinnredning med skuffer, hyller og hengestenger","caption":"Innredning med skuffer","sort":5,"id":6,"alt_text":"Brun garderobeinnredning med skuffer, hyller og hengestenger","sort_order":5,"active":1,"featured":1},{"image":"assets/images/montert-garderoberom-sort.jpg","alt":"Sort garderoberom med åpne hyller og skuffer ferdig montert","caption":"Sort garderoberom","sort":6,"id":7,"alt_text":"Sort garderoberom med åpne hyller og skuffer ferdig montert","sort_order":6,"active":1,"featured":1},{"image":"assets/images/lys-tregarderobe-med-speil.jpg","alt":"Lys tregarderobe med høye skapfronter montert på soverom","caption":"Lys tregarderobe","sort":7,"id":8,"alt_text":"Lys tregarderobe med høye skapfronter montert på soverom","sort_order":7,"active":1,"featured":1},{"image":"assets/images/sort-hyllesystem-montert.jpg","alt":"Sort hylle- og skapinnredning ferdig montert på hvit vegg","caption":"Hylle- og skapinnredning","sort":8,"id":9,"alt_text":"Sort hylle- og skapinnredning ferdig montert på hvit vegg","sort_order":8,"active":1,"featured":1}],"categories":[{"id":1,"title":"Kontor og næring","slug":"kontor-og-naering","active":1,"sort_order":0},{"id":2,"title":"Garderober og skyvedører","slug":"garderober-og-skyvedorer","active":1,"sort_order":1},{"id":3,"title":"Kjøkkenmontering","slug":"kjokkenmontering","active":1,"sort_order":2},{"id":4,"title":"Møbelmontering hjemme","slug":"mobelmontering-hjemme","active":1,"sort_order":3},{"id":5,"title":"Veggmontering","slug":"veggmontering","active":1,"sort_order":4},{"id":6,"title":"Handyman-tjenester","slug":"handyman-tjenester","active":1,"sort_order":5}]}
JSON, true, 512, JSON_THROW_ON_ERROR);
}

function public_services(bool &$available): array
{
    static $cached, $state;
    if ($cached !== null) { $available = $state; return $cached; }
    try {
        $rows = query('SELECT s.*, c.title AS category_title, c.slug AS category_slug FROM services s JOIN service_categories c ON s.category_id = c.id WHERE s.active = 1 AND c.active = 1 ORDER BY c.sort_order,s.sort_order,s.id')->fetchAll();
        $available = true;
        $state = true;
        return $cached = $rows;
    } catch (Throwable $error) { safe_log('public services unavailable', $error); $available = false; $state = false; return $cached = fallback_content()['services']; }
}

function public_gallery(): array
{
    try { return query('SELECT * FROM gallery_items WHERE active = 1 AND featured = 1 ORDER BY sort_order,id LIMIT 60')->fetchAll(); }
    catch (Throwable $error) {
        safe_log('public gallery unavailable', $error);
        $selection = json_decode(@file_get_contents(dirname(__DIR__) . '/assets/images/gallery-selection.json') ?: '[]', true);
        return [...fallback_content()['gallery'], ...(is_array($selection) ? $selection : [])];
    }
}

function home_service_offers(): array
{
    return [
        'Montering av ditt nye garderobeskap eller andre møbler',
        'Levering og montering av ditt nye garderobeskap eller møbler',
        'Levering og montering av ditt nye garderobeskap eller møbler, samt bortkjøring av emballasje og avfall',
        'Handymantjenester og praktisk hjelp i hjemmet',
        'Send gjerne et bilde eller en lenke til møblene for et uforpliktende pristilbud',
    ];
}

function enquiry_service_options(): array
{
    return array_combine(array_slice(home_service_offers(), 0, 4), [
        'Montering av møbler', 'Levering og montering',
        'Levering, montering og bortkjøring', 'Handymantjenester',
    ]);
}

function contact_services(): array
{
    $available = true;
    $titles = array_column(public_services($available), 'title');
    // Keep legacy service-page enquiries valid while exposing the new offers.
    return [...array_values(array_unique([...array_keys(enquiry_service_options()), ...$titles])), 'Annet handyman-oppdrag'];
}

function service_media(array $service): array
{
    $defaults = [
        'kontor-og-naering' => ['lys-trehylle-montert.jpg', 'Montert åpen trehylle med flere hyller'],
        'garderober-og-skyvedorer' => ['garderobe-under-skratt-tak.jpg', 'Garderobeinnredning tilpasset skråtak'],
        'kjokkenmontering' => ['veggskap-i-tre.jpg', 'Tilpasset skapinnredning i tre med hyller og høye skap'],
        'mobelmontering-hjemme' => ['kommoder-i-lyst-tre.jpg', 'To monterte kommoder i lyst tre med skuffer'],
        'veggmontering' => ['tv-og-oppbevaringsvegg.jpg', 'Montert oppbevaringsvegg med hyller rundt TV'],
        'handyman-tjenester' => ['lavt-skap-under-skratt-tak.jpg', 'Lavt hvitt skap montert under skråtak'],
    ];
    $default = $defaults[$service['category_slug'] ?? ''] ?? $defaults[$service['slug'] ?? ''] ?? ['montert-garderoberom-sort.jpg', 'Ferdig montert garderobe med hyller og skuffer'];
    $candidates = [
        [$service['image'] ?? '', $service['title']],
        ['assets/images/' . $default[0], $default[1]],
        ['assets/images/montert-garderoberom-sort.jpg', 'Ferdig montert garderobe med hyller og skuffer'],
    ];
    foreach ($candidates as [$path, $alt]) {
        $path = image_path($path);
        if ($path !== '' && is_file(dirname(__DIR__) . '/' . $path) && @getimagesize(dirname(__DIR__) . '/' . $path) !== false) return [$path, $alt];
    }
    return ['', ''];
}

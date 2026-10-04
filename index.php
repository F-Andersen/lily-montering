<?php
declare(strict_types=1);
require_once __DIR__ . '/app/public-layout.php';
require_once __DIR__ . '/app/phone.php';
require_once __DIR__ . '/app/reviews.php';
require_once __DIR__ . '/app/gallery.php';
start_session();
public_alias_redirect($_GET ? '?' . http_build_query($_GET) : '', ['index.php', 'index.html']);
$s = settings();
$available = true;
$services = public_services($available);
$offers = home_service_offers();
$gallery = public_gallery();
$reviewsAvailable = true;
$reviews = public_reviews($reviewsAvailable);
$selectedService = is_string($_GET['service'] ?? null) ? $_GET['service'] : '';
$metadata = home_metadata();
public_head($metadata['title'], $metadata['description'], '', home_schema($metadata), true, allowIndex: $available);
public_header();
?>
<main id="main">
      <section class="hero" id="top" aria-labelledby="hero-title">
        <picture>
          <source
            type="image/webp"
            srcset="<?= e(url('assets/images/og-montering-900.webp')) ?> 900w, <?= e(url('assets/images/og-montering.webp')) ?> 1200w"
            sizes="100vw"
          >
          <img
            class="hero-image"
            src="<?= e(url('assets/images/og-montering.webp')) ?>"
            width="1200"
            height="630"
            alt="Montert skyvedørsgarderobe med speil og sort ramme på soverom"
            fetchpriority="high"
            decoding="async"
          >
        </picture>
        <div class="hero-overlay" aria-hidden="true"></div>
        <div class="hero-content">
          <p class="eyebrow">Din lokale handyman i <?= e($s['service_region']) ?></p>
          <h1 id="hero-title"><?= e($s['company_name']) ?></h1>
          <p class="hero-slogan">Har du kjøpt nytt skap? Jeg hjelper deg med montering!</p>
          <p class="hero-copy">
            Nøyaktig og ryddig montering av garderober og møbler,
            med praktisk hjelp for både private og bedrifter.
          </p>
          <div class="hero-actions" aria-label="Hovedhandlinger">
            <a class="button button-primary" href="#contact-form" data-order-open>Bestill nå</a>
            <a class="button button-secondary" href="#arbeid">Se utført arbeid</a>
          </div>
          <p class="hero-note">Nøyaktig montert. Ryddig levert.</p>
        </div>
      </section>

      <section class="trust-strip" aria-label="Trygghet før bestilling">
        <div>
          <span class="benefit-number" aria-hidden="true">01</span>
          <strong>Tydelig avklaring</strong>
          <span>Vi avklarer omfang og detaljer før arbeidet starter.</span>
        </div>
        <div>
          <span class="benefit-number" aria-hidden="true">02</span>
          <strong>Nøyaktig montering</strong>
          <span>Skap, fronter, skinner og beslag justeres nøye.</span>
        </div>
        <div>
          <span class="benefit-number" aria-hidden="true">03</span>
          <strong>Ryddig utførelse</strong>
          <span>Arbeidsområdet holdes ryddig gjennom hele jobben.</span>
        </div>
        <div>
          <span class="benefit-number" aria-hidden="true">04</span>
          <strong>Holder avtalt tid</strong>
          <span>Et praktisk tidspunkt for hjemmet eller arbeidsplassen.</span>
        </div>
      </section>

      <?php gallery_section($gallery); ?>

      <section class="section client-services" id="tjenester" aria-labelledby="services-title">
        <div class="section-heading">
          <p class="eyebrow">Tjenester</p>
          <h2 id="services-title">Fra nye møbler<br>til ferdig montert.</h2>
          <p>Velg hjelpen du trenger. Omfang og pris avklares før oppdraget starter.</p>
        </div>
        <ol class="service-offers">
          <?php foreach ($offers as $index => $offer): ?>
          <li class="service-offer">
            <span class="offer-number" aria-hidden="true"><?= sprintf('%02d', $index + 1) ?></span>
            <div><h3><?= e($offer) ?></h3>
            <?php if ($index === 4): ?><a class="button button-primary" href="#contact-form" data-order-open>Bestill nå</a><?php endif ?>
            </div>
          </li>
          <?php endforeach ?>
        </ol>
      </section>

      <section class="section about-section" id="om-oss" aria-labelledby="about-title">
        <div class="about-intro">
          <p class="eyebrow">Om oss</p>
          <h2 id="about-title">Trenger du hjelp til montering av møbler?</h2>
          <p>Jeg er en erfaren og pålitelig montør med lang erfaring innen montering av alle typer møbler, fra enkle hjemmemøbler til komplekse løsninger for både private og bedrifter.</p>
          <?= image_html('assets/images/hvitt-garderoberom.webp', 'Ferdig montert garderobe med speildører', 'about-image', true, '(max-width: 980px) 85vw, 480px') ?>
        </div>
        <div class="about-details">
          <h3>Hva jeg kan hjelpe deg med</h3>
          <ul>
            <li>Skyvedørsgarderober og innebygde løsninger med ulike vanskelighetsgrader</li>
            <li>Kontormøbler: elektriske heve-/senkebord, kontorstoler og seksjoner</li>
            <li>Montering av kjøkken</li>
            <li>Veggmontering: TV-oppheng, hyller, speil og bilder</li>
            <li>Møbler fra Bohus, JYSK og andre leverandører</li>
            <li>IKEA-møbler: PAX-garderober, BESTÅ, HEMNES og andre serier</li>
          </ul>
          <h3>Hvorfor velge meg?</h3>
          <ul>
            <li>Høy kvalitet og nøyaktighet i arbeidet</li>
            <li>Profesjonelt verktøy og riktig innfesting for dine vegger</li>
            <li>Ryddig, pålitelig og møter til avtalt tid</li>
            <li>Tar vare på boligen din og rydder opp etter arbeidet</li>
          </ul>
          <p class="about-business">Arbeidet utføres offisielt via registrert enkeltpersonforetak (ENK). Du mottar faktura eller kvittering for oppdraget. Betaling kan gjøres via Vipps eller bankoverføring.</p>
        </div>
      </section>

      <section class="section process-section" id="prosess" aria-labelledby="process-title">
        <div class="section-heading">
          <p class="eyebrow">Slik fungerer det</p>
          <h2 id="process-title">Fra forespørsel til ferdig montert</h2>
        </div>
        <ol class="process-list">
          <li>
            <span>01</span>
            <h3>Send kort beskrivelse</h3>
            <p>Fortell hva som skal monteres, hvor jobben er, og legg gjerne ved bilder i forespørselen.</p>
          </li>
          <li>
            <span>02</span>
            <h3>Få avklaring</h3>
            <p>Omfang, materialer, veggtype og tidsbruk avklares før avtale.</p>
          </li>
          <li>
            <span>03</span>
            <h3>Avtal tidspunkt</h3>
            <p>Du får en praktisk tid som passer for hjemmet, kontoret eller lokalet.</p>
          </li>
          <li>
            <span>04</span>
            <h3>Montering og rydding</h3>
            <p>Jobben utføres effektivt, detaljer justeres, og området etterlates ryddig.</p>
          </li>
        </ol>
      </section>

      <section class="quote-cta" aria-labelledby="quote-title">
        <div>
          <p class="eyebrow">Tilbud</p>
          <h2 id="quote-title">Send noen linjer om jobben, så tar vi neste steg.</h2>
          <p>Jo bedre beskrivelse, desto enklere er det å gi riktig vurdering av tid, verktøy og gjennomføring.</p>
        </div>
        <a class="button button-primary" href="#contact-form" data-order-open>Bestill nå</a>
      </section>

      <section class="section faq-section" id="faq" aria-labelledby="faq-title">
        <div class="section-heading">
          <p class="eyebrow">FAQ</p>
          <h2 id="faq-title">Vanlige spørsmål</h2>
        </div>
        <div class="faq-list">
          <details>
            <summary>Tar dere både små og store oppdrag?</summary>
            <p>Ja. En hylle, et speil eller en TV-brakett kan være like viktig å få riktig som en større garderobe.</p>
          </details>
          <details>
            <summary>Kan dere montere møbler for bedrifter?</summary>
            <p>Ja. Kontorer, skoler, barnehager, møterom og næringslokaler kan få hjelp med flere møbler og rom samlet.</p>
          </details>
          <details>
            <summary>Hva bør jeg sende for et godt estimat?</summary>
            <p>Send gjerne produktlenke, antall møbler, romtype, område og bilder av vegger, gulv eller nisjer i forespørselen.</p>
          </details>
          <details>
            <summary>Kan dere hjelpe med skråtak og vanskelige hjørner?</summary>
            <p>Ja. Slike jobber vurderes på forhånd slik at løsning, feste og tidsbruk blir riktig.</p>
          </details>
          <details>
            <summary>Må jeg ha alt klart før montering?</summary>
            <p>Det hjelper om varene er levert, rommet er tilgjengelig og monteringsanvisninger følger med.</p>
          </details>
        </div>
      </section>

      <section class="contact-section" id="kontakt" aria-labelledby="contact-title">
        <div class="contact-inner">
          <div class="contact-copy">
            <p class="eyebrow">Kontakt oss</p>
            <h2 id="contact-title">Klar for å få jobben gjort?</h2>
            <p>
              Beskriv hva du trenger hjelp til, hvor jobben er, og legg gjerne ved
              bilder av møblene eller rommet.
            </p>
            <div class="contact-methods" aria-label="Direkte kontakt">
              <?php if ($s['phone']): ?><a class="contact-method" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $s['phone'])) ?>">Ring oss: <?= e($s['phone']) ?></a><?php endif ?>
              <?php if ($s['email']): ?><a class="contact-method" href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a><?php endif ?>
              <a class="contact-method" href="#contact-form" data-order-open>Bestill nå</a>
            </div>
            <ul class="contact-checklist">
              <li>Hva skal monteres eller henges opp?</li>
              <li>Hvor mange møbler eller rom gjelder det?</li>
              <li>Er det skråtak, betongvegg eller andre detaljer?</li>
            </ul>
          </div>

          <div class="contact-form-home" data-order-home>
          <form
            class="lead-form"
            id="contact-form"
            action="<?= e(url('contact.php')) ?>"
            method="post"
            enctype="multipart/form-data"
            data-lead-form
            aria-describedby="form-note form-status"
            novalidate
          >
            <input type="hidden" name="started_at"  data-started-at value="<?= (int)floor(microtime(true) * 1000) ?>">
            <?= csrf_input() ?>
            <div class="honeypot" aria-hidden="true">
              <label for="website">Nettside</label>
              <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-row">
              <label for="name">Navn</label>
              <input id="name" name="name" type="text" autocomplete="name" maxlength="80" required>
            </div>
            <div class="form-grid">
              <div class="form-row">
                <label for="phone" id="phone-label">Telefon</label>
                <div class="phone-control" role="group" aria-labelledby="phone-label">
                  <select id="phone-country" name="phone_country" aria-label="Land og landskode" autocomplete="tel-country-code">
                    <?php foreach (phone_countries() as $code => $country): ?><option value="<?= e($code) ?>" data-prefix="<?= e($country['prefix']) ?>" title="<?= e($country['label']) ?>" aria-label="<?= e($country['label'] . ($country['prefix'] === '' ? '' : ' +' . $country['prefix'])) ?>"><?= e($country['prefix'] === '' ? 'Annet (+)' : '+' . $country['prefix'] . ' ' . $code) ?></option><?php endforeach ?>
                  </select>
                  <input id="phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="32" placeholder="12345678" required>
                </div>
              </div>
              <div class="form-row">
                <label for="email">E-post <span>valgfritt</span></label>
                <input id="email" name="email" type="email" autocomplete="email" maxlength="120">
              </div>
            </div>
            <div class="form-grid">
              <div class="form-row">
                <label for="service">Hva gjelder det?</label>
                <select id="service" name="service" required>
                  <option value="">Velg tjeneste</option>
                  <?php foreach (enquiry_service_options() as $title => $label): ?><option value="<?= e($title) ?>" <?= $selectedService === $title ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
                  <?php if ($selectedService !== '' && $selectedService !== 'Annet handyman-oppdrag' && !isset(enquiry_service_options()[$selectedService]) && in_array($selectedService, contact_services(), true)): ?><option value="<?= e($selectedService) ?>" selected><?= e($selectedService) ?></option><?php endif ?>
                  <option value="Annet handyman-oppdrag" <?= $selectedService === 'Annet handyman-oppdrag' ? 'selected' : '' ?>>Annet oppdrag</option>
                </select>
              </div>
              <div class="form-row">
                <label for="area">By eller område</label>
                <input id="area" name="area" type="text" autocomplete="address-level2" maxlength="80" required>
              </div>
            </div>
            <div class="form-row">
              <label for="message">Kort beskrivelse</label>
              <textarea
                id="message"
                name="message"
                rows="5"
                maxlength="1500"
                minlength="20"
                placeholder="Skriv hva som skal monteres, antall møbler og eventuelle vanskelige detaljer."
                required
              ></textarea>
            </div>
            <div class="form-row">
              <label for="product-url">Lenke til møblene <span>valgfritt</span></label>
              <input id="product-url" name="product_url" type="url" inputmode="url" maxlength="2048" placeholder="https://" autocomplete="off">
            </div>
            <div class="form-row photo-field">
              <label for="photos">Bilder <span>valgfritt</span></label>
              <input id="photos" name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" aria-describedby="photos-note photos-count" data-remove-icon="<?= e(url('assets/icons/x.svg')) ?>">
              <p class="form-note" id="photos-note">Inntil 5 bilder. Maks 5 MB per bilde. JPEG, PNG eller WebP.</p>
              <p class="photo-count" id="photos-count" role="status" aria-live="polite">Ingen bilder valgt.</p>
              <p class="field-error" data-photo-error role="alert" hidden></p>
              <div class="photo-preview-list" data-photo-previews></div>
            </div>
            <label class="consent-row" for="privacy">
              <input id="privacy" name="privacy" type="checkbox" value="1" required>
              <span>Jeg samtykker til at opplysningene brukes for å svare på forespørselen.</span>
            </label>
            <p class="form-note" id="form-note">Bildene brukes bare til å vurdere forespørselen og publiseres ikke på nettsiden.</p>
            <button class="button button-primary form-button" type="submit">
              <span class="button-label">Send forespørsel</span>
              <span class="button-loading" hidden>Sender...</span>
            </button>
            <p class="form-status" id="form-status" role="status" aria-live="polite" hidden></p>
          </form>
          </div>
        </div>
      </section>
      <?php reviews_section($reviews, $reviewsAvailable); ?>
      <dialog class="order-dialog" id="order-dialog" aria-labelledby="order-title">
        <div class="order-dialog-heading">
          <div><p class="eyebrow">Uforpliktende forespørsel</p><h2 id="order-title">Bestill nå</h2></div>
          <button class="gallery-icon" type="button" data-order-close aria-label="Lukk skjema" title="Lukk skjema"><img src="<?= e(url('assets/icons/x.svg')) ?>" alt="" width="22" height="22"></button>
        </div>
        <div data-order-form-slot></div>
      </dialog>
    </main>
<?php public_footer(); ?>

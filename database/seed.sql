-- Existing site copy and project photos. No administrator or fake contact details.
INSERT IGNORE INTO service_categories (id,title,slug,sort_order) VALUES (1,'Kontor og næring','kontor-og-naering',0);
INSERT IGNORE INTO service_categories (id,title,slug,sort_order) VALUES (2,'Garderober og skyvedører','garderober-og-skyvedorer',1);
INSERT IGNORE INTO service_categories (id,title,slug,sort_order) VALUES (3,'Kjøkkenmontering','kjokkenmontering',2);
INSERT IGNORE INTO service_categories (id,title,slug,sort_order) VALUES (4,'Møbelmontering hjemme','mobelmontering-hjemme',3);
INSERT IGNORE INTO service_categories (id,title,slug,sort_order) VALUES (5,'Veggmontering','veggmontering',4);
INSERT IGNORE INTO service_categories (id,title,slug,sort_order) VALUES (6,'Handyman-tjenester','handyman-tjenester',5);
INSERT IGNORE INTO services (id,category_id,title,slug,short_description,full_description,image,featured,sort_order) VALUES (1,1,'Kontor, skole og næring','kontor-og-naering','Kontorpulter, skap, reoler, møtebord og inventar for lokaler som skal raskt i bruk.','Kontorpulter, skap, reoler, møtebord og inventar for lokaler som skal raskt i bruk.

Vi hjelper med kontorpulter, skap, reoler og møtebord. Antall møbler, adkomst og tidspunkt avklares før oppstart, slik at lokalene kan tas i bruk med minst mulig avbrudd.','',1,0);
INSERT IGNORE INTO services (id,category_id,title,slug,short_description,full_description,image,featured,sort_order) VALUES (2,2,'Garderober og skyvedører','garderober-og-skyvedorer','Skap, skinner, fronter, kurver og innredning monteres med fokus på rette linjer og stabil bruk.','Skap, skinner, fronter, kurver og innredning monteres med fokus på rette linjer og stabil bruk.

Skap, skinner og innredning monteres og justeres nøye. Ved skråtak eller trange rom avklarer vi løsningen ut fra mål og bilder før arbeidet starter.','assets/images/garderobe-under-skratt-tak.jpg',1,1);
INSERT IGNORE INTO services (id,category_id,title,slug,short_description,full_description,image,featured,sort_order) VALUES (3,3,'Kjøkken og krevende skap','kjokkenmontering','Hjelp med hjørneskap, uttrekk, skyvesystemer og løsninger under skråtak eller mansard.','Hjelp med hjørneskap, uttrekk, skyvesystemer og løsninger under skråtak eller mansard.

Vi hjelper med kjøkkenmoduler, fronter og uttrekksløsninger. Omfang og eventuell tilpasning avklares på forhånd. Elektriske og rørtekniske arbeider avtales med riktig fagperson.','',1,2);
INSERT IGNORE INTO services (id,category_id,title,slug,short_description,full_description,image,featured,sort_order) VALUES (4,4,'Møbler til private hjem','mobelmontering-hjemme','Senger, kommoder, spisebord, skap, reoler og flatpakkede møbler monteres stabilt.','Senger, kommoder, spisebord, skap, reoler og flatpakkede møbler monteres stabilt.

Senger, kommoder, spisebord og flatpakkede møbler monteres stabilt. Send produktnavn, antall møbler og område, så avklarer vi hva oppdraget innebærer.','',1,3);
INSERT IGNORE INTO services (id,category_id,title,slug,short_description,full_description,image,featured,sort_order) VALUES (5,5,'Veggmontering','veggmontering','Bilder, speil, hyller, TV-braketter, gardinstenger og andre detaljer henges opp pent.','Bilder, speil, hyller, TV-braketter, gardinstenger og andre detaljer henges opp pent.

Vi monterer hyller, speil, gardinstenger og TV-fester. Veggtype, underlag og egnet innfesting vurderes før montering.','assets/images/tv-og-oppbevaringsvegg.jpg',1,4);
INSERT IGNORE INTO services (id,category_id,title,slug,short_description,full_description,image,featured,sort_order) VALUES (6,6,'Små handyman-jobber','handyman-tjenester','Små fikser hjemme eller på kontoret tas seriøst. Ingen jobb er for liten når den må gjøres ordentlig.','Små fikser hjemme eller på kontoret tas seriøst. Ingen jobb er for liten når den må gjøres ordentlig.

Små praktiske oppdrag hjemme eller på kontoret avklares ut fra behov og bilder. Vi vurderer om arbeidet passer vårt tjenestetilbud før en avtale inngås.','',1,5);
INSERT IGNORE INTO gallery_items (id,image,caption,alt_text,sort_order) VALUES (1,'assets/images/garderobe-under-skratt-tak.jpg','Garderobe under skråtak','Sort garderobe montert under skråtak med presis tilpasning mot lav takhøyde',0);
INSERT IGNORE INTO gallery_items (id,image,caption,alt_text,sort_order) VALUES (2,'assets/images/apent-garderobesystem-med-kurver.jpg','Åpent garderobesystem','Åpent hvitt garderobesystem med skuffer, kurver og hengestenger',1);
INSERT IGNORE INTO gallery_items (id,image,caption,alt_text,sort_order) VALUES (3,'assets/images/tv-og-oppbevaringsvegg.jpg','TV- og oppbevaringsvegg','Hvit oppbevaringsvegg bygget rundt TV med hyller og skyvedørsskinne',2);
INSERT IGNORE INTO gallery_items (id,image,caption,alt_text,sort_order) VALUES (4,'assets/images/skyvedorsgarderobe-ved-trapp.jpg','Skyvedører ved trapp','Skyvedørsgarderobe med speil montert ved trapp og skrå vegg',3);
INSERT IGNORE INTO gallery_items (id,image,caption,alt_text,sort_order) VALUES (5,'assets/images/garderobe-med-varm-trebelysning.jpg','Garderobe i mørkt tre','Garderobe i mørkt tre med skuffer og hyller',4);
INSERT IGNORE INTO gallery_items (id,image,caption,alt_text,sort_order) VALUES (6,'assets/images/brun-garderobe-innredning.jpg','Innredning med skuffer','Brun garderobeinnredning med skuffer, hyller og hengestenger',5);
INSERT IGNORE INTO gallery_items (id,image,caption,alt_text,sort_order) VALUES (7,'assets/images/montert-garderoberom-sort.jpg','Sort garderoberom','Sort garderoberom med åpne hyller og skuffer ferdig montert',6);
INSERT IGNORE INTO gallery_items (id,image,caption,alt_text,sort_order) VALUES (8,'assets/images/lys-tregarderobe-med-speil.jpg','Lys tregarderobe','Lys tregarderobe med høye skapfronter montert på soverom',7);
INSERT IGNORE INTO gallery_items (id,image,caption,alt_text,sort_order) VALUES (9,'assets/images/sort-hyllesystem-montert.jpg','Hylle- og skapinnredning','Sort hylle- og skapinnredning ferdig montert på hvit vegg',8);
INSERT IGNORE INTO site_settings (setting_key,setting_value) VALUES ('company_name','fiksitt'),('slogan','Presis montering. Ryddig levert.'),('service_region','Østlandet'),('primary_cta','Be om tilbud'),('phone',''),('email',''),('public_domain',''),('organization_number',''),('social_url','');

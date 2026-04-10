# Changelog

## [1.0.9] - 2026-04-09

### Fixed
- Opravene odosielanie e-mailov so ZIP prilohou: priloha sa pridava iba do jedneho cieloveho pola mail parametrov podla aktualneho mail flow.
- Pre ZIP je pri odoslani znormalizovany MIME typ na `application/zip`.

### Changed
- Navysena verzia modulu na `1.0.9`.

## [1.0.8] - 2026-04-09

### Fixed
- Upgrade skripty boli upravene na non-blocking rezim, aby update modulu nezlyhal bez vypisu chyby.
- Pridany fallback upgrade krok `upgrade/upgrade-1.0.8.php` vracajuci `true` pre spolahlive obnovenie update flow.

### Changed
- Navysena verzia modulu na `1.0.8`.

## [1.0.7] - 2026-04-09

### Fixed
- Opravene duplicity e-mail sablon v selecte aj pre pripady viacnasobnych pripon (`.html.twig`).
- Doplnena kompatibilita nacitavania priloh aj pre starsie ulozene nazvy sablon (`template`, `template.html`, `template.txt`, `template.twig`, `template.html.twig`).

### Added
- Rozsirena podpora priloh o: `RAR`, `TXT`, `DOCX`, `XLS`, `XLSX`, `ODT`, `ODS`, `PNG`, `JPG`, `GIF`.

### Changed
- Navysena verzia modulu na `1.0.7`.

## [1.0.6] - 2026-04-09

### Fixed
- Opravene duplicity v zozname e-mailovych sablon: sablony sa teraz deduplikuju podla normalizovaneho nazvu (bez pripony a bez rozlisenia velkosti pismen).

### Changed
- Nazov sablony sa interne normalizuje na lowercase pre konzistentne mapovanie medzi `.html`, `.txt` a `.twig` variantami.
- Navysena verzia modulu na `1.0.6`.

## [1.0.5] - 2026-04-09

### Fixed
- Opravene upgrade skripty tak, aby neboli zavisle na explicitnom `instanceof mv_attachment`.
- Pridany dalsi upgrade krok `upgrade/upgrade-1.0.5.php` pre spolahlive opakovane spustenie update po zlyhani predchadzajuceho pokusu.

### Changed
- Navysena verzia modulu na `1.0.5`.

## [1.0.4] - 2026-04-09

### Added
- Pridany upgrade skript `upgrade/upgrade-1.0.4.php` pre spolahlive nasadenie pri update z predoslych verzii.

### Changed
- Navysena verzia modulu na `1.0.4` pre jednoznacny upgrade path z `1.0.2`/`1.0.3`.

## [1.0.3] - 2026-04-09

### Added
- Doplnena ochrana proti duplicitnemu pridaniu rovnakej prilohy pri viacerych mail hookoch v jednom toku.
- Doplneny limit velkosti uploadu (5 MB).

### Changed
- Rozsirene nacitanie e-mail sablon pre PS8/PS9 aj o `.txt` a `.twig` (okrem `.html`).
- Sprisnena validacia MIME typu pri nahravani suboru (MIME musi byt detegovatelny a povoleny).
- Zjednotena normalizacia nazvu sablony aj pri ukladani formulare.
- Navysena verzia modulu na `1.0.3`.

## [1.0.2] - 2026-04-09

### Added
- Pridany slovensky preklad pre administraciu modulu cez subor `translations/sk.php`.
- Kod modulu zostava v predvolenom anglickom jazyku, lokalizacia je riesena samostatnym prekladom.

### Changed
- Navysena verzia modulu na `1.0.2`.

## [1.0.1] - 2026-04-09

### Added
- Doplnena kompatibilita pre rozne mail flow v PrestaShop 8/9 cez fallback hook `actionEmailSendBefore`.
- Doplnena interna metoda pre spolocne spracovanie e-mail parametrov a pridavanie priloh.
- Doplnena normalizacia nazvu sablony (odstranenie cesty a koncovky `.html`, `.txt`, `.twig`).
- Doplnena automaticka registracia hookov pri otvoreni konfiguracie modulu (bez nutnosti reinstall).
- Pridany upgrade skript `upgrade/upgrade-1.0.1.php`.
- Doplnena validacia, ze `template_name` je mozne ulozit iba zo zoznamu dostupnych e-mail sablon.
- Doplnena automaticka pripona v nazve prilohy pre e-mail, ak `display_name` priponu neobsahuje.

### Changed
- BO formular pouziva vyber sablony cez `select` (nie volny text).
- Vnutorna mail logika doplna prilohy do `attachments` aj `fileAttachment` pre lepsiu kompatibilitu.

### Fixed
- Opraveny scenar, ked sa priloha nezobrazila v e-maile napriek aktivnemu zaznamu.

## [1.0.0] - 2026-04-09

### Added
- Inicialna verzia modulu `mv_attachment`.
- Vytvorenie DB tabulky `ps_mv_attachment` pri instalacii.
- Odstranenie DB tabulky a upload priecinka pri uninstall.
- BO zoznam priloh cez `HelperList`.
- BO formular cez `HelperForm` pre pridanie a upravu prilohy.
- Upload dokumentov (PDF, ZIP, DOC) do `/upload/mv_attachment/`.
- Ukladanie metadat: `display_name`, `template_name`, `id_lang`, `file_mime`, `active`.
- Hook `actionMailSendBeforeOut` pre dynamicke pripajanie priloh podla sablony a jazyka.
- Bezpecnostne prvky upload adresara (`index.php`, `.htaccess`).
- Post-submit redirect po create/update/delete pre cistu URL a bezpecne obnovenie stranky.

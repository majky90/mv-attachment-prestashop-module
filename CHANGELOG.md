# Changelog

## [1.1.0] - 2026-04-10

### Added
- Added upgrade script `upgrade/upgrade-1.1.0.php` for reliable in-place updates without uninstall/reinstall.

### Changed
- Upgrade flow now ensures required DB schema, upload directory hardening files, and mail hooks during update.
- Bumped module version to `1.1.0`.

## [1.0.9] - 2026-04-09

### Fixed
- Fixed sending e-mails with ZIP attachments: the attachment is now added only to one target mail parameter field according to the active mail flow.
- For ZIP files, outgoing MIME type is normalized to `application/zip`.

### Changed
- Bumped module version to `1.0.9`.

## [1.0.8] - 2026-04-09

### Fixed
- Updated upgrade scripts to non-blocking mode so module updates do not fail silently.
- Added fallback upgrade step `upgrade/upgrade-1.0.8.php` returning `true` for reliable recovery of the update flow.

### Changed
- Bumped module version to `1.0.8`.

## [1.0.7] - 2026-04-09

### Fixed
- Fixed duplicate e-mail templates in the select list, including cases with multiple extensions (`.html.twig`).
- Added compatibility for loading attachments with older stored template names (`template`, `template.html`, `template.txt`, `template.twig`, `template.html.twig`).

### Added
- Extended attachment support with: `RAR`, `TXT`, `DOCX`, `XLS`, `XLSX`, `ODT`, `ODS`, `PNG`, `JPG`, `GIF`.

### Changed
- Bumped module version to `1.0.7`.

## [1.0.6] - 2026-04-09

### Fixed
- Fixed duplicates in the e-mail template list: templates are now deduplicated by normalized name (without extension and case-insensitive).

### Changed
- Template names are now internally normalized to lowercase for consistent mapping between `.html`, `.txt`, and `.twig` variants.
- Bumped module version to `1.0.6`.

## [1.0.5] - 2026-04-09

### Fixed
- Fixed upgrade scripts so they are no longer dependent on explicit `instanceof mv_attachment` checks.
- Added another upgrade step `upgrade/upgrade-1.0.5.php` for reliable repeated update execution after a previous failed attempt.

### Changed
- Bumped module version to `1.0.5`.

## [1.0.4] - 2026-04-09

### Added
- Added upgrade script `upgrade/upgrade-1.0.4.php` for reliable deployment when updating from previous versions.

### Changed
- Bumped module version to `1.0.4` for a clear upgrade path from `1.0.2`/`1.0.3`.

## [1.0.3] - 2026-04-09

### Added
- Added protection against duplicate insertion of the same attachment when multiple mail hooks run in one flow.
- Added upload size limit (5 MB).

### Changed
- Extended e-mail template loading for PS8/PS9 to include `.txt` and `.twig` (in addition to `.html`).
- Tightened MIME type validation during file upload (MIME must be detectable and allowed).
- Unified template name normalization during form save.
- Bumped module version to `1.0.3`.

## [1.0.2] - 2026-04-09

### Added
- Added Slovak translation for module administration via `translations/sk.php`.
- Module code remains in default English; localization is handled by separate translations.

### Changed
- Bumped module version to `1.0.2`.

## [1.0.1] - 2026-04-09

### Added
- Added compatibility for different mail flows in PrestaShop 8/9 via fallback hook `actionEmailSendBefore`.
- Added internal method for shared e-mail parameter processing and attachment injection.
- Added template name normalization (remove path and `.html`, `.txt`, `.twig` suffixes).
- Added automatic hook registration when opening module configuration (no reinstall needed).
- Added upgrade script `upgrade/upgrade-1.0.1.php`.
- Added validation that `template_name` can be saved only from available e-mail templates list.
- Added automatic extension in e-mail attachment name when `display_name` has no extension.

### Changed
- Back-office form now uses template selection via `select` (not free text).
- Internal mail logic now adds attachments to both `attachments` and `fileAttachment` for better compatibility.

### Fixed
- Fixed case where attachment was not shown in e-mail despite an active record.

## [1.0.0] - 2026-04-09

### Added
- Initial release of module `mv_attachment`.
- Create DB table `ps_mv_attachment` on install.
- Remove DB table and upload folder on uninstall.
- Back-office attachment list via `HelperList`.
- Back-office form via `HelperForm` for creating and editing attachments.
- Document upload (PDF, ZIP, DOC) to `/upload/mv_attachment/`.
- Store metadata: `display_name`, `template_name`, `id_lang`, `file_mime`, `active`.
- Hook `actionMailSendBeforeOut` for dynamic attachment injection by template and language.
- Upload directory hardening (`index.php`, `.htaccess`).
- Post-submit redirect after create/update/delete for clean URL and safe page refresh.

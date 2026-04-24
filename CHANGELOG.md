# Changelog

All notable changes to `dashed-popups` will be documented in this file.

## 4.8.0 - 2026-04-24

### Added
- Targeting-regels per popup: include/exclude op URL-patronen en visitable models (Product, Page, enzovoort) via nieuwe `dashed__popup_targets` tabel.
- `visibility_mode` kolom op `dashed__popups` met keuze `overal` of `alleen op selectie`.
- `PopupTargetingService` evalueert regels tijdens Livewire mount; exclude-regels winnen altijd.
- `MetricsResolver::breakdownBy(popupId, dimension, from, to)` voor groepering op `url`, `device_type`, `locale` of `referrer_domain`. Retourneert views, submits, redemptions, revenue en net_revenue per bucket.
- Breakdown-tabellen per dimensie toegevoegd aan de bestaande `PopupAnalyticsPanel` (URL, device, locale, referrer); kolommen voor conversies en omzet zijn alleen zichtbaar voor korting-popups.
- Nieuwe Filament "Weergave"-sectie op `PopupResource` met URL-pattern repeaters (include + exclude) en per-routeModel radio (geen/alle/geselecteerde) + multi-select.

### Changed
- `Page::buildPageResponse`, `ProductCategory::resolveRoute` en `Article::resolveRoute` zetten het huidige visitable model op `request()->attributes` zodat popup-targeting dit kan lezen zonder cross-request leakage.

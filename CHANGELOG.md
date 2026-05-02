# Changelog

All notable changes to `dashed-popups` will be documented in this file.

## v4.9.1 - 2026-05-02

### Added
- Pest test-suite voor de popup→newsletter sync feature: `tests/Feature/PopupNewsletterSyncTest.php` met 10 tests / 17 asserts. Dekt: dispatch-gate (`$wasFirstSubmit && api_subscriptions`), idempotency-guard (`newsletter_synced_at`), per-provider error-isolatie, en de query achter de backfill-actie. `tests/TestCase.php`, `tests/Pest.php`, `tests/TestServiceProvider.php` en `phpunit.xml.dist` toegevoegd voor Orchestra Testbench setup zonder afhankelijkheid van het volledige `cms()`-runtime.

## v4.9.0 - 2026-05-01

### Added
- Newsletter-koppeling per popup: in een nieuwe sectie "Nieuwsbrief koppeling" op de Popup-edit page kan een repeater met meerdere providers (LaPoste, Ternair, etc.) worden geconfigureerd via `forms()->builder('popupApiClasses')`. Configuratie wordt opgeslagen op `dashed__popups.api_subscriptions` (json).
- `SyncPopupSubmissionToNewsletterJob` dispatched zodra een eerste e-mail wordt ingevuld op een popup met `api_subscriptions`. Idempotency-guard via nieuwe `dashed__popup_views.newsletter_synced_at`-kolom; als die al gezet is wordt de job no-op.
- Errors per provider worden gevangen en gelogd; één falende provider blokkeert geen andere providers in dezelfde dispatch.
- Backfill-actie "Stuur eerder verzamelde emails door" op de Popup edit-page om reeds ingevulde maar nog niet gesyncte e-mailadressen alsnog door te zetten naar de nieuwsbrief-lijsten.

### Fixed
- Livewire-hydratie crashte met "Cannot assign array to property `$email` of type string" wanneer een client-payload een array op het email-veld stuurde. `$email` is nu untyped zodat `rules()` (via `validate()`) de error netjes opvangt in plaats van te crashen tijdens hydratie.

## v4.8.2 - 2026-04-24

### Added

- `matched_order_id` op popup-conversies (`PopupView`): koppelt conversies
  automatisch aan een betaalde order binnen 30 dagen via `discount_code_id`
  of `email`. De "Conversies"-tab op een popup toont nu een vinkje +
  tooltip + link naar de order.
- `PopupOrderMatcher` service met twee ingangen: `matchView` (per conversie)
  en `matchForOrder` (wanneer een order paid wordt).
- Automatische trigger: `OrderPopupMatchObserver` vindt kandidaten zodra
  een order `paid` / `waiting_for_confirmation` / `partially_paid` wordt.
- Command `php artisan dashed-popups:backfill-order-matches` om bestaande
  conversies terugwerkend te koppelen. Accepteert `--chunk=500`.
- Filter "Heeft order" in de Conversies-tabel.

Matches zijn eenmalig: een eenmaal gekoppelde conversie krijgt nooit een
andere order toegewezen. `user_id`-match valt buiten scope.

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

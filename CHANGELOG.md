# Changelog

All notable changes to `dashed-popups` will be documented in this file.

## v4.12.2 - 2026-05-03

### Fixed
- `PopupFollowUpFlowResource` was niet geregistreerd in `DashedPopupsPlugin::register()`. Daardoor was de admin-route `/admin/popup-follow-up-flows` niet bereikbaar en konden gebruikers helemaal geen follow-up flow aanmaken — wat ervoor zorgde dat de Popup-form de "Standaard flow gebruiken (indien ingesteld)" placeholder toonde maar er nooit een default flow bestond. Resource nu in de plugin toegevoegd.

## v4.12.1 - 2026-05-03

### Added
- **Backfill-knop op de Popup zelf** (in aanvulling op de flow-niveau actie van v4.12.0). De Popup edit-pagina toont nu de actie "Follow-up flow toepassen op bestaande inzendingen" die alleen de inzendingen van DEZE popup in de bijbehorende flow zet. Handig wanneer je per popup wil backfillen ipv flow-breed. Visible alleen als de popup een resolveFollowUpFlow() heeft.
- `BackfillPopupFollowUpFlowService::run()` heeft een optionele `$onlyPopupId` parameter waarmee de backfill gescoped wordt op één specifieke popup ipv alle popups die de flow gebruiken.

## v4.12.0 - 2026-05-03

### Added
- **Backfill-knop op PopupFollowUpFlow.** Op de bewerk-pagina van een follow-up flow staat nu de actie "Toepassen op bestaande" die de emails van de flow alsnog plant voor `PopupView`-records waar de bezoeker al een email heeft ingevuld (`submitted_at` gevuld) maar nog niet in een follow-up flow zit. Configureerbaar venster (1–365 dagen, default 30). Skipt views die al gestart, geannuleerd of zonder email zijn, en views waarvan de popup een ander resolveFollowUpFlow() teruggeeft.
- Nieuwe service `Services\BackfillPopupFollowUpFlowService` met statistics-array (`views_started`, `views_skipped_*`, `emails_dispatched`).
- `PopupView::followUpStatus()` retourneert: `not_in_flow`, `cancelled`, `finished` of `step_X_of_Y` op basis van `follow_up_started_at`, `follow_up_cancelled_at` en de `send_after_minutes` van de actieve emails. Geeft inzicht waar elke bezoeker zit in de flow zonder extra log-tabel.
- Nieuwe kolom in de Conversions-tabel (op de Popup edit-pagina): "Follow-up flow" badge met de huidige status — geannuleerd, niet in flow, afgerond, of "Stap X van Y".

## v4.11.0 - 2026-05-02

### Added
- `closeAllPopups` browser-event wordt nu gedispatched bij `clickAway()` of `goTo()` als de popup in success-state staat (na ingevulde email + getoonde discount-code). Andere popups op de pagina (bv. cart-popup, added-to-cart popup) kunnen hierop luisteren en zichzelf sluiten zodat het scherm volledig leeg is na het wegklikken van de discount.
- Nieuwe check in `mount()`: discount-popups slaan zichzelf over als de gebruiker eerder al een korting heeft geclaimd via een willekeurige discount-popup (`PopupView` met `submitted_at` en `discount_code_id` op user_id of email). Voorkomt dat dezelfde gebruiker bij elke nieuwe popup opnieuw zijn email moet invullen.

### Changed
- Auto-dismiss timer in success-state roept nu `clickAway` aan ipv `$wire.set('showPopup', false)` zodat `closed_at` correct wordt gelogd én het `closeAllPopups`-event ook bij auto-dismiss vuurt.

## v4.10.0 - 2026-05-02

### Added
- **Popup follow-up email flow.** Popup-gebruikers die hun email achterlaten maar (nog) niet bestellen krijgen automatisch een reeks follow-up mails. Werkt onafhankelijk van de cart (ook bij lege winkelwagen).
  - Nieuwe tabellen `dashed__popup_follow_up_flows` (naam, `is_default`) en `dashed__popup_follow_up_emails` (per stap: `send_after_minutes`, `is_active`, translatable `subject` + `blocks`).
  - Nieuwe kolommen op `dashed__popups`: `follow_up_flow_id` (FK, nullable). Resolution: eigen flow ➝ globale default ➝ geen flow.
  - Nieuwe kolommen op `dashed__popup_views`: `follow_up_started_at` (idempotency-guard) en `follow_up_cancelled_at`.
  - `PopupFollowUpFlow::default()` + uniqueness-guard zodat maar één flow tegelijk default is.
  - `Popup::resolveFollowUpFlow()` regelt de fallback-keten.
  - `SendPopupFollowUpEmailJob` per stap dispatched met `delay()`. Job checkt `follow_up_cancelled_at` op runtime; cancel komt automatisch door wanneer een betaalde order met dit emailadres binnenkomt.
  - `CancelPopupFollowUpsOnPaidOrder` listener gekoppeld aan `Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent`. Cancel-scope: alleen op email-match (niet op user_id).
  - Trigger zit in `Popup::submitEmail()` na de newsletter-sync; alleen op eerste submit (`$wasFirstSubmit && follow_up_started_at IS NULL`).
- **Filament admin UI** in dezelfde stijl als de abandoned-cart flow:
  - `PopupFollowUpFlowResource` met list/edit, repeater van emails per flow.
  - Per email: Builder-component met blocks `heading`, `paragraph` (RichEditor), `button`, `image`, `divider`, `usp` (textarea, één per regel) en `discount` (handmatige code óf fallback naar `popup_view.discount_code_id`).
  - Translatable `subject` + `blocks` via spatie/laravel-translatable.
  - Variabele substitutie `:siteName:` en `:email:` in subject + tekst-blocks.
  - Theme-override fallback: `<theme>.emails.popup-follow-up` ➝ `dashed-popups::emails.follow-up`.
  - Sectie "Follow-up flow" op `PopupResource` met Select voor `follow_up_flow_id` en helper-text over de fallback.

## v4.9.3 - 2026-05-02

### Added
- `Exceptions\NewsletterRateLimitException` met `retryAfter`-seconds. Provider-classes (Laposta, Ternair, ...) kunnen die gooien wanneer de externe API rate-limit'd; `SyncPopupSubmissionToNewsletterJob` vangt 'm op en doet `release($retryAfter)` zodat de job opnieuw op de queue komt zonder de andere providers in dezelfde view te raken. Vereist `dashed-laposta` v4.0.12+.
- `SyncPopupSubmissionToNewsletterJob::__construct(int $popupViewId, bool $force = false)`: tweede parameter forceert het opnieuw versturen van een al gesyncte submission (idempotency-guard wordt overgeslagen). Default false zodat bestaande dispatches gedrag-identiek blijven.
- Backfill-actie op `EditPopup` heeft nu een `Toggle` "Alles opnieuw versturen". Aan = stuur ook reeds verzonden inzendingen opnieuw door (forceert via de `$force`-flag op de job). Uit = alleen nog niet verzonden. De actie is nu zichtbaar zolang er überhaupt submissions zijn (niet meer disabled bij 0 pending).
- `EditPopup::totalSubmissionsCount()` als helper voor de visibility/disabled-logica.

## v4.9.2 - 2026-05-02

### Changed
- `EditPopup::dispatchNewsletterBackfill()` extracted as a protected method zodat de backfill-query, job-dispatch en notification direct in tests aangeroepen kunnen worden via een anonymous EditPopup-subklasse (zonder Filament panel-auth te bootstrappen). De `syncToNewsletter` Filament-action is nu een dunne shim. Methode geeft de dispatch-count terug voor assertability. Geen gedragsverandering in productie.

### Style
- Pint import-sorting sweep over `PopupResource`, `EditPopup`, `SyncPopupSubmissionToNewsletterJob`, models, migraties, analytics, observers en services. Pure formatting.

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

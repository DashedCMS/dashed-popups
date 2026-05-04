# Changelog

All notable changes to `dashed-popups` will be documented in this file.

## v4.13.7 - 2026-05-04

### Added
- **Afmeld-link onderaan elke popup-follow-up-mail**. Nieuwe signed route `dashed.frontend.popup-follow-up.unsubscribe` op `/popup-follow-up/unsubscribe/{view}` met `PopupFollowUpUnsubscribeController`: zet `PopupView::follow_up_cancelled_at = now()` zodat de bestaande check in `SendPopupFollowUpEmailJob::handle()` alle reeds geplande vervolgstappen overslaat. Toont een bevestigingspagina. `PopupFollowUpMail::build()` levert nu `$unsubscribeUrl` (signed) en `$unsubscribeLabel` aan de gedeelde `dashed-core::emails.layout` (vereist `dashed-core` v4.3.4+).
- Routes worden nu geladen via `loadRoutesFrom(__DIR__.'/../routes/frontend.php')` in `DashedPopupsServiceProvider::configurePackage()`.

## v4.13.6 - 2026-05-03

### Added
- **Send-time check op popup-conversie -> order match** in `SendPopupFollowUpEmailJob::handle()`. Vlak voor het versturen van een follow-up mail draait de job nu een laatste `PopupOrderMatcher::matchView()` over de `PopupView` (om recente conversies op te pikken die de `OrderMarkedAsPaidEvent`-listener zou hebben gemist) en checkt vervolgens `matched_order_id`. Is die gevuld -> de mail wordt overgeslagen, `follow_up_cancelled_at` wordt gezet zodat alle reeds gequeue-de vervolgstappen ook stoppen. Dit is een minimale defensieve check bovenop de event-driven cancel-listener; ook als de event nooit fired (eigen order-flow, manuele DB-edit, race-condition met queue-delay) komt de bezoeker niet meer in de flow voor.

## v4.13.5 - 2026-05-03

### Added
- **Test-mail knop per follow-up email** in de Repeater op de PopupFollowUpFlow edit-pagina. Naast de drag-handle, collapse-toggle en delete-button staat nu een "papieren-vliegtuig"-icon dat een modal opent met een ontvanger-veld (default = ingelogde admin). Bouwt een transient `PopupFollowUpEmail` + `PopupView` op uit de huidige form-state (werkt dus ook voor nog niet opgeslagen wijzigingen) en stuurt synchroon via `Mail::to()->sendNow(new PopupFollowUpMail(...))`. Errors gaan via `report()` naar de error-log + danger-notification.

### Changed
- **PopupFollowUpMail rendert nu via de gedeelde `dashed-core::emails.layout`** - dezelfde wrapper (header met logo of site-naam tegen primaryColor band, content-card met afgeronde hoeken, footer-tekst met © + jaar) die de andere systeem-mails (admin order confirmation, payment-link, password reset, etc.) gebruiken. Voorheen rendered popup-followups via een eigen `dashed-popups::emails.follow-up` view zonder logo/footer. Block-types (`heading`, `paragraph`/`text`, `button`, `image`, `divider`, `usp`, `discount`) worden nu per stuk omgezet naar `<tr><td>` rijen die in de layout-table passen; popup-specifieke types (`paragraph`/`usp`/`discount`) krijgen aliassen of inline rendering die qua styling matcht. Customsetting-driven kleuren (`mail_primary_color`, `mail_text_color`, `mail_background_color`) en `mail_show_logo` / `mail_show_site_name` / `mail_logo` worden gerespecteerd.
- **Volledig variabelen-systeem in alle blokken**: 5 variabelen (`:siteName:`, `:email:`, `:discountCode:`, `:discountValue:`, `:siteUrl:`) zijn beschikbaar in subject + élk tekst-, link-, label- en code-veld van elk block-type (heading, paragraph, button label/url, image url/alt, usp items, discount label/code). Helper-text staat op elk individueel veld én op de Builder zelf. Substitutie via `strtr()` op alle data-fields in `renderBlock()`.
- `PopupFollowUpFlow::createDefault()` gebruikt nu alle variabelen in zowel onderwerp als blocks van de 3 standaard-stappen: paragraph-teksten tonen `:discountValue:` + `:discountCode:`, en elke stap krijgt een button-block ("Bekijk onze producten" / "Shoppen bij :siteName:" / "Naar de website") met `:siteUrl:` als URL.
- **Discount-block toont nu het kortingsbedrag** ("Bespaar **12,5%** op je bestelling" of "Bespaar **€ 5,00** op je bestelling"). Wordt afgeleid uit `DiscountCode::type` + `discount_percentage` of `discount_amount` via nieuwe `PopupFollowUpMail::resolvePopupDiscountInfo()` die zowel code als geformatteerde value teruggeeft. Bedrag-formatting via `CurrencyHelper::formatPrice()` (zelfde format als de rest van het systeem) met fallback voor environments zonder dashed-ecommerce-core. Percentage-formatting trimt trailing nullen ("10%" / "12,5%" / "7,75%").
- **Decimale kortingspercentages**: `dashed__popups.discount_percentage` en `dashed__popup_variants.discount_percentage_override` zijn omgezet van `integer` naar `decimal(5,2)`. Filament-velden hebben `->step(0.01)` zodat de admin "12.5" of "7.25" kan invullen. Model-casts bijgewerkt naar `decimal:2`. `PopupVariant::resolvedDiscountPercentage()` returnt nu `float` ipv `int`. Vereist `dashed-ecommerce-core` v4.8.2+ (waar `dashed__discount_codes.discount_percentage` ook naar decimal is gemigreerd).
- Nieuwe public property `PopupFollowUpMail::$previewDiscountValue` (naast `$previewDiscountCode`) als override voor preview-context. De test-mail-actie zet deze op `10%` zodat de admin het volledige discount-block in de testmail correct ziet renderen zonder echte popup-conversie aan de mail te koppelen.
- **Website-link** wordt nu meegegeven aan de unified `dashed-core::emails.layout` (vereist `dashed-core` v4.3.3+): popup follow-up emails krijgen daardoor automatisch dezelfde clickable header-logo, "Bezoek site" CTA-button boven de footer en domein-link in de footer als alle andere systeem-mails. URL komt uit `Customsetting::get('site_url')` met fallback naar `config('app.url')`.
- Em-dashes (U+2014) verwijderd uit alle source-bestanden, blade-templates en CHANGELOG-entries van deze package; vervangen door gewone hyphen-minus per project-conventie.

### Fixed
- `PopupFollowUpMail` constructor gebruikte property-promotion `public readonly ?string $locale` die botste met `Illuminate\Mail\Mailable::$locale` (untyped public). Render of send via deze mailable crashte met `Unknown Property: Did you mean Illuminate\Mail\Mailable::$locale?`. Het locale-argument is nu een gewone parameter die in de constructor-body via `$this->locale = $locale` op de parent-property wordt gezet - read-paden in `build()` blijven werken via de bestaande `$this->locale` referentie.

## v4.13.4 - 2026-05-03

### Fixed
- `PopupFollowUpFlowResource` Repeater (`emails`) heeft nu een `mutateRelationshipDataBeforeFillUsing` callback die `subject` en `blocks` per locale uitpakt voordat het Filament-form gevuld wordt. Spatie's `attributesToArray()` retourneert translatable JSON-kolommen als `{nl: ..., en: ...}` arrays - Filament's Builder kreeg dus de hele locale-wrapped structuur als state, wikkelde die in een nieuwe UUID, en `getBlockPickerBlocks()` crashte op `Undefined array key "type"` omdat de top-level state geen block-items maar locale-keys bevatte. Nu wordt `data['blocks']` voor het invullen omgezet naar een platte list (`array_values($value[$locale] ?? [])`) en `data['subject']` naar de string voor de huidige locale. Dehydration blijft via `array_is_list` → `setTranslation` (huidige locale) op model-niveau, dus saven werkt onveranderd. **Workflow-blocker** opgelost: alle popup-follow-up-flows kunnen nu zonder crash bewerkt worden, ongeacht via `createDefault()` of via de UI aangemaakt.

## v4.13.3 - 2026-05-03

### Fixed
- `PopupFollowUpFlow::createDefault()` storede blocks als indexed array; Filament's Builder verwacht een UUID-keyed object (zoals het zelf zou serialiseren bij UI-create). Bij openen van de seeded flow crashte Filament's getBlockPickerBlocks omdat de iteratie over de wrapped translatable structuur items zonder `type` key aantrof. Blocks nu opgebouwd met UUID-keys via `Str::uuid()`, en gestored via `setTranslation()` voor expliciete locale-wrapping.

## v4.13.2 - 2026-05-03

### Fixed
- `PopupFollowUpFlow::createDefault()` gebruikte `'discount-highlight'` als block-type in de geseede emails. Dat block-type bestaat alleen in de popup-form (`PopupBlockRegistry`), niet in de follow-up flow email-form (waar het block `'discount'` heet). Filament's Builder crashte bij openen van de seeded flow met `Undefined array key "type"` omdat het block-type niet in de geregistreerde blocks zat. Vervangen door `'discount'` met de juiste data-structuur (`label` + `code`).

## v4.13.1 - 2026-05-03

### Added
- **Knop "Maak standaard flow aan"** op `/admin/popup-follow-up-flows` - mirror van het abandoned-cart-flow patroon. Maakt een complete `PopupFollowUpFlow` aan met 3 zinnige opvolg-stappen (1 uur, 24 uur, 72 uur na conversie), elk met een paragraph-block + discount-highlight-block, en zet de flow direct op `is_active` + `is_default`.
- Nieuwe statische `PopupFollowUpFlow::createDefault()` method die de seed uitvoert.

## v4.13.0 - 2026-05-03

### Added
- **`is_active` toggle op PopupFollowUpFlow** - mirror van het abandoned-cart-flow patroon. Slechts één flow tegelijk kan actief zijn (model-`saved`-hook zet anderen automatisch op inactive bij save). Filament-form heeft een Toggle, list-table heeft een IconColumn.
- Migration `add_is_active_to_popup_follow_up_flows` voegt de boolean kolom toe (default `true`) en zet bestaande rijen op `is_active=true` zodat upgrade niets breekt.
- `Popup::resolveFollowUpFlow()` retourneert alleen flows met `is_active=true` (zowel voor expliciete `follow_up_flow_id` op de popup als de globale `default()`).
- Popup-form select toont alleen actieve flows als keuze; placeholder is bijgewerkt naar "actieve standaard".
- `BackfillPopupFollowUpFlowService::run()` schiet niets in als de flow `is_active=false`.

### Changed
- `PopupFollowUpFlow::default()` filtert nu ook op `is_active=true` - een inactieve flow kan nooit als default fungeren, ook niet als `is_default=true`.

## v4.12.2 - 2026-05-03

### Fixed
- `PopupFollowUpFlowResource` was niet geregistreerd in `DashedPopupsPlugin::register()`. Daardoor was de admin-route `/admin/popup-follow-up-flows` niet bereikbaar en konden gebruikers helemaal geen follow-up flow aanmaken - wat ervoor zorgde dat de Popup-form de "Standaard flow gebruiken (indien ingesteld)" placeholder toonde maar er nooit een default flow bestond. Resource nu in de plugin toegevoegd.

## v4.12.1 - 2026-05-03

### Added
- **Backfill-knop op de Popup zelf** (in aanvulling op de flow-niveau actie van v4.12.0). De Popup edit-pagina toont nu de actie "Follow-up flow toepassen op bestaande inzendingen" die alleen de inzendingen van DEZE popup in de bijbehorende flow zet. Handig wanneer je per popup wil backfillen ipv flow-breed. Visible alleen als de popup een resolveFollowUpFlow() heeft.
- `BackfillPopupFollowUpFlowService::run()` heeft een optionele `$onlyPopupId` parameter waarmee de backfill gescoped wordt op één specifieke popup ipv alle popups die de flow gebruiken.

## v4.12.0 - 2026-05-03

### Added
- **Backfill-knop op PopupFollowUpFlow.** Op de bewerk-pagina van een follow-up flow staat nu de actie "Toepassen op bestaande" die de emails van de flow alsnog plant voor `PopupView`-records waar de bezoeker al een email heeft ingevuld (`submitted_at` gevuld) maar nog niet in een follow-up flow zit. Configureerbaar venster (1–365 dagen, default 30). Skipt views die al gestart, geannuleerd of zonder email zijn, en views waarvan de popup een ander resolveFollowUpFlow() teruggeeft.
- Nieuwe service `Services\BackfillPopupFollowUpFlowService` met statistics-array (`views_started`, `views_skipped_*`, `emails_dispatched`).
- `PopupView::followUpStatus()` retourneert: `not_in_flow`, `cancelled`, `finished` of `step_X_of_Y` op basis van `follow_up_started_at`, `follow_up_cancelled_at` en de `send_after_minutes` van de actieve emails. Geeft inzicht waar elke bezoeker zit in de flow zonder extra log-tabel.
- Nieuwe kolom in de Conversions-tabel (op de Popup edit-pagina): "Follow-up flow" badge met de huidige status - geannuleerd, niet in flow, afgerond, of "Stap X van Y".

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

<?php

declare(strict_types=1);

namespace Dashed\DashedPopups\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedPopups\Models\Popup;
use Dashed\DashedPopups\Models\PopupTarget;
use Dashed\DashedPopups\Models\PopupFollowUpFlow;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Concerns\SyncsPopupTargets;

/**
 * Native popups-beheer voor de mobiele app: volledige CRUD-pariteit met de
 * Filament PopupResource (type/inhoud-blokken/korting/trigger/display/targeting/
 * nieuwsbrief-koppeling/follow-up). Targeting + korting-pivots worden via
 * dezelfde logica als de CMS weggeschreven (SyncsPopupTargets + sync()).
 */
class PopupController extends Controller
{
    use SyncsPopupTargets;

    /** Kolommen die 1-op-1 op de popup gezet mogen worden (behalve translatables + targeting/pivots). */
    private const FILLABLE = [
        'name', 'type', 'active', 'trigger_type', 'trigger_value', 'show_again_after',
        'notify_on_conversion', 'start_date', 'end_date', 'visibility_mode',
        'discount_type', 'discount_percentage', 'discount_amount', 'discount_valid_days',
        'discount_usage_limit', 'auto_apply_discount', 'minimal_requirements',
        'minimum_products_count', 'minimum_amount', 'valid_for', 'follow_up_flow_id',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Popup::query()->with(['discountProducts:id', 'discountCategories:id']);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where('name', 'like', "%{$search}%");
        }
        if (($active = $request->query('active')) !== null && $active !== '') {
            $query->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);
        $popups = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => collect($popups->items())->map(fn (Popup $p) => $this->listRow($p))->all(),
            'meta' => [
                'current_page' => $popups->currentPage(),
                'last_page' => $popups->lastPage(),
                'total' => $popups->total(),
            ],
        ]);
    }

    public function show(int $popup): JsonResponse
    {
        return response()->json(['data' => $this->detail($this->find($popup))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $popup = new Popup();
        $this->write($popup, $data);

        return response()->json(['data' => $this->detail($popup->fresh())], 201);
    }

    public function update(Request $request, int $popup): JsonResponse
    {
        $model = $this->find($popup);
        $data = $this->validatePayload($request, $model);
        $this->write($model, $data);

        return response()->json(['data' => $this->detail($model->fresh())]);
    }

    public function destroy(int $popup): JsonResponse
    {
        $this->find($popup)->delete();

        return response()->json(['success' => true]);
    }

    public function toggleActive(int $popup): JsonResponse
    {
        $model = $this->find($popup);
        $model->active = ! $model->active;
        $model->save();

        return response()->json(['data' => $this->detail($model->fresh())]);
    }

    public function duplicate(int $popup): JsonResponse
    {
        $model = $this->find($popup);

        $copy = $model->replicate(['cached_views_count', 'cached_submits_count', 'cached_dismissals_count', 'cached_in_flow_count', 'cached_views_30d', 'cached_submits_30d', 'cached_dismissals_30d', 'cached_bounces_30d', 'cached_revenue_30d', 'stats_recalculated_at', 'ai_analysis', 'ai_analyzed_at']);
        $copy->active = false;
        $copy->name = $this->uniqueName($model->name . ' (kopie)');
        $copy->save();

        // Pivots + targeting mee-kopiëren.
        $copy->discountProducts()->sync($model->discountProducts()->pluck('id')->all());
        $copy->discountCategories()->sync($model->discountCategories()->pluck('id')->all());
        foreach ($model->targets as $target) {
            $copy->targets()->create($target->only(['rule_type', 'match_type', 'pattern', 'targetable_type', 'targetable_id', 'recommendation_strategy_slug']));
        }

        return response()->json(['data' => $this->detail($copy->fresh())], 201);
    }

    /** Keuzelijsten voor de editor (enums + dynamische bronnen). */
    public function options(Request $request): JsonResponse
    {
        $routeModels = [];
        foreach ((array) (cms()->builder('routeModels') ?? []) as $key => $rm) {
            $routeModels[] = ['key' => $key, 'label' => $rm['name'] ?? $key, 'class' => $rm['class'] ?? null];
        }

        $categories = \Dashed\DashedEcommerceCore\Models\ProductCategory::query()
            ->orderBy('name')->limit(500)->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => is_array($c->name) ? ($c->name[app()->getLocale()] ?? reset($c->name)) : $c->name])->all();

        $products = [];
        if ($ps = trim((string) $request->query('product_search', ''))) {
            $products = \Dashed\DashedEcommerceCore\Models\Product::query()
                ->where('name', 'like', "%{$ps}%")->limit(50)->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => is_array($p->name) ? ($p->name[app()->getLocale()] ?? reset($p->name)) : $p->name])->all();
        }

        $flows = PopupFollowUpFlow::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->all();

        return response()->json([
            'types' => [['value' => 'simple', 'label' => 'Simpel'], ['value' => 'discount', 'label' => 'Korting + email-capture']],
            'trigger_types' => [['value' => 'delay', 'label' => 'Tijdsvertraging'], ['value' => 'scroll', 'label' => 'Scroll-diepte'], ['value' => 'exit_intent', 'label' => 'Exit-intent']],
            'visibility_modes' => [['value' => 'everywhere', 'label' => 'Overal'], ['value' => 'only_selection', 'label' => 'Alleen op de selectie']],
            'discount_types' => [['value' => 'percentage', 'label' => 'Percentage'], ['value' => 'amount', 'label' => 'Vast bedrag']],
            'minimal_requirements' => [['value' => null, 'label' => 'Geen'], ['value' => 'products', 'label' => 'Minimaal aantal producten'], ['value' => 'amount', 'label' => 'Minimaal aankoopbedrag']],
            'valid_for' => [['value' => 'all', 'label' => 'Alle producten'], ['value' => 'products', 'label' => 'Specifieke producten'], ['value' => 'categories', 'label' => 'Specifieke categorieën']],
            'block_types' => [
                ['value' => 'heading', 'label' => 'Kop'],
                ['value' => 'paragraph', 'label' => 'Paragraaf'],
                ['value' => 'image', 'label' => 'Afbeelding'],
                ['value' => 'usp_list', 'label' => 'USP-lijst'],
                ['value' => 'discount_highlight', 'label' => 'Kortings-highlight'],
            ],
            'route_models' => $routeModels,
            'categories' => $categories,
            'products' => $products,
            'follow_up_flows' => $flows,
        ]);
    }

    // ── Schrijven ────────────────────────────────────────────────────────────

    private function write(Popup $popup, array $data): void
    {
        foreach (self::FILLABLE as $col) {
            if (array_key_exists($col, $data)) {
                $popup->{$col} = $data[$col];
            }
        }
        if (array_key_exists('title', $data)) {
            $popup->title = $data['title'];
        }
        if (array_key_exists('blocks', $data)) {
            $popup->blocks = $data['blocks'] ?? [];
        }
        if (array_key_exists('api_subscriptions', $data)) {
            $popup->api_subscriptions = $data['api_subscriptions'] ?? [];
        }
        $popup->save();

        if (array_key_exists('discount_product_ids', $data)) {
            $popup->discountProducts()->sync($data['discount_product_ids'] ?? []);
        }
        if (array_key_exists('discount_category_ids', $data)) {
            $popup->discountCategories()->sync($data['discount_category_ids'] ?? []);
        }
        if (array_key_exists('targeting', $data)) {
            $this->syncPopupTargets($popup, $this->targetingToSyncData($data['targeting'] ?? []));
        }
    }

    /** Zet de API-targeting-vorm om naar de sleutels die SyncsPopupTargets verwacht. */
    private function targetingToSyncData(array $targeting): array
    {
        $out = [
            'include_url_patterns' => array_values(array_filter((array) ($targeting['include_url_patterns'] ?? []))),
            'exclude_url_patterns' => array_values(array_filter((array) ($targeting['exclude_url_patterns'] ?? []))),
            'recommendation_strategy_slug' => $targeting['recommendation_strategy_slug'] ?? null,
        ];

        foreach ((array) ($targeting['models'] ?? []) as $key => $model) {
            foreach (['include', 'exclude'] as $rule) {
                $spec = $model[$rule] ?? [];
                $out["target_mode_{$rule}_{$key}"] = $spec['mode'] ?? 'none';
                $out["target_ids_{$rule}_{$key}"] = array_values((array) ($spec['ids'] ?? []));
            }
        }

        return $out;
    }

    // ── Payload-vormers ──────────────────────────────────────────────────────

    private function listRow(Popup $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'type' => $p->type,
            'active' => (bool) $p->active,
            'title' => $p->title,
            'start_date' => optional($p->start_date)->toIso8601String(),
            'end_date' => optional($p->end_date)->toIso8601String(),
            'stats' => $this->stats($p),
        ];
    }

    private function detail(Popup $p): array
    {
        return array_merge($this->listRow($p), [
            'blocks' => $p->blocks ?? [],
            'trigger_type' => $p->trigger_type,
            'trigger_value' => $p->trigger_value !== null ? (int) $p->trigger_value : null,
            'show_again_after' => $p->show_again_after !== null ? (int) $p->show_again_after : null,
            'notify_on_conversion' => (bool) $p->notify_on_conversion,
            'visibility_mode' => $p->visibility_mode,
            'discount' => [
                'discount_type' => $p->discount_type,
                'discount_percentage' => $p->discount_percentage !== null ? (float) $p->discount_percentage : null,
                'discount_amount' => $p->discount_amount !== null ? (float) $p->discount_amount : null,
                'discount_valid_days' => $p->discount_valid_days !== null ? (int) $p->discount_valid_days : null,
                'discount_usage_limit' => $p->discount_usage_limit !== null ? (int) $p->discount_usage_limit : null,
                'auto_apply_discount' => (bool) $p->auto_apply_discount,
                'minimal_requirements' => $p->minimal_requirements,
                'minimum_products_count' => $p->minimum_products_count !== null ? (int) $p->minimum_products_count : null,
                'minimum_amount' => $p->minimum_amount !== null ? (float) $p->minimum_amount : null,
                'valid_for' => $p->valid_for,
                'discount_product_ids' => $p->discountProducts()->pluck('id')->all(),
                'discount_category_ids' => $p->discountCategories()->pluck('id')->all(),
            ],
            'api_subscriptions' => $p->api_subscriptions ?? [],
            'follow_up_flow_id' => $p->follow_up_flow_id,
            'targeting' => $this->readTargeting($p),
        ]);
    }

    private function stats(Popup $p): array
    {
        return [
            'views' => (int) $p->cached_views_count,
            'submits' => (int) $p->cached_submits_count,
            'dismissals' => (int) $p->cached_dismissals_count,
            'in_flow' => (int) $p->cached_in_flow_count,
            'revenue_30d' => (float) $p->cached_revenue_30d,
            'views_30d' => (int) $p->cached_views_30d,
            'submits_30d' => (int) $p->cached_submits_30d,
        ];
    }

    private function readTargeting(Popup $p): array
    {
        $targets = $p->targets;
        $patterns = fn (string $rule) => $targets
            ->where('rule_type', $rule)->where('match_type', 'url_pattern')
            ->pluck('pattern')->values()->all();

        $models = [];
        foreach ((array) (cms()->builder('routeModels') ?? []) as $key => $rm) {
            $class = $rm['class'] ?? null;
            if (! $class) {
                continue;
            }
            $entry = [];
            foreach (['include', 'exclude'] as $rule) {
                $rows = $targets->where('rule_type', $rule)->where('targetable_type', $class);
                if ($rows->firstWhere('match_type', 'all_of_type')) {
                    $entry[$rule] = ['mode' => 'all', 'ids' => []];
                } elseif ($rows->where('match_type', 'specific_model')->isNotEmpty()) {
                    $entry[$rule] = ['mode' => 'selected', 'ids' => $rows->where('match_type', 'specific_model')->pluck('targetable_id')->map(fn ($id) => (int) $id)->values()->all()];
                } else {
                    $entry[$rule] = ['mode' => 'none', 'ids' => []];
                }
            }
            $models[$key] = $entry;
        }

        $strategy = $targets->firstWhere('match_type', PopupTarget::MATCH_RECOMMENDATION_STRATEGY);

        return [
            'include_url_patterns' => $patterns('include'),
            'exclude_url_patterns' => $patterns('exclude'),
            'models' => $models,
            'recommendation_strategy_slug' => $strategy?->recommendation_strategy_slug,
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function validatePayload(Request $request, ?Popup $existing = null): array
    {
        return $request->validate([
            'name' => [$existing ? 'sometimes' : 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'in:simple,discount'],
            'active' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'nullable', 'string'],
            'blocks' => ['sometimes', 'nullable', 'array'],
            'trigger_type' => ['sometimes', 'nullable', 'in:delay,scroll,exit_intent'],
            'trigger_value' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'show_again_after' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notify_on_conversion' => ['sometimes', 'boolean'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'visibility_mode' => ['sometimes', 'nullable', 'in:everywhere,only_selection'],
            'discount_type' => ['sometimes', 'nullable', 'in:percentage,amount'],
            'discount_percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99.99'],
            'discount_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'discount_valid_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'discount_usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'auto_apply_discount' => ['sometimes', 'boolean'],
            'minimal_requirements' => ['sometimes', 'nullable', 'in:products,amount'],
            'minimum_products_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'minimum_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'valid_for' => ['sometimes', 'nullable', 'in:all,products,categories'],
            'discount_product_ids' => ['sometimes', 'array'],
            'discount_product_ids.*' => ['integer'],
            'discount_category_ids' => ['sometimes', 'array'],
            'discount_category_ids.*' => ['integer'],
            'api_subscriptions' => ['sometimes', 'nullable', 'array'],
            'follow_up_flow_id' => ['sometimes', 'nullable', 'integer'],
            'targeting' => ['sometimes', 'array'],
        ]);
    }

    private function find(int $id): Popup
    {
        return Popup::query()->findOrFail($id);
    }

    private function uniqueName(string $base): string
    {
        $name = $base;
        $i = 2;
        while (Popup::where('name', $name)->exists()) {
            $name = $base . ' ' . $i++;
        }

        return $name;
    }
}

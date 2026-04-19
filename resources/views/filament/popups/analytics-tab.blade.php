@php
    $metrics = $this->metrics();
    $status = $this->status();
    $ai = $this->popup->ai_analysis;
    $aiAt = $this->popup->ai_analyzed_at;
    $levels = ['good' => 'bg-green-100 text-green-700', 'warn' => 'bg-amber-100 text-amber-700', 'poor' => 'bg-red-100 text-red-700', 'insufficient_data' => 'bg-gray-100 text-gray-600'];
    $overallLabel = [
        'excellent' => 'Goed',
        'ok' => 'Voldoende',
        'mediocre' => 'Matig',
        'poor' => 'Slecht',
        'insufficient_data' => 'Weinig data',
    ];
    $pct = fn ($v) => number_format(((float) $v) * 100, 1) . '%';
@endphp

<div class="space-y-6" wire:poll.30s>
    <div class="flex items-center justify-between">
        <div class="flex gap-3 items-center">
            <label class="text-sm text-gray-600">Periode:</label>
            <select wire:model.live="period" class="text-sm rounded border-gray-300">
                <option value="1">Vandaag</option>
                <option value="7">Laatste 7 dagen</option>
                <option value="30">Laatste 30 dagen</option>
                <option value="90">Laatste 90 dagen</option>
            </select>
        </div>
        <div>
            <span class="text-sm px-3 py-1 rounded-full font-semibold {{ $levels[$status['overall']] ?? '' }}">
                {{ $overallLabel[$status['overall']] ?? $status['overall'] }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach ([
            ['Views', $metrics['views'], null, null],
            ['Submits', $metrics['submits'], 'conversion_rate', $pct($metrics['conversion_rate'])],
            ['Dismissals', $metrics['dismissals'], 'dismissal_rate', $pct($metrics['dismissal_rate'])],
            ['Bounces (<2s)', $metrics['bounces'], 'bounce_rate', $pct($metrics['bounce_rate'])],
        ] as [$label, $n, $mKey, $ratio])
            <div class="p-4 rounded border border-gray-200">
                <div class="text-xs uppercase text-gray-500">{{ $label }}</div>
                <div class="text-2xl font-semibold text-gray-900">{{ $n }}</div>
                @if ($mKey && isset($status['per_metric'][$mKey]))
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-xs px-2 py-0.5 rounded {{ $levels[$status['per_metric'][$mKey]['level']] ?? '' }}">{{ $ratio }}</span>
                        <span class="text-xs text-gray-500" title="{{ $status['per_metric'][$mKey]['explanation'] }}">ⓘ</span>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="p-4 rounded border border-gray-200">
            <h3 class="font-semibold mb-2">Per device</h3>
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-500 text-left">
                    <tr><th class="py-1">Device</th><th>Views</th><th>Submits</th><th>Conversie</th></tr>
                </thead>
                <tbody>
                    @forelse ($metrics['by_device'] as $row)
                        <tr class="border-t">
                            <td class="py-1">{{ $row['key'] }}</td>
                            <td>{{ $row['views'] }}</td>
                            <td>{{ $row['submits'] }}</td>
                            <td>{{ $pct($row['conversion_rate']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-2 text-gray-500">Geen data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 rounded border border-gray-200">
            <h3 class="font-semibold mb-2">Per trigger</h3>
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-500 text-left">
                    <tr><th class="py-1">Trigger</th><th>Views</th><th>Submits</th><th>Conversie</th></tr>
                </thead>
                <tbody>
                    @forelse ($metrics['by_trigger'] as $row)
                        <tr class="border-t">
                            <td class="py-1">{{ $row['key'] }}</td>
                            <td>{{ $row['views'] }}</td>
                            <td>{{ $row['submits'] }}</td>
                            <td>{{ $pct($row['conversion_rate']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-2 text-gray-500">Geen data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="p-4 rounded border border-gray-200">
            <h3 class="font-semibold mb-2">Top URLs</h3>
            <ul class="text-sm space-y-1">
                @forelse ($metrics['top_urls'] as $r)
                    <li class="flex justify-between border-t pt-1" title="{{ $r['value'] }}">
                        <span class="truncate max-w-[18rem]">{{ \Illuminate\Support\Str::limit($r['value'], 30) }}</span>
                        <span class="text-gray-500">{{ $r['views'] }} · {{ $pct($r['conversion_rate']) }}</span>
                    </li>
                @empty
                    <li class="text-gray-500">Geen data</li>
                @endforelse
            </ul>
        </div>
        <div class="p-4 rounded border border-gray-200">
            <h3 class="font-semibold mb-2">Top referrers</h3>
            <ul class="text-sm space-y-1">
                @forelse ($metrics['top_referrers'] as $r)
                    <li class="flex justify-between border-t pt-1" title="{{ $r['value'] }}">
                        <span class="truncate max-w-[18rem]">{{ \Illuminate\Support\Str::limit($r['value'], 30) }}</span>
                        <span class="text-gray-500">{{ $r['views'] }} · {{ $pct($r['conversion_rate']) }}</span>
                    </li>
                @empty
                    <li class="text-gray-500">Geen data</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="p-4 rounded border border-gray-200">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold">AI-analyse</h3>
            @if ($this->aiAvailable())
                <button type="button" wire:click="requestAiAnalysis" wire:loading.attr="disabled"
                        class="text-sm px-3 py-1 rounded bg-primary-600 text-white hover:bg-primary-700 disabled:opacity-50">
                    {{ $ai ? 'Ververs analyse' : 'Vraag AI om analyse' }}
                </button>
            @else
                <span class="text-xs text-gray-500">Geen AI-provider geconfigureerd</span>
            @endif
        </div>

        @if ($aiError)
            <div class="mt-2 text-sm text-red-600">{{ $aiError }}</div>
        @endif

        @if ($ai)
            <p class="mt-3 text-gray-800">{{ $ai['overall_verdict'] ?? '' }}</p>

            @if (! empty($ai['strengths']))
                <div class="mt-3">
                    <div class="text-xs uppercase text-gray-500">Sterke punten</div>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach ($ai['strengths'] as $s)
                            <span class="px-2 py-0.5 rounded bg-green-100 text-green-700 text-xs">{{ $s }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (! empty($ai['concerns']))
                <div class="mt-3">
                    <div class="text-xs uppercase text-gray-500">Zorgen</div>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach ($ai['concerns'] as $c)
                            <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-700 text-xs">{{ $c }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (! empty($ai['recommendations']))
                <div class="mt-3">
                    <div class="text-xs uppercase text-gray-500">Aanbevelingen</div>
                    <ol class="list-decimal ml-5 mt-1 text-sm text-gray-800 space-y-1">
                        @foreach ($ai['recommendations'] as $r)
                            <li>{{ $r }}</li>
                        @endforeach
                    </ol>
                </div>
            @endif

            <div class="mt-3 text-xs text-gray-500">
                Geanalyseerd op {{ optional($aiAt)->format('d-m-Y H:i') }}{{ isset($ai['provider']) ? ' door ' . $ai['provider'] : '' }}
            </div>
        @endif
    </div>
</div>

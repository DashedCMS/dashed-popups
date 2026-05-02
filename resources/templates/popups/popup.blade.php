@php
    $triggerType = $popup?->trigger_type ?? 'delay';
    $triggerValue = (int) ($popup?->trigger_value ?? 0);
    $delayMs = $triggerType === 'delay' ? $triggerValue * 1000 : 0;
    $isDiscount = $popup && method_exists($popup, 'isDiscountType') && $popup->isDiscountType();
@endphp

<div x-data="{
        showPopup: @entangle('showPopup'),
        show: false,
        triggered: false,
        init() {
            const self = this;
            const trigger = () => {
                if (self.triggered) return;
                self.triggered = true;
                self.show = true;
            };

            @if ($triggerType === 'scroll')
                const threshold = {{ $triggerValue }};
                window.addEventListener('scroll', () => {
                    const scrolled = (window.scrollY + window.innerHeight) / Math.max(document.body.scrollHeight, 1) * 100;
                    if (scrolled >= threshold) trigger();
                });
            @elseif ($triggerType === 'exit_intent')
                const isMobile = /Mobi|Android/i.test(navigator.userAgent);
                if (isMobile) {
                    setTimeout(trigger, 30000);
                } else {
                    document.addEventListener('mouseleave', (e) => {
                        if (e.clientY <= 0) trigger();
                    });
                }
            @else
                setTimeout(trigger, {{ $delayMs }});
            @endif
        }
     }"
     x-cloak
     x-show="show && showPopup"
     class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500/75 w-full h-full -z-1" aria-hidden="true"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        @if ($popup)
            <div class="inline-block z-50 align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6 relative">
                <button class="absolute right-2 top-2" wire:click="clickAway">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </button>

                @if ($isDiscount)
                    @if (! $showSuccess)
                        @if ($popup->title)
                            <h2 class="text-lg leading-6 font-bold text-gray-900 mb-3">{{ $popup->title }}</h2>
                        @endif

                        @if(is_array($popup->blocks))
                            @foreach ($popup->blocks ?? [] as $block)
                                @includeIf('dashed-popups::blocks.' . str_replace('_', '-', $block['type'] ?? ''), ['data' => $block['data'] ?? []])
                            @endforeach
                        @endif

                        <form wire:submit.prevent="submitEmail" class="mt-4 space-y-3">
                            <input type="email"
                                   wire:model="email"
                                   placeholder="Je e-mailadres"
                                   required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                            @error('email')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                            @enderror
                            <button type="submit" class="button button--primary transition w-full">
                                Claim mijn {{ $popup->discount_percentage }}% korting
                            </button>
                        </form>
                    @else
                        <div class="text-center py-6"
                             x-init="setTimeout(() => { $wire.call('clickAway'); show = false; }, 8000)">
                            <h2 class="text-xl font-bold text-gray-900 mb-2">Bedankt!</h2>
                            <p class="text-sm text-gray-600">Je kortingscode:</p>
                            <p class="text-2xl font-mono font-bold text-blue-600 my-2">{{ $discountCode }}</p>
                            <p class="text-sm text-gray-600">Is automatisch toegepast op je winkelmand.</p>
                        </div>
                    @endif
                @else
                    <div class="text-center">
                        @if (Translation::get('popup-title', 'popup-' . $popup->name, 'Popup titel'))
                            <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">
                                {{ Translation::get('popup-title', 'popup-' . $popup->name, 'Popup titel') }}
                            </h3>
                        @endif
                        <x-dashed-files::image
                                class="max-w-full mx-auto mt-2 h-full max-h-[700px] rounded-xl"
                                config="dashed"
                                :mediaId="Translation::get('popup-image', 'popup-' . $popup->name, '', 'image')"
                                alt=""
                                :manipulations="['widen' => 500]"
                        />
                        @if (strip_tags(cms()->convertToHtml(Translation::get('popup-description', 'popup-' . $popup->name, '', 'editor'))))
                            <div class="mt-2">
                                <div class="text-sm text-gray-500 content">
                                    {!! cms()->convertToHtml(Translation::get('popup-description', 'popup-' . $popup->name, '', 'editor')) !!}
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="mt-5 sm:mt-6">
                        @if (Translation::get('popup-button-primary', 'popup-' . $popup->name, ''))
                            <button
                                    wire:click="goTo"
                                    class="button button--primary transition w-full">
                                {!! Translation::get('popup-button-primary', 'popup-' . $popup->name, '') !!}
                            </button>
                        @endif
                    </div>

                    <script>
                        document.addEventListener('redirectToLogin', () => {
                            window.location('{{ Translation::get('redirect-url', 'popup-' . $popup->name, '') }}');
                        })
                    </script>
                @endif
            </div>
        @endif
    </div>
</div>

<div x-data="{ showPopup: @entangle('showPopup'), show: false }"
     x-init="setTimeout(() => show = true, {{ $popup->delay * 1000 }})"
     x-cloak
     x-show="show && showPopup"
     class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500/75 w-full h-full -z-1" aria-hidden="true"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        @if($popup)
            <div
                    x-data="{ showPopup: @entangle('showPopup'), show: false }"
                    x-init="setTimeout(() => show = true, {{ $popup->delay * 1000 }})"
                    x-show="show && showPopup"
                    class="inline-block z-50 align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div x-cloak>
                    <button class="absolute right-2 top-2" wire:click="clickAway">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </button>
                    <div class="text-center">
                        @if(Translation::get('popup-title', 'popup-' . $popup->name, 'Popup titel'))
                            <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">
                                {{ Translation::get('popup-title', 'popup-' . $popup->name, 'Popup titel') }}
                            </h3>
                        @endif
                        <x-dashed-files::image
                                class="w-24 lg:w-36 xl:w-48 rounded-xl"
                                config="dashed"
                                :mediaId="Translation::get('popup-image', 'popup-' . $popup->name, '', 'image')"
                                alt=""
                                :manipulations="[
                                                        'widen' => 500,
                                                    ]"
                        />
                        @if(strip_tags(cms()->convertToHtml(Translation::get('popup-description', 'popup-' . $popup->name, '', 'editor'))))
                            <div class="mt-2">
                                <div class="text-sm text-gray-500 content">
                                    {!! cms()->convertToHtml(Translation::get('popup-description', 'popup-' . $popup->name, '', 'editor')) !!}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="mt-5 sm:mt-6">
                    @if(Translation::get('popup-button-primary', 'popup-' . $popup->name, ''))
                        <button
                                wire:click="goTo"
                                class="button button--primary transition w-full">
                            {!! Translation::get('popup-button-primary', 'popup-' . $popup->name, '') !!}
                        </button>
                    @endif
                </div>

                <script>

                    document.addEventListener('redirectToLogin', () => {
                        window.open('{{ Translation::get('redirect-url', 'popup-' . $popup->name, '') }}');
                    })
                </script>
            </div>
        @endif
    </div>
</div>

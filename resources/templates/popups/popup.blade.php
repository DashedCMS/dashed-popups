<<<<<<< HEAD
<div x-data="{ showPopup: @entangle('showPopup'), show: false }" x-init="setTimeout(() => show = true, {{ $popup->delay * 1000 }})"
     x-show="show && showPopup">
    <div class="fixed z-50 w-screen bottom-0 left-0 bg-linear-to-tr from-primary-900 to-primary-900 "
         aria-labelledby="modal-title" role="dialog"
         x-cloak
         aria-modal="true">
        {{--            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>--}}
=======
<div>
    @if($popup)
        <div x-data="{ showPopup: @entangle('showPopup'), show: false }"
             x-init="setTimeout(() => show = true, {{ $popup->delay * 1000 }})"
             x-show="show && showPopup">
            <div class="fixed z-[50] w-[100vw] bottom-0 left-0 bg-gradient-to-tr from-primary-900 to-primary-900 "
                 aria-labelledby="modal-title" role="dialog"
                 x-cloak
                 aria-modal="true">
                {{--            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>--}}
>>>>>>> 1ad178eab92d6d3872b54d73b2982933155d5eb7

                {{--            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>--}}


                <div class="py-4">
                    <button wire:click="clickAway"
                            class="absolute right-4 bottom-8 lg:top-8 rounded-full bg-white p-2 text-primary-500 shadow-2xl">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                    <div class="text-center grid lg:flex lg:items-center lg:justify-between gap-4">
                        <div class="absolute left-[25px] lg:left-[50px] xl:left-[100px] -bottom-4 lg:bottom-0">
                            @if(request()->has('love'))
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                     class="w-[200px] text-red-500 animate-ping">
                                    <path
                                            d="m11.645 20.91-.007-.003-.022-.012a15.247 15.247 0 0 1-.383-.218 25.18 25.18 0 0 1-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0 1 12 5.052 5.5 5.5 0 0 1 16.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 0 1-4.244 3.17 15.247 15.247 0 0 1-.383.219l-.022.012-.007.004-.003.001a.752.752 0 0 1-.704 0l-.003-.001Z"/>
                                </svg>
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
                        </div>
                        <div
                                class="mx-auto lg:ml-[250px] xl:ml-[350px] text-md lg:text-lg xl:text-xl text-white font-bold max-w-[600px] popup-description">
                            {!! Translation::get('popup-description', 'popup', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industrys standard dummy text ever since the 1500s', 'text', [
            'phoneNumber' => Customsetting::get('company_phone_number'),
            ]) !!}
                        </div>
                        <div class="lg:mr-[150px] flex flex-col gap-2">
                            <div class="flex gap-2 items-center justify-center">
                                <x-dashed-files::image
                                        class="w-12 rounded-xl"
                                        config="dashed"
                                        :mediaId="Translation::get('review-image', 'popup-' . $popup->name, '', 'image')"
                                        alt=""
                                        :manipulations="[
                                    'widen' => 500,
                                ]"
                                />
                                <div class="flex gap-1 items-center justify-center">
                                    @for($i = 5; $i > 0; $i--)
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                             class="size-8 text-yellow-500">
                                            <path fill-rule="evenodd"
                                                  d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                    @endfor
                                </div>
                            </div>

                            <p class="text-white text-xs xl:text-lg"><span
                                        class="font-bold text-primary-300">{{ Customsetting::get('google_maps_rating') }}</span>
                                op
                                basis van <span
                                        class="font-bold text-primary-300">{{ Customsetting::get('google_maps_review_count') }}</span>
                                reviews</p>
                        </div>
                    </div>
                </div>

                <script>
                    document.addEventListener('redirectTo', () => {
                        window.open('{{ Translation::get('redirect-url', 'popup-' . $popup->name, '') }}', '_blank').focus();
                    })
                </script>
            </div>
            <div class="h-[100px]"></div>
        </div>
    @endif
</div>

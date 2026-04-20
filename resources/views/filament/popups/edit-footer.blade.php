<section class="mt-8 space-y-4">
    <header class="flex items-center justify-between border-b border-gray-950/5 pb-3 dark:border-white/10">
        <div>
            <h2 class="text-lg font-semibold leading-6 text-gray-950 dark:text-white">
                Analytics
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Prestaties, ROI en AI-analyse voor deze popup.
            </p>
        </div>
    </header>

    @livewire('dashed-popups.admin.popup-analytics-panel', ['popup' => $record])
</section>

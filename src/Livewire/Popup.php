<?php

namespace Dashed\DashedPopups\Livewire;

use Livewire\Component;

class Popup extends Component
{
    public ?\Dashed\DashedPopups\Models\Popup $popup = null;
    public ?\Dashed\DashedPopups\Models\PopupView $popupView = null;
    public $showPopup = false;

    public function mount(string|int $popupId)
    {
        $this->popup = \Dashed\DashedPopups\Models\Popup::where('name', $popupId)->orWhere('id', $popupId)->first();
        if (! $this->popup) {
            return;
        }

        $popupView = $this->popup->views()->where('session_id', session()->getId())->first();
        if (! $popupView) {
            $popupView = $this->popup->views()->create([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'session_id' => session()->getId(),
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'seen_count' => 0,
            ]);
            $this->showPopup = $this->popup->start_date <= now() && $this->popup->end_date >= now();
        } else {
            $this->showPopup = $this->popup->start_date <= now() && $this->popup->end_date >= now() && (! $popupView->closed_at || $popupView->closed_at < now()->subMinutes($this->popup->show_again_after));
        }
        $this->popupView = $popupView;
        if ($this->showPopup) {
            $this->popupView->seen_count++;
            $this->popupView->last_seen_at = now();
            $this->popupView->save();
        }
    }

    public function goTo()
    {
        $this->popupView->closed_at = now();
        $this->popupView->save();
        $this->showPopup = false;
        $this->dispatch('redirectTo');
    }

    public function clickAway(): void
    {
        $this->popupView->closed_at = now();
        $this->popupView->save();
        $this->showPopup = false;
    }

    public function render()
    {
        if (view()->exists('dashed.popups.' . str($this->popup->name)->slug() . '-popup')) {
            return view(config('dashed-core.site_theme') . '.popups.' . str($this->popup->name)->slug() . '-popup');
        } else {
            return view(config('dashed-core.site_theme') . '.popups.popup');
        }
    }
}

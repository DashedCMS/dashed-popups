<?php

namespace Dashed\DashedPopups\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedPopups\Models\PopupView;

class PopupFollowUpUnsubscribeController extends Controller
{
    public function unsubscribe(Request $request, int $view)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $popupView = PopupView::find($view);
        if (! $popupView) {
            abort(404);
        }

        $alreadyCancelled = $popupView->follow_up_cancelled_at !== null;
        if (! $alreadyCancelled) {
            $popupView->update(['follow_up_cancelled_at' => now()]);
        }

        $siteName = class_exists(\Dashed\DashedCore\Models\Customsetting::class)
            ? \Dashed\DashedCore\Models\Customsetting::get('site_name')
            : config('app.name');

        return response()->view('dashed-popups::emails.unsubscribe-confirmation', [
            'siteName' => $siteName,
            'alreadyCancelled' => $alreadyCancelled,
        ]);
    }
}

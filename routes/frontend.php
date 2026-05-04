<?php

use Illuminate\Support\Facades\Route;
use Dashed\DashedPopups\Controllers\PopupFollowUpUnsubscribeController;

Route::get('/popup-follow-up/unsubscribe/{view}', [PopupFollowUpUnsubscribeController::class, 'unsubscribe'])
    ->name('dashed.frontend.popup-follow-up.unsubscribe')
    ->middleware('signed');

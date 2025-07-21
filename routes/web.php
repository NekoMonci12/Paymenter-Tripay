<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Gateways\Tripay\Tripay;

Route::post('/extensions/tripay/webhook', [Tripay::class, 'webhook'])->name('extensions.gateways.tripay.webhook');

<?php

use Illuminate\Support\Facades\Route;
use IslamDB\OrchidSetting\Screens\SettingScreen;
use Tabuna\Breadcrumbs\Trail;

Route::screen('/setting', SettingScreen::class)
    ->name('setting')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push(config('orchidsetting.name'));
    });

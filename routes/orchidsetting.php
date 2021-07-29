<?php

use Illuminate\Support\Facades\Route;
use IslamDB\OrchidSetting\Screens\SettingListScreen;
use IslamDB\OrchidSetting\Screens\SettingEditScreen;
use Tabuna\Breadcrumbs\Trail;

Route::screen('/setting', SettingListScreen::class)
    ->name('setting')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push(config('orchidsetting.name'));
    });

Route::screen('/setting/create', SettingEditScreen::class)
    ->name('setting.create')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('setting')
            ->push(__('Create'));
    });

Route::screen('/setting/{setting}/edit', SettingEditScreen::class)
    ->name('setting.edit')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('setting')
            ->push(__('Edit'));
    });

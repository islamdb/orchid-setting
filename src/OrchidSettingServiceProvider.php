<?php

namespace IslamDB\OrchidSetting;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Screen\Actions\Menu;

class OrchidSettingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'../config/orchidsetting.php',
            'orchidsetting'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(Dashboard $dashboard)
    {
        $dashboard->registerPermissions(
            ItemPermission::group(__('Setting'))
                ->addPermission('setting.browse', __('Browse'))
                ->addPermission('setting.edit', __('Edit'))
                ->addPermission('setting.properties', __('Properties'))
                ->addPermission('setting.add', __('Add'))
                ->addPermission('setting.order', __('Order'))
                ->addPermission('setting.delete', __('Delete'))
        );

        \Orchid\Support\Facades\Dashboard::registerMenuElement(
            Dashboard::MENU_MAIN,
            Menu::make(config('orchidsetting.name'))
                ->icon('settings')
                ->route('setting')
                ->permission('setting.browse')
                ->sort(config('orchidsetting.menu_sort'))
                ->title(config('orchidsetting.menu_title'))
        );

        $this->publishes([
            __DIR__.'/../config/orchidsetting.php' => config_path('orchidsetting.php'),
            __DIR__.'/../database/migrations/2021_06_30_111633_create_settings_table.php' => database_path('migrations/2021_06_30_111633_create_settings_table.php')
        ]);

        Route::domain((string)config('platform.domain'))
            ->prefix(Dashboard::prefix('/'))
            ->as('platform.')
            ->middleware(config('platform.middleware.private'))
            ->group(__DIR__ . '/../routes/orchidsetting.php');
    }
}

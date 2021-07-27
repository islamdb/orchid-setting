<?php

namespace IslamDB\OrchidSetting\Layouts;

use IslamDB\OrchidHelper\Field;
use IslamDB\OrchidSetting\Models\Setting;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Matrix;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Listener;
use Orchid\Support\Facades\Layout;

class SettingFormListenerLayout extends Listener
{
    /**
     * List of field names for which values will be listened.
     *
     * @var string[]
     */
    protected $targets = [
        'key',
        'group',
        'name',
        'type',
        'description',
        'old_key'
    ];

    /**
     * What screen method should be called
     * as a source for an asynchronous request.
     *
     * The name of the method must
     * begin with the prefix "async"
     *
     * @var string
     */
    protected $asyncMethod = 'asyncField';

    /**
     * @return Layout[]
     */
    protected function layouts(): array
    {
        $setting = Setting::query()->find(request()->setting);
        $key = $setting->key ?? null;
        $name = $setting->name ?? null;
        $group = $setting->group ?? null;
        $type = $setting->type ?? null;
        $description = $setting->description ?? null;
        $options = $setting->options ?? null;
        $options = is_null($options)
            ? []
            : json_decode($options, true);
        $oldKey = null;
        if (!empty($setting)) {
            $oldKey = $setting->key;
        } else if (request()->has('old_key')) {
            $oldKey = request()->old_key;
        }

        return [
            Layout::columns([
                Layout::rows([
                    Input::make('key')
                        ->title('Key')
                        ->value($key)
                        ->readonly(!empty($oldKey))
                        ->required(),
                    Input::make('name')
                        ->title('Name')
                        ->value($name)
                        ->required()
                ]),
                Layout::rows([
                    Input::make('group')
                        ->title('Group')
                        ->value($group)
                        ->required(),
                    Select::make('type')
                        ->title('Type')
                        ->value($type)
                        ->options(Setting::options())
                        ->empty()
                        ->required()
                ])
            ]),
            Layout::rows([
                TextArea::make('description')
                    ->value($description)
                    ->title('Description'),
                Matrix::make('options')
                    ->title('Options')
                    ->value($options)
                    ->columns([
                        __('Active') => 'active',
                        __('Name') => 'name',
                        __('Parameter') => 'param',
                        __('Info') => 'full'
                    ])
                    ->fields([
                        'active' => CheckBox::make()
                            ->style('margin-left: 8px')
                            ->sendTrueOrFalse(),
                        'name' => Input::make(),
                        'param' => TextArea::make()->style('font-family: "Courier New", monospace;'),
                        'full' => Input::make()->style('font-family: "Courier New", monospace;')
                    ]),
                Input::make('old_key')
                    ->type('hidden')
                    ->value($oldKey)
            ])
        ];
    }

    /**
     * @param null $key
     * @param null $group
     * @param null $name
     * @param null $type
     * @param null $description
     * @param null $exists
     * @return array
     */
    public static function process($key = null, $group = null, $name = null, $type = null, $description = null, $exists = null)
    {
        if (!empty($key)) {
            $key = str_replace('.', '_', $key);
        }

        $options = Field::find($type)
            ->methods
            ->map(function ($param) {
                return [
                    'active' => $param->active,
                    'name' => $param->name,
                    'param' => $param->param_str,
                    'full' => $param->full
                ];
            });

        return [
            'key' => $key,
            'group' => $group,
            'name' => $name,
            'type' => $type,
            'description' => $description,
            'options' => $options,
            'exists' => $exists
        ];
    }
}

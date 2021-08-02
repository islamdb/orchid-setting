<?php

namespace IslamDB\OrchidSetting\Screens;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use IslamDB\OrchidHelper\Field;
use IslamDB\OrchidSetting\Layouts\SettingFormListenerLayout;
use IslamDB\OrchidSetting\Models\Setting;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Matrix;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Modal;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Alert;
use Orchid\Support\Facades\Dashboard;
use Orchid\Support\Facades\Layout;

class SettingEditScreen extends Screen
{
    /**
     * Display header name.
     *
     * @var string
     */
    public $name = '';

    /**
     * Display header description.
     *
     * @var string|null
     */
    public $description = '';

    /**
     * Flag
     *
     * @var bool
     */
    protected $edit = true;

    /**
     * Setting model
     *
     * @var
     */
    protected $setting;

    public function __construct()
    {
        if (Str::contains(url()->current(), Dashboard::prefix('/setting/create'))) {
            $this->permission = [
                'setting.add'
            ];
        } else {
            $this->permission = [
                'setting.properties'
            ];
        }
    }

    /**
     * Query data.
     *
     * @return array
     */
    public function query(Setting $setting): array
    {
        if (!$setting->exists) {
            $type = 'Orchid\Screen\Fields\\'.request()->type;
            $type = Field::find($type);
            if (empty($type)) {
                $type = Field::find(Input::class);
            }

            $setting->options = $type->methods;
            $setting->options = collect(json_decode(json_encode($setting->options), true))
                ->map(function ($option) {
                    $option['param'] = $option['param_str'];
                    unset($option['param_str']);

                    return $option;
                });
            $setting->type = $type->class;

            $this->name = __('Create').' '.$type->name;
            $this->edit = false;
        } else {
            $this->name = __('Edit').' '.$setting->name;
            $setting->options = json_decode($setting->options, true);
        }

        $this->setting = $setting;

        return [
            'setting' => $setting
        ];
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): array
    {
        return [
            Button::make('Save')
                ->icon('save')
                ->method(($this->edit ? 'update' : 'store'))
        ];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): array
    {
        return [
            Layout::rows([
                Input::make('setting.key')
                    ->title('Key')
                    ->required(),
                Input::make('setting.name')
                    ->title('Name')
                    ->required(),
                Input::make('setting.group')
                    ->title('Group')
                    ->required(),
                TextArea::make('setting.description')
                    ->title('Description'),
                Matrix::make('setting.options')
                    ->title('Options')
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
                Input::make('setting.type')
                    ->type('hidden'),
                Input::make('old_key')
                    ->type('hidden')
                    ->value($this->setting->key)
            ])
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update()
    {
        $this->validate(request(), [
            'setting.key' =>  'required:unique:settings,key',
            'setting.name' => 'required',
            'setting.group' => 'required',
            'setting.type' => 'required',
            'old_key' => 'required'
        ]);

        $setting = Setting::query()
            ->find(request()->old_key);

        if (!empty($setting)) {
            $data = request()->get('setting');
            $data['options'] = json_encode($data['options']);

            $setting->fill($data);
            $setting->save();

            Alert::success($setting->name . ' ' . __('updated'));
        } else {
            Alert::warning(__('Setting was not found'));
        }

        return redirect()->route('setting.edit', ['setting' => $setting->key]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store()
    {
        $this->validate(request(), [
            'setting.key' => 'required',
            'setting.name' => 'required',
            'setting.group' => 'required',
            'setting.type' => 'required'
        ]);

        $data = request()->get('setting');
        $data['key'] = str_replace('.', '_', $data['key']);
        $data['options'] = json_encode($data['options'] ?? []);
        $data['position'] = Setting::query()->max('position') + 1;

        Setting::query()
            ->create($data);

        Alert::success(__('Setting was created.'));

        return redirect()->route('setting');
    }
}

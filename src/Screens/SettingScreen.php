<?php

namespace IslamDB\OrchidSetting\Screens;

use Illuminate\Support\Facades\DB;
use IslamDB\OrchidSetting\Layouts\SettingFormListenerLayout;
use IslamDB\OrchidSetting\Models\Setting;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Layouts\Modal;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Alert;
use Orchid\Support\Facades\Layout;

class SettingScreen extends Screen
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
     * Permissons
     *
     * @var
     */
    protected $user;
    protected $addPermission = false;
    protected $editPermission = false;
    protected $propertiesPermission = false;
    protected $orderPermission = false;
    protected $deletePermission = false;

    /**
     * @var string[]
     */
    public $permission = [
        'setting.browse'
    ];

    public function __construct()
    {
        $this->name = config('orchidsetting.name');
        $this->description = config('orchidsetting.description');

        $this->middleware(function () {
            $user = auth()->user();

            $this->addPermission = $user->hasAccess('setting.add');
            $this->editPermission = $user->hasAccess('setting.edit');
            $this->propertiesPermission = $user->hasAccess('setting.properties');
            $this->orderPermission = $user->hasAccess('setting.order');
            $this->deletePermission = $user->hasAccess('setting.delete');

            $this->user = $user;
        });
    }

    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        return [];
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): array
    {
        return [
            ModalToggle::make('Create')
                ->icon('plus')
                ->modalTitle(__('Create'))
                ->method('store')
                ->modal('createOrEdit')
                ->asyncParameters(['setting' => null])
        ];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): array
    {
        $settings = Setting::query()
            ->get()
            ->map(function ($setting) {
                $setting->options = json_decode($setting->options);

                return $setting;
            });

        $layouts = [
            Layout::tabs(
                $settings->groupBy('group')
                    ->map(function ($group, $key) {
                        $group = $group->sortBy('position')->values();
                        $settingCount = $group->count();

                        return Layout::rows(
                            $group->map(function (Setting $setting, $key) use ($settingCount) {
                                $group = [];

                                if ($this->editPermission) {
                                    $group[] = Button::make('Save')
                                        ->icon('check')
                                        ->method('save')
                                        ->parameters([
                                            '_clicked' => $setting->key
                                        ])
                                        ->type(Color::SUCCESS());
                                }

                                if ($this->propertiesPermission) {
                                    $group[] = ModalToggle::make('Properties')
                                        ->icon('pencil')
                                        ->type(Color::INFO())
                                        ->modalTitle(__('Edit') . ' ' . $setting->name)
                                        ->method('update')
                                        ->modal('createOrEdit')
                                        ->asyncParameters(['setting' => $setting->key]);
                                }

                                if ($this->deletePermission) {
                                    $group[] = Button::make('Delete')
                                        ->icon('close')
                                        ->type(Color::DANGER())
                                        ->method('delete')
                                        ->confirm(__('Delete') . ' ' . $setting->name)
                                        ->parameters([
                                            '_clicked' => $setting->key
                                        ]);
                                }

                                if ($this->orderPermission) {
                                    $up = Button::make('')
                                        ->icon('arrow-up-circle')
                                        ->method('upDown')
                                        ->parameters([
                                            '_clicked' => $setting->key,
                                            '_up_down' => '<'
                                        ]);
                                    $down = Button::make('')
                                        ->icon('arrow-down-circle')
                                        ->method('upDown')
                                        ->parameters([
                                            '_clicked' => $setting->key,
                                            '_up_down' => '>'
                                        ]);

                                    if ($key == 0) {
                                        $group[] = $down;
                                    } elseif ($key == $settingCount - 1) {
                                        $group[] = $up;
                                    } else {
                                        $group[] = $up;
                                        $group[] = $down;
                                    }
                                }

                                $fields = [
                                    Input::make($setting->key . '.old_value')
                                        ->type('hidden')
                                        ->value($setting->value),
                                    $setting->field(),
                                    Label::make('')
                                        ->value("setting('$setting->key')")
                                        ->style('font-family: "Courier New", monospace; font-size: smaller')
                                ];

                                if (count($group) > 0) {
                                    $fields[] = Group::make($group)
                                        ->autoWidth();
                                }

                                if ($settingCount - 1 > $key) {
                                    $fields[] = Label::make('')->hr();
                                }

                                return $fields;
                            })->flatten(1)
                                ->toArray()
                        );
                    })
                    ->toArray()
            )
        ];

        if ($this->addPermission or $this->editPermission) {
            $layouts[] = Layout::modal('createOrEdit', [SettingFormListenerLayout::class])
                ->method('update')
                ->applyButton(__('Save'))
                ->size(Modal::SIZE_LG)
                ->title('Update Setting')
                ->async('asyncGetData');
        }

        return $layouts;
    }

    /**
     * @param $setting
     * @return array
     */
    public function asyncGetData($setting)
    {
        return [
            'setting' => Setting::query()->find($setting)
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
    public function asyncField($key = null, $group = null, $name = null, $type = null, $description = null, $exists = null)
    {
        return SettingFormListenerLayout::process($key, $group, $name, $type, $description, $exists);
    }

    /**
     *
     */
    public function upDown()
    {
        rescue(function () {
            DB::beginTransaction();

            $currentSetting = Setting::query()
                ->select(['key', 'group', 'position'])
                ->find(request()->_clicked);
            $toSwitchSetting = Setting::query()
                ->select(['key', 'group', 'position'])
                ->where('group', $currentSetting->group)
                ->where('position', request()->_up_down, $currentSetting->position)
                ->orderBy('position', (request()->_up_down == '<' ? 'desc' : 'asc'))
                ->first();

            $_ = $currentSetting->position;
            $currentSetting->position = $toSwitchSetting->position;
            $toSwitchSetting->position = $_;
            $currentSetting->save();
            $toSwitchSetting->save();

            DB::commit();

            Alert::success(__('Saved'));
        }, function ($e) {
            Alert::error(__('Failed') . '. ' . $e->getMessage());
        });
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update()
    {
        $this->validate(request(), [
            'key' => 'required',
            'name' => 'required',
            'group' => 'required',
            'type' => 'required',
            'old_key' => 'required'
        ]);

        $setting = Setting::query()
            ->find(request()->old_key);

        if (!empty($setting)) {
            $data = request()->all();
            $data['options'] = json_encode($data['options']);

            $setting->fill($data);
            $setting->save();

            Alert::success($setting->name . ' ' . __('updated'));
        } else {
            Alert::warning(__('Setting was not found'));
        }
    }

    /**
     *
     */
    public function delete()
    {
        $setting = Setting::query()->find(request()->_clicked);
        if (!empty($setting)) {
            $setting->delete();

            Alert::success($setting->name . ' ' . __('deleted'));
        } else {
            Alert::warning(__('Setting was not found'));
        }
    }

    /**
     *
     */
    public function save()
    {
        $settings = request()->all();

        $setting = Setting::query()->find($settings['_clicked']);
        if (!empty($setting)) {
            $value = $settings[$setting->key];
            if (is_array($value)) {
                $new = $value['new_value'] ?? null;
                $old = $value['old_value'] ?? null;
                $old = ($setting->is_array_value or is_array($new))
                    ? json_decode($old)
                    : $old;

                if ($new != $old) {
                    $isArrayValue = is_array($new);
                    $new = $isArrayValue
                        ? json_encode($new)
                        : $new;

                    $setting->update([
                        'is_array_value' => $isArrayValue,
                        'value' => $new
                    ]);
                }
            }

            Alert::success($setting->name . ' ' . __('saved'));
        } else {
            Alert::warning(__('Setting was not found'));
        }
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store()
    {
        $this->validate(request(), [
            'key' => 'required',
            'name' => 'required',
            'group' => 'required',
            'type' => 'required'
        ]);

        $data = request()->all();
        $data['key'] = str_replace('.', '_', $data['key']);
        $data['options'] = json_encode($data['options'] ?? []);
        $data['position'] = Setting::query()->max('position') + 1;

        Setting::query()
            ->create($data);

        Alert::success(__('Setting was created.'));
    }
}

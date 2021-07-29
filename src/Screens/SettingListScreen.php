<?php

namespace IslamDB\OrchidSetting\Screens;

use Illuminate\Support\Facades\DB;
use IslamDB\OrchidHelper\Field;
use IslamDB\OrchidSetting\Models\Setting;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Layouts\Modal;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Alert;
use Orchid\Support\Facades\Layout;

class SettingListScreen extends Screen
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

        $this->middleware(function ($request, $next) {
            $user = auth()->user();

            $this->addPermission = $user->hasAccess('setting.add');
            $this->editPermission = $user->hasAccess('setting.edit');
            $this->propertiesPermission = $user->hasAccess('setting.properties');
            $this->orderPermission = $user->hasAccess('setting.order');
            $this->deletePermission = $user->hasAccess('setting.delete');

            $this->user = $user;

            return $next($request);
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
            DropDown::make('Create')
                ->icon('plus')
                ->list(
                    Field::all(false)
                        ->map(function ($field) {
                            return Link::make($field->name)
                                ->route('setting.create', [
                                    'type' => $field->name
                                ]);
                        })
                        ->toArray()
                )
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
                                    $group[] = Link::make('Properties')
                                        ->icon('pencil')
                                        ->type(Color::INFO())
                                        ->route('setting.edit', ['setting' => $setting->key]);
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

                                    if ($settingCount > 1) {
                                        if ($key == 0) {
                                            $group[] = $down;
                                        } elseif ($key == $settingCount - 1) {
                                            $group[] = $up;
                                        } else {
                                            $group[] = $up;
                                            $group[] = $down;
                                        }
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

        return $layouts;
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
     * Save Setting
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
}

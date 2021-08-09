<?php

namespace IslamDB\OrchidSetting\Screens;

use Carbon\Carbon;
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
use Orchid\Screen\Fields\Upload;
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
    protected $backupPermission = false;
    protected $restorePermission = false;

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
            $this->backupPermission = $user->hasAccess('setting.backup');
            $this->restorePermission = $user->hasAccess('setting.restore');

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
            Button::make('Backup')
                ->method('backup')
                ->rawClick()
                ->canSee($this->backupPermission)
                ->icon('cloud-download'),
            ModalToggle::make('Restore')
                ->method('restore')
                ->modal('restore')
                ->canSee($this->restorePermission)
                ->modalTitle('Restore')
                ->icon('cloud-upload'),
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

        if ($this->restorePermission) {
            $layouts[] = Layout::modal('restore', [
                Layout::rows([
                    Input::make('file')
                        ->type('file')
                        ->accept('.json')
                        ->required()
                ])
            ])->applyButton(__('Restore'));
        }

        return $layouts;
    }

    public function restore()
    {
        if (!$this->restorePermission) {
            Alert::warning(__('You are not allowed to do this action'));
        } else {
            $this->validate(request(), [
                'file' => 'required'
            ]);

            rescue(function () {
                $data = request()->file('file')->getRealPath();
                $data = file_get_contents($data);
                $data = json_decode($data, true);

                if (is_null($data)) {
                    Alert::warning(__('Your file is not valid'));
                } else {
                    $data = collect($data)->map(function ($setting) {
                        $setting['created_at'] = Carbon::parse($setting['created_at']);
                        $setting['updated_at'] = Carbon::parse($setting['updated_at']);

                        return $setting;
                    });

                    DB::beginTransaction();

                    Setting::query()
                        ->whereIn('key', $data->pluck('key')->toArray())
                        ->delete();

                    Setting::query()
                        ->insert($data->toArray());

                    DB::commit();

                    if ($data->count() <= 1) {
                        Alert::success($data->count().' '.__('setting was restored'));
                    } else {
                        Alert::success($data->count().' '.__('settings were restored'));
                    }
                }
            }, function (\Throwable $e) {
                DB::rollBack();

                Alert::error(__('Failed').'<br>'.$e->getMessage());
            });
        }
    }

    /**
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function backup()
    {
        if (!$this->backupPermission) {
            Alert::warning(__('You are not allowed to do this action'));
        } else {
            return response()->streamDownload(function () {
                echo Setting::all()
                    ->toJson(JSON_PRETTY_PRINT);
            }, 'setting.json');
        }
    }

    /**
     * Move setting
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
     * Delete setting
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

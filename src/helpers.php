<?php

use IslamDB\OrchidHelper\Field;
use IslamDB\OrchidSetting\Models\Setting;

if (!function_exists('chained_method_call')) {
    function chained_method_call($object, $methods)
    {
        $callStr = 'return $object->';
        foreach($methods as $method => $param){
            $callStr.= "$method($param)->";
        }
        $callStr = substr($callStr, 0, -2);
        $callStr.= ';';

        return eval($callStr);
    }
}

if (!function_exists('method_from_doc_code')) {
    function method_from_doc_code($line)
    {
        $exploded = explode('(', $line);
        $exploded[1] = explode(')', $exploded[1])[0].')';

        $name = explode(' ', $exploded[0]);
        $name = end($name);

        $method = $name.'('.$exploded[1];

        $params = explode(',', str_replace(')', '', $exploded[1]));
        $params = collect($params)
            ->map(function ($param) use ($line, $exploded) {
                if (str_contains($param, '$')) {
                    $param = explode('$', $param);
                    $name = explode(' ', $param[1])[0];
                    $default = explode('=', $param[1]);
                    $default = count($default) == 1
                        ? null
                        : trim($default[1]);

                    return (object)[
                        'name' => $name,
                        'default' => $default
                    ];
                }

                return [
                    'name' => '',
                    'default' => ''
                ];
            })
            ->where('name', '!=', '');

        $paramStr = $params->pluck('default')->join(', ');

        return [
            'name' => $name,
            'params' => $params->toArray(),
            'param_str' => $paramStr,
            'full' => $method,
            'raw' => trim($line)
        ];
    }
}

if (!function_exists('setting')) {
    function setting($key, $default = null, $getUrlIfAttachments = true)
    {
        $setting = Setting::query()
            ->find($key);

        if (empty($setting)) {
            return $default;
        }

        if (Field::isFileField($setting->type)) {
            $attachments = $setting->attachment()->get();

            if (count($attachments) == 1) {
                return $getUrlIfAttachments
                    ? $attachments->first()->url()
                    : $attachments->first();
            } elseif (count($attachments) == 0) {
                return $default;
            }

            return $getUrlIfAttachments
                ? $attachments->map->url()
                : $attachments;
        } elseif ($setting->is_array_value) {
            return rescue(function () use ($setting) {
                return json_decode($setting->value, true);
            }, function () use ($setting) {
                return $setting->value;
            });
        }

        return $setting->value;
    }
}

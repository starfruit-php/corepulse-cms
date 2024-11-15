<?php

namespace CorepulseBundle\Services;

use CorepulseBundle\Services\APIService;
use Pimcore\Db;
use Starfruit\BuilderBundle\Tool\LanguageTool;
use Starfruit\BuilderBundle\Model\Option;
use CorepulseBundle\Model\Indexing;
use Pimcore\Model\Document;
use Starfruit\BuilderBundle\Sitemap\Setting;

class SettingServices
{
    public static function getObjectSetting($blackList, $objectSetting)
    {
        // lấy danh sách bảng
        $query = 'SELECT * FROM `classes` WHERE id NOT IN ("' . implode('","', $blackList) . '")';
        $classListing = Db::get()->fetchAllAssociative($query);
        $data = [];
        foreach ($classListing as $class) {
            $data[] = [
                "id" => $class['id'],
                "name" => $class['name'],
                "checked" => in_array($class['id'], $objectSetting)
            ];
        }
        return $data;
    }

    // get data with obect or login
    public static function getData($type)
    {
        $item = Db::get()->fetchAssociative('SELECT * FROM `corepulse_settings` WHERE `type` = "' . $type . '"', []);
        if (!$item) {
            Db::get()->insert('corepulse_settings', [
                'type' => $type,
            ]);
            $item = Db::get()->fetchAssociative('SELECT * FROM `corepulse_settings` WHERE `type` = "' . $type . '"', []);
        }
        if ($item['config']) {
            $item['config'] = json_decode($item['config'], true);
        } else {
            $item['config'] = [];
        }

        return $item;
    }

    public static function updateConfig($type, $data)
    {
        return Db::get()->update('corepulse_settings', ['config' => json_encode($data)], ['type' => $type]);
    }
}

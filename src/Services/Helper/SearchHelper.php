<?php

namespace CorepulseBundle\Services\Helper;

use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition;

class SearchHelper
{
    public static function dataConfig()
    {
        $dataConfig = [];

        $objectSetting = Db::get()->fetchAssociative('SELECT * FROM `corepulse_settings` WHERE `type` = "object"', []);
        if ($objectSetting !== null && $objectSetting) {
            // lấy danh sách bảng
            $query = 'SELECT * FROM `classes`';
            $classListing = Db::get()->fetchAllAssociative($query);
            $dataObjectSetting = json_decode($objectSetting['config']) ?? [];

            foreach ($classListing as $class) {
                if (in_array($class['id'], $dataObjectSetting)) {
                    $classDefinition = ClassDefinition::getById($class['id']);

                    //lọc các field được cấu hình search
                    $visibleSearchConfig = array_filter($classDefinition->getFieldDefinitions(), function ($item) {
                        return $item->visibleSearch === true;
                    });

                    $visibleSearch = [];
                    foreach ($visibleSearchConfig as $item) {
                        if ($item instanceof \Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields) {
                            $children = $item->children;
                            foreach ($children as $child) {
                                if ($child->visibleSearch && self::validSearchField($child)) {
                                    $visibleSearch[] = $child->name;
                                }
                            }
                        } else {
                            if (self::validSearchField($item)) {

                                $visibleSearch[] = $item->name;
                            }
                        }
                    }

                    if (!empty($visibleSearch)) {
                        $dataConfig[] = [
                            "visibleSearch" => $visibleSearch,
                            "id" => $class["id"],
                            "name" => $class["name"],
                        ];
                    }
                }
            }
        }

        return $dataConfig;
    }

    public static function validSearchField($item)
    {
        if (
            $item instanceof \Pimcore\Model\DataObject\ClassDefinition\Data\Input
            || $item instanceof \Pimcore\Model\DataObject\ClassDefinition\Data\Numeric
            || $item instanceof \Pimcore\Model\DataObject\ClassDefinition\Data\Select
            || $item instanceof \Pimcore\Model\DataObject\ClassDefinition\Data\Wysiwyg
            || $item instanceof \Pimcore\Model\DataObject\ClassDefinition\Data\Email
            || $item instanceof \Pimcore\Model\DataObject\ClassDefinition\Data\Lastname
            || $item instanceof \Pimcore\Model\DataObject\ClassDefinition\Data\Firstname
            || $item instanceof \Pimcore\Model\DataObject\ClassDefinition\Data\Textarea
        ) return true;
        return false;
    }

    public static function getTree($model, $keyword = null, $config = [], $limit = 0)
    {
        $data = [];
        $conditionQuery = "";
        $conditionParams = [];

        $modelName = '\\Pimcore\\Model\\' . ucfirst($model) . '\Listing';

        if ($keyword) {
            $keyword = strtolower($keyword);

            switch ($model) {
                case 'asset':
                    $conditionQuery .= "LOWER(`filename`) LIKE :key OR LOWER(`path`) LIKE :key";
                    $conditionParams['key'] = "%" . $keyword . "%";
                    break;
                case 'document':
                    $conditionQuery .= "LOWER(`key`) LIKE :key OR LOWER(`path`) LIKE :key";
                    $conditionParams['key'] = "%" . $keyword . "%";
                    break;
                default:
                    if (!empty($config)) {
                        $modelName = '\\Pimcore\\Model\\DataObject\\' . ucfirst($model) . '\Listing';

                        $conditionQuery .= "LOWER(`key`) LIKE :key OR LOWER(`path`) LIKE :key";
                        foreach ($config as $conf) {
                            $conditionQuery .= " OR LOWER(`" . $conf . "`) LIKE :" . $conf;
                            $conditionParams[$conf] = "%" . $keyword . "%";
                        }
                        $conditionParams['key'] = "%" . $keyword . "%";
                    }
                    break;
            }
        }

        $listing = new $modelName();
        $listing->setCondition($conditionQuery, $conditionParams);
        $listing->setOrderKey('id');
        $listing->setOrder('ASC');

        if ($limit) {
            $listing->setLimit($limit);
        }

        if ($model != 'asset') {
            $listing->setUnpublished(true);
        }

        $listing->load();

        foreach ($listing as $item) {
            $class = '';

            if ($item->getType()) {
                if ($model == 'dataObject' && $item->getType() != 'folder') {
                    $class = $item->getClassName();
                } else {
                    if ($model != 'asset' && $model != 'document') {
                        $class = $model;
                        $model = 'dataObject';
                    }
                }

                $icon = self::getIcon($item->getType());
                $route = self::getRoute($model);

                $data[] = [
                    'id' => $item->getId(),
                    'name' => $item->getId() == 1 ? 'home' : $item->getKey(),
                    'path' => $item->getFullPath(),
                    'type' => $item->getType(),
                    'model' => $model,
                    'class' => $class,
                    'icon' => $icon,
                    'route' => $route,
                ];
            }
        }

        return $data;
    }

    public static function getRoute($type)
    {
        $route = '';
        if ($type == 'dataObject') {
            $route = 'vuetify_object_detail';
        }

        if ($type == 'asset') {
            $route = 'vuetify_asset_detail';
        }

        if ($type == 'document') {
            $route = 'vuetify_doc_detail';
        }

        return $route;
    }

    public static function getIcon($type)
    {
        $key = [
            "folder" => 'mdi-folder-outline',
            "object" => 'mdi-cube-outline',
            "image" => 'mdi-image',
            "document" => 'mdi-file-document',
            "text" => 'mdi-text',
            "video" => 'mdi-video',
            "unknown" => 'mdi-crosshairs-question',
            "page" => 'mdi-book-open-page-variant',
            "link" => 'mdi-link',
            "snippet" => 'mdi-file-code',
            "email" => 'mdi-email',
            "hardlink" => 'mdi-vector-link',
            'class' => 'mdi-book-multiple',
            'create' => 'mdi-plus',
            'listing' => 'mdi-menu',
            'printcontainer' => 'mdi-book-open-blank-variant',
            'printpage' => 'mdi-printer',
        ];

        if (array_key_exists($type, $key)) {
            return $key[$type];
        }

        return 'mdi-help-circle-outline';
    }

    public static function getClassSearch($action)
    {
        $datas = [];
        $classSetting = Db::get()->fetchAssociative('SELECT `config` FROM `corepulse_settings` WHERE `type` = "object"', []);
        if ($classSetting) {
            $classSetting = json_decode($classSetting['config'], true);
        }

        if ($classSetting && count($classSetting)) {
            foreach ($classSetting as $class) {
                $classDefinition = ClassDefinition::getById($class);
                if ($classDefinition) {
                    $data = [
                        "id" => $classDefinition->getId(),
                        "name" => $classDefinition->getName(),
                        "value" => $classDefinition->getId(),
                        "key" => $classDefinition->getName(),
                        "title" => $action,
                        "type" => "class",
                        "model" => "class",
                        "class" => "class",
                        "icon" => self::getIcon($action),
                        "action" => $action,
                    ];

                    $datas[] = $data;
                }
            }
        }

        return $datas;
    }
}

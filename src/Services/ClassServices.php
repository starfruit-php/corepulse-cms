<?php

namespace CorepulseBundle\Services;

use Pimcore\Db;
use Pimcore\Model\DataObject;

class ClassServices
{
    const KEY_DOCUMENT = 'Document';
    const KEY_OBJECT = 'DataObject';
    const KEY_ASSET = 'Asset';

    const BACKLIST_TYPE = [
        "fieldcollections", "block", "advancedManyToManyRelation", "advancedManyToManyObjectRelation", "password"
    ];

    CONST TYPE_RESPONSIVE = [
        "firstname" => 'string',
        "lastname" => 'string',
        "textarea" => 'string',
        "imageGallery" => 'gallery',
        "datetime" => 'string',
        "dateRange" => 'string',
        "checkbox" => 'boolean',
        "input" => 'string',
        "urlSlug" => 'string',
        "numeric" => 'number',
        "gender" => 'select',
        "manyToOneRelation" => 'select',
        "manyToManyObjectRelation" => 'multiselect',
        "manyToManyRelation" => 'multiselect',
        "advancedManyToManyRelation" => 'multiselect',
        "advancedManyToManyObjectRelation" => 'multiselect',
    ];

    CONST TYPE_OPTION = [
        "multiselect",
        "select",
        "gender",
        "manyToOneRelation",
        "manyToManyObjectRelation",
        "manyToManyRelation",
        "advancedManyToManyRelation",
        "advancedManyToManyObjectRelation",
    ];

    CONST SYSTEM_FIELD = ['id' => 'number', 'key' => 'string', 'path' => 'string', 'published' => 'boolean', 'modificationDate' => 'date', 'creationDate' => 'date' ];

    // check class setting
    public static function isValid($classId)
    {
        $objectSetting = Db::get()->fetchAssociative('SELECT * FROM `corepulse_settings` WHERE `type` = "object"');
        if ($objectSetting) {
            $data = json_decode($objectSetting['config'], true) ?? [];
            return in_array($classId, $data);
        }
        return false;
    }

    // get full field
    public static function examplesAction($classId)
    {
        $data = [];
        try {
            $classDefinition = DataObject\ClassDefinition::getById($classId);
            $propertyVisibility = $classDefinition->getPropertyVisibility();
            $fieldDefinitions = $classDefinition->getFieldDefinitions();
            $result = [];
            foreach ($fieldDefinitions as $key => $fieldDefinition) {
                if ($fieldDefinition instanceof DataObject\ClassDefinition\Data\Localizedfields) {
                    foreach ($fieldDefinition->getChildren() as $child) {
                        $result[$child->name] = self::getFieldProperty($child, true);
                    }
                } else {
                    $result[$key] = self::getFieldProperty($fieldDefinition);
                }
            }

            $data = [ 'fields' => $result, 'class' => $classDefinition->getName() ];

            $data = array_merge($data, $propertyVisibility);
        } catch (\Throwable $th) {
        }

        return $data;
    }

    // get detail field
    public static function getFieldProperty($fieldDefinition, $localized = false, $classId = null)
    {
        $fieldtype = $fieldDefinition->getFieldType();

        $data = get_object_vars($fieldDefinition);

        $data = array_merge($data, [
            'type' => $fieldtype,
            'fieldtype' => $fieldtype,
            'localized' => $localized,
        ]);

        $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($fieldtype);
        if (class_exists($getClass)) {
            $revert = get_object_vars($fieldDefinition);

            $component = new $getClass(null, $revert);
            $data['type'] = $component->getFrontEndType();
        }

        if($classId && in_array($fieldDefinition->getFieldType(), self::TYPE_OPTION)) {
            $data['api_options'] = [
                'id' => $data['name'],
                'class' => $classId
            ];
        }

        if (method_exists($fieldDefinition, 'getDisplayMode')) {
            $data['displayMode'] = $fieldDefinition->getDisplayMode();
        }

        if (method_exists($fieldDefinition, 'getChildren')) {
            if ($fieldtype != 'block') {
                foreach ($fieldDefinition->getChildren() as $k => $v) {
                    if (is_object($v)) {
                        $data['children'][$k] = ClassServices::getFieldProperty($v, $localized, $classId);
                    }
                }
            }
        }

        return $data;
    }

    public static function updateTable($className, $visibleFields, $tableView = false)
    {
        $config = self::getConfig($className);
        if ($config) {
            $saveData = json_decode($config['visibleFields'], true);

            $saveData = $tableView ? $visibleFields : ($visibleFields ?? $saveData ?? []);
            Db::get()->update('corepulse_class', ['visibleFields' => json_encode($saveData)], ['className' => $className]);
            return true;
        }
        return false;
    }

    //các field hệ thống
    public static function systemField($propertyVisibility = null)
    {
        $fields = [];
        $properties = self::SYSTEM_FIELD;

        foreach ($properties as $key => $property) {
            $fields[$key] = [
                "name" => $key,
                "title" => $key,
                "fieldtype" => "system",
                "type" => $property,
                "subtype" => $key,
            ];

            if ($propertyVisibility) {
                $fields[$key]["visibleSearch"] = $propertyVisibility['search'][$key] ?? false;
                $fields[$key]["visibleGridView"] = $propertyVisibility['grid'][$key] ?? false;
            }
        }

        return $fields;
    }

    public static function getConfig($className)
    {
        $item = Db::get()->fetchAssociative('SELECT * FROM `corepulse_class` WHERE `className` = ?', [$className]);
        if (!$item) {
            Db::get()->insert('corepulse_class', [
                'className' => $className,
            ]);
            $item = Db::get()->fetchAssociative('SELECT * FROM `corepulse_class` WHERE `className` = ?', [$className]);
        }

        return $item;
    }

    // condition = ['visibleSearch', 'visibleGridView']
    public static function getVisibleFields($fields, $condition)
    {
        return array_filter($fields, function($value) use ($condition) {
            return $value[$condition] === true;
        });
    }

    // filter visibleGridView
    public static function filterFill($fields, $tableView)
    {
        if(empty($tableView)) {
            return self::getVisibleFields($fields, 'visibleGridView');
        }

        $data = [];
        foreach ($tableView as $view) {
            $data[$view] = $fields[$view];
        }

        return $data;
    }

    public static function handleOption($entityType, $id, $fieldId)
    {
        switch ($entityType) {
            case 'fieldcollections':
                $layoutDefinition = DataObject\Fieldcollection\Definition::getByKey($id);
                if (!$layoutDefinition) return self::error('FieldCollection not found');
                break;
            case 'localizedfields':
                $layoutDefinition = DataObject\ClassDefinition::getById($id);
                if (!$layoutDefinition) return self::error('Class not found');
                break;
            case 'block':
                $layoutDefinition = DataObject\ClassDefinition::getById($id);
                if (!$layoutDefinition) return self::error('Class not found');
                break;
            case 'class':
                $layoutDefinition = DataObject\ClassDefinition::getById($id);
                if (!$layoutDefinition) return self::error('Class not found');
                break;
            default:
                $layoutDefinition = DataObject\ClassDefinition::getById($id);
                if (!$layoutDefinition) return self::error('Class not found');
                break;
        }

        $fieldDefinitions = $layoutDefinition->getFieldDefinitions();
        if ($entityType === 'block') {
            if (!is_array($fieldId) && !isset($fieldId[0]) && !isset($fieldDefinitions[$fieldId[0]])) {
                return self::error('Block Field not found');
            }

            $blockDefinition = $fieldDefinitions[$fieldId[0]];
            if ($blockDefinition && $blockDefinition->getChildren()) {
                $value = $fieldId[1];
                $filter = array_filter($blockDefinition->getChildren(), function($item) use ($value) {
                    return $item->name === $value;
                });
                if ($filter && $fieldDefinition = reset($filter)) {
                    return self::getFieldOptions($fieldDefinition);
                }
            }

            return self::error('Block Field not found');
        }

        if ($entityType === 'localizedfields') {
            $localizedfieldDefinition = $fieldDefinitions['localizedfields'];
            if ($localizedfieldDefinition && $localizedfieldDefinition->getChildren()) {
                $filter = array_filter($localizedfieldDefinition->getChildren(), function($item) use ($fieldId) {
                    return $item->name === $fieldId;
                });
                if ($filter && $fieldDefinition = reset($filter)) {
                    return self::getFieldOptions($fieldDefinition);
                }
            }

            return self::error('Localizedfields Field not found');
        }

        if ($fieldDefinitions && !isset($fieldDefinitions[$fieldId])) {
            return self::error('Field not found');
        }

        $fieldDefinition = $fieldDefinitions[$fieldId];
        return self::getFieldOptions($fieldDefinition);
    }

    public static function getFieldOptions($fieldDefinition)
    {
        if (!in_array($fieldDefinition->getFieldType(), self::TYPE_OPTION)) {
            return self::error('Field not select option');
        }

        return self::getOptions($fieldDefinition);
    }

    public static function error($mesage)
    {
        return  ['error' => ['message' => $mesage]];
    }

    static public function getOptions($fieldDefinition)
    {
        $data = [];
        $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($fieldDefinition->getFieldtype());
        if (class_exists($getClass)) {
            $component = new $getClass(null, $fieldDefinition);
            $data = $component->getOption();
        }

        return $data;
    }

    public static function getCommonOptions($config, $subtypes, $defaultTypes = ['asset', 'object', 'document'])
    {
        $data = [];
        $allType = isset($config['types']) && count($config['types']) ? $config['types'] : $defaultTypes;

        if (in_array('asset', $allType)) {
            $listAsset = isset($subtypes['asset']) && count($subtypes['asset']) ? $subtypes['asset'] : ['archive', 'image', 'audio', 'document', 'text', 'folder', 'video', 'unknown'];
            $data[] = self::getRelationType(ClassServices::KEY_ASSET, $listAsset);
        }

        if (in_array('document', $allType)) {
            $listDocument = isset($subtypes['document']) && count($subtypes['document']) ? $subtypes['document'] : ['email', 'link', 'hardlink', 'snippet', 'folder', 'page'];
            $data[] = self::getRelationType(ClassServices::KEY_DOCUMENT, $listDocument);
        }

        if (in_array('object', $allType)) {
            $listObject = isset($config['classes']) && count($config['classes']) ? $config['classes'] : self::getClassList(["user", "role"]);
            $subObject = isset($subtypes['object']) && count($subtypes['object']) ? $subtypes['object'] : ['object', 'variant', 'folder'];
            $data[] = self::getRelationType(ClassServices::KEY_OBJECT, $listObject, $subObject);
        }

        return $data;
    }

    static public function getRelationType($type, $listKey, $subObject = null)
    {
        $options = [
            'label' => $type,
            'value' => $type,
            'children' => [],
            'publish' => true,
        ];

        foreach ($listKey as $value) {
            $children = self::getRelationData($value, $type, $subObject);

            if (!empty($children)) {
                $options['children'][] = [
                    'label' => $value,
                    'value' => $value,
                    'children' => $children,
                    'publish' => true,
                ];
            }
        }

        return $options;
    }

    //type : loại trường đc cấu hình; model : asset, object , document
    public static function getRelationData($type, $model, $subtypeObject = null)
    {
        $data = [];

        try {
            $isObject = $model === ClassServices::KEY_OBJECT;
            $isSpecificType = $type !== 'All' && $type !== 'folder';

            $modelName = $isObject && $isSpecificType
                ? "Pimcore\\Model\\{$model}\\{$type}\\Listing"
                : "Pimcore\\Model\\{$model}\\Listing";

            $listing = new $modelName();

            if ($listing) {
                if ($model !== ClassServices::KEY_ASSET) {
                    $listing->setUnpublished(true);
                }

                if (($model !== ClassServices::KEY_OBJECT || $type === 'folder') && $isSpecificType) {
                    $listing->setCondition('type = ?', [$type]);
                }

                if ($isObject && $isSpecificType && !empty($subtypeObject)) {
                    $listing->setObjectTypes($subtypeObject);
                }

                $data = array_map(function ($item) use ($model) {
                    $key = $model === ClassServices::KEY_ASSET
                        ? $item->getFilename()
                        : $item->getKey();

                    return [
                        'key' => $key,
                        'value' => $item->getId(),
                        'type' => $model,
                        'label' => $key,
                        'publish' => $model !== ClassServices::KEY_ASSET ? $item->getPublished() : true,
                    ];
                }, iterator_to_array($listing));
            }
        } catch (\Throwable $th) {
        }

        return $data;
    }
    static public function getClassList($blackList)
    {
        $query = 'SELECT * FROM `classes` WHERE id NOT IN ("' . implode('","', $blackList) . '")';
        $classListing = Db::get()->fetchAllAssociative($query);
        $data = [];
        foreach ($classListing as $class) {
            $data[] = $class['name'];
        }

        return $data;
    }
}

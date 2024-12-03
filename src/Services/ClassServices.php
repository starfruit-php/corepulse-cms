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
}

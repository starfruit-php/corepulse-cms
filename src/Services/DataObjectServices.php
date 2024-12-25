<?php

namespace CorepulseBundle\Services;

use CorepulseBundle\Services\ClassServices;
use Pimcore\Model\DataObject;

class DataObjectServices
{
    // get data listing
    public static function getData($object, $fields, $backlist = false)
    {
        $data = [];
        foreach ($fields as $key => $field) {
            $field = self::convertField($field);

            // Check if field is in the blacklist
            if ($backlist && in_array($field['fieldtype'], ClassServices::BACKLIST_TYPE)) {
                continue;
            }

            // Handle localized fields
            if (isset($field['children']) && $field['fieldtype'] === 'localizedfields') {
                foreach ($field['children'] as $k => $vars) {
                    $data[$key][$k] = self::getComponentValue($object, $vars);
                }
                continue;
            }

            // For other fields
            $data[$key] = self::getComponentValue($object, $field);
        }

        return $data;
    }

    private static function getComponentValue($object, $field)
    {
        $field = self::convertField($field);
        $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($field['fieldtype']);

        return class_exists($getClass) ? (new $getClass($object, $field))->getValue() : null;
    }

    public static function getSidebarData($object, $locale = null)
    {
        $fields = ClassServices::systemField();

        $data = self::getData($object, $fields);

        $languages = \Pimcore\Tool::getValidLanguages();
        foreach ($languages as $language) {
            $data['languages'][] = [
                'key' => \Locale::getDisplayLanguage($language),
                'value' => $language,
                'selected' => $locale === $language,
            ];
        }

        return $data;
    }

    // convert object to array
    public static function convertField($field)
    {
        if (is_object($field)) {
            $result = get_object_vars($field);
            $result['fieldtype'] = $field->getFieldType();

            return $result;
        }

        return $field;
    }

    // save object detail
    public static function saveEdit($object, $updateData, $locale)
    {
        $classDefinition = $object->getClass();
        $fieldDefinitions = $classDefinition->getFieldDefinitions();

        foreach ($updateData as $key => $value) {
            if ($key == '_publish') {
                $object->setPublished($value === 'publish');
                continue;
            }

            if (isset($fieldDefinitions[$key])) {
                $object = self::processField($object, $fieldDefinitions[$key], $key, $value, $locale);
                if (!($object instanceof DataObject\AbstractObject)) {
                    return $object;
                }
            } elseif (isset($fieldDefinitions['localizedfields'])) {
                $object->getLocalizedfields()->setObject($object);
                foreach ($fieldDefinitions['localizedfields']->getChildren() as $k => $v) {
                    if ($v->getName() == $key) {
                        $object = self::processField($object, $v, $key, $value, $locale);
                        if (!($object instanceof DataObject\AbstractObject)) {
                            return $object;
                        }
                    }
                }
            }
        }

        $object->save();
        return $object;
    }

    public static function processField($object, $fieldDefinition, $key, $value, $locale)
    {
        try {
            $fieldType = $fieldDefinition->getFieldType();
            $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($fieldType);

            if (class_exists($getClass)) {
                $component = new $getClass($object, $fieldDefinition, $value, $locale);
                $func = 'set' . ucfirst($key);

                $object->{$func}($component->getDataSave(), $locale);
            }

            return $object;
        } catch (\Throwable $th) {
            $data = [
                'success' => false,
                'message' => "Key: " .$fieldDefinition->getTitle() . "\nError: " . $th->getMessage(),
            ];

            return $data;
        }
    }
}

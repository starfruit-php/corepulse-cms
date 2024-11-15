<?php

namespace CorepulseBundle\Services;

use CorepulseBundle\Services\ClassServices;

class DataObjectServices
{
    // get data listing
    static public function getData($object, $fields, $backlist = false)
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

    static private function getComponentValue($object, $field)
    {
        $field = self::convertField($field);
        $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($field['fieldtype']);

        return class_exists($getClass) ? (new $getClass($object, $field))->getValue() : null;
    }

    static public function getSidebarData($object, $locale = null)
    {
        $fields = ClassServices::systemField();

        $data = self::getData($object, $fields);

        $languages = \Pimcore\Tool::getValidLanguages();
        foreach ($languages as $language) {
            $data['languages'][] = [
                'key' => \Locale::getDisplayLanguage($language),
                'value' => $language,
                'selected' => $locale === $language
            ];
        }

        return $data;
    }

    // convert object to array
    static public function convertField($field)
    {
        if (is_object($field)) {
            $result = get_object_vars($field);
            $result['fieldtype'] = $field->getFieldType();

            return $result;
        }

        return $field;
    }

    // save object detail
    static public function saveEdit($object, $updateData, $locale)
    {
        $classDefinition = $object->getClass();
        $fieldDefinitions = $classDefinition->getFieldDefinitions();

        foreach ($updateData as $key => $value) {
            if (isset($fieldDefinitions[$key])) {
                $object = self::processField($object, $fieldDefinitions[$key], $key, $value, $locale);
            } elseif (isset($fieldDefinitions['localizedfields'])) {
                $object->getLocalizedfields()->setObject($object);
                foreach ($fieldDefinitions['localizedfields']->getChildren() as $k => $v) {
                    if ($v->getName() == $key) {
                        $object = self::processField($object, $v, $key, $value, $locale);
                    }
                }
            }
        }

        $object->save();
        return $object;
    }

    static public function processField($object, $fieldDefinition, $key, $value, $locale) {
        
            $fieldType = $fieldDefinition->getFieldType();
            $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($fieldType);

            if (class_exists($getClass)) {
                $component = new $getClass($object, $fieldDefinition, $value, $locale);
                $func = 'set' . ucfirst($key);

                $object->{$func}($component->getDataSave(), $locale);
            }
        
        return $object;
    }
}

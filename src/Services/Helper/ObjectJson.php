<?php

namespace CorepulseBundle\Services\Helper;

class ObjectJson extends JsonHelper
{
    public static function getJson($object, $hiddenFields = [])
    {
        $json = [];

        $class = method_exists($object, 'getClassName')
            ? \Pimcore\Model\DataObject\ClassDefinition::getByName($object->getClassName())
            : null;

        if ($class) {

            $json = self::getBaseJson($object, $class->getFieldDefinitions(), $hiddenFields, $class->getAllowInherit());
            $json = array_merge(['id' => $object->getId()], $json);
        }

        return $json;
    }
}

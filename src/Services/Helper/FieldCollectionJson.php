<?php

namespace CorepulseBundle\Services\Helper;

class FieldCollectionJson extends JsonHelper
{
    public static function getJson($fieldCollection, $hiddenFields = [])
    {
        $json = [];

        $class = method_exists($fieldCollection, 'getType')
            ? \Pimcore\Model\DataObject\Fieldcollection\Definition::getByKey($fieldCollection->getType())
            : null;

        return $class ? self::getBaseJson($fieldCollection, $class->getFieldDefinitions(), $hiddenFields) : $json;
    }
}

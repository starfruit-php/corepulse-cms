<?php

namespace CorepulseBundle\Services\Helper;

use Pimcore\Model\DataObject\Objectbrick\Definition;

class BrickJson extends JsonHelper
{
    public static function getJson($brick, $hiddenFields = [])
    {
        $json = [];

        $class = method_exists($brick, 'getType')
            ? \Pimcore\Model\DataObject\Objectbrick\Definition::getByKey($brick->getType())
            : null;

        return $class ? self::getBaseJson($brick, $class->getFieldDefinitions(), $hiddenFields) : $json;
    }
}

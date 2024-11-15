<?php

namespace CorepulseBundle\Services\Helper;

use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition;
use CorepulseBundle\Services\Helper\Text\PrettyText;

class ArrayHelper
{
    //xử lý filter
    public static function filterData($data, $key, $value)
    {
        if (count($data)) {

            $result = array_filter($data, function ($item) use ($key, $value) {
                $itemData = is_object($item) ? get_object_vars($item) : $item;

                return self::checkValue($itemData[$key], $value);
            });

            return $result;
        }
    }

    public static function checkValue($string, $value)
    {
        $lowercaseString = PrettyText::getPretty(strtolower($string));
        $lowercaseValue = PrettyText::getPretty(strtolower(ltrim($value)));

        return stripos($lowercaseString, $lowercaseValue) !== false;
    }

    //sắp xếp dữ liệu
    public static function sortArrayByField($array, $field, $order = 'asc') {
        usort($array, function($a, $b) use ($field, $order) {
            if (is_object($a) && is_object($b)) {
                $keyName = 'get' . ucfirst($field);
                $valueA = $a->$keyName();
                $valueB = $b->$keyName();
            } else {
                $valueA = $a[$field];
                $valueB = $b[$field];
            }

            if ($valueA == $valueB) {
                return 0;
            }

            if ($order == 'asc') {
                return ($valueA < $valueB) ? -1 : 1;
            } else {
                return ($valueA > $valueB) ? -1 : 1;
            }
        });

        return $array;
    }
}

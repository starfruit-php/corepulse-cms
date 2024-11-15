<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\DataObject\Data\NumericRange as Numbers;

class NumericRange extends Input
{
    public function formatBlock($value)
    {
        if ($value) {
            $data = $value->getMinimum() . ' - ' . $value->getMaximum();

            return $data;
        }

        return null;
    }

    public function formatDataSave($value)
    {
        if ($value) {
            $params = explode(' - ', $value);

            $data = new Numbers($params[0], $params[1]);
            return $data;
        }
        
        return null;
    }

    public function getFrontEndType()
    {
        return 'numberRange';
    }
}

<?php

namespace CorepulseBundle\Component\Field;

use CorepulseBundle\Component\Field\FieldInterface;
use Pimcore\Model\DataObject\Data\BlockElement;

class Input extends AbstractField
{
    public function format($value)
    {
        return $value;
    }

    public function formatDocument($value)
    {
        return $value;
    }

    public function formatBlock($value)
    {
        return $value;
    }

    public function formatDataSave($value)
    {
        return $value;
    }

    public function getFrontEndType()
    {
        return 'string';
    }
}

<?php

namespace CorepulseBundle\Component\Field;

use CorepulseBundle\Services\Helper\ObjectHelper;

class System extends Input
{
    const SYSTEM_CONVERT_DATE = ['creationDate', 'modificationDate'];

    public function format($value)
    {
        if (in_array($this->layout->subtype, self::SYSTEM_CONVERT_DATE)) {
            return date('Y/m/d', $value);
        }

        if ($this->layout->subtype == 'published') {
            return ObjectHelper::getPublished($this->getObjectOrDocument());
        }

        return $value;
    }
}

<?php

namespace CorepulseBundle\Component\Field;

use Starfruit\BuilderBundle\Tool\AssetTool;

class ExternalImage extends Image
{
    public function format($value)
    {
        if ($value) {
            return $value->getUrl();
        }

        return null;
    }
}

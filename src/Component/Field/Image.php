<?php

namespace CorepulseBundle\Component\Field;

use Starfruit\BuilderBundle\Tool\AssetTool;
use Pimcore\Model\Asset;

class Image extends AbstractField
{
    public function formatDocument($value) 
    {
        return $this->format($value);
    }

    public function format($value)
    {
        $data = [
            [
                'path' => $value?->getFrontendPath(),
                'id' => $value?->getId()
            ]
        ];

        return $data;
    }

    public function formatBlock($value)
    {
        return  $this->format($value);
    }

    public function formatDataSave($value)
    {
        $image = null;
        if ($value && $format = reset($value)) {
            $image = Asset::getById((int)$format['id']);
        }

        return $image;
    }

    public function getFrontEndType()
    {
        return 'image';
    }
}

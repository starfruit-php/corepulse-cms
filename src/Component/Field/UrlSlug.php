<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\DataObject\Data\UrlSlug as DataUrlSlug;

class UrlSlug extends Input
{
    public function format($data)
    {
        if (empty($data)) {
            return null;
        }

        $value = array_shift($data)?->getSlug();

        return $value;
    }

    public function formatDataSave($value)
    {
        $slug = new DataUrlSlug($value);

        $data = [$slug];

        return $data;
    }
}

<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\DataObject\Data\GeoCoordinates;

class Geopoint extends AbstractField
{
    public function formatBlock($value)
    {
        return $this->format($value);
    }

    public function formatDocument($value)
    {
        return $this->format($value);
    }

    public function format($value)
    {
        if ($value) {
            return [
                'latitude' => $value->getLatitude(),
                'longitude' => $value->getLongitude(),
            ];
        }

        return null;
    }

    public function formatDocumentSave($value)
    {
        return $value;
    }

    public function formatDataSave($value)
    {
        if (is_array($value)) {
            $latitude = (float)$value['lat'];
            $longitude = (float)$value['lng'];

            $data = new GeoCoordinates($latitude, $longitude);
            return $data;
        }

        return null;
    }

    public function getFrontEndType()
    {
        return 'geopoint';
    }
}

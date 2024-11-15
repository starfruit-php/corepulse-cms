<?php

namespace CorepulseBundle\Component\Field;

use Starfruit\BuilderBundle\Tool\AssetTool;

class Hotspotimage extends Geopoint
{
    public function format($value)
    {
        if ($value) {
            $result = [];
            $result['hotspots'] = $value->getHotspots();
            $result['marker'] = $value->getMarker();
            $result['crop'] = $value->getCrop();
            $result['image'] = AssetTool::getPath($value->getImage(), true);

            if ($result['image']) {
                $result['image']['id'] = $value->getImage()->getId();
            }

            return $result;
        }

        return null;
    }

    public function getFrontEndType()
    {
        return 'hotspotimage';
    }
}

<?php

namespace CorepulseBundle\Component\Field;

use CorepulseBundle\Services\ClassServices;

class ManyToManyObjectRelation extends ManyToManyRelation
{
    public function getOption()
    {
        $layoutDefinition = $this->layout;

        $classes = $layoutDefinition->classes;
        $blackList = ["user", "role"];
        $listObject = self::getClassList($blackList);

        $data = self::getRelationType($classes, ClassServices::KEY_OBJECT, 'classes', $listObject);

        // if ($options && count($options) == 1) {
        //     $options = isset($options[0]['children']) ? $options[0]['children'] : [];
        //     if ($options && count($options) == 1) {
        //         $options = $options[0]['children'];
        //     }
        // }

        return $data;
    }
}

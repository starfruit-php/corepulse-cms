<?php

namespace CorepulseBundle\Component\Field;

use CorepulseBundle\Services\ClassServices;

class ManyToManyObjectRelation extends ManyToManyRelation
{
    public function getOption()
    {
        $layoutDefinition = $this->layout;

        $classes = self::mapTypes($layoutDefinition->classes, 'classes');

        $config = [
            'types' => ['object'],
            'classes' => $classes,
        ];

        $subtypes = [
            // 'object' => [],
        ];

        return ClassServices::getCommonOptions($config, $subtypes);
    }
}

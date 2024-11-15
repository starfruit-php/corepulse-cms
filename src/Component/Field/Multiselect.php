<?php

namespace CorepulseBundle\Component\Field;

class Multiselect extends Select
{
    public function format($value)
    {
        if (!empty($value)) {
            $data = [];
            foreach ($value as $k => $v) {
                $data[] = $v;
            }

            return $data;
        }

        return null;
    }

    public function getFrontEndType()
    {
        return 'multiselect';
    }
}

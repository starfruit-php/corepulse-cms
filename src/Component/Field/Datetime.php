<?php

namespace CorepulseBundle\Component\Field;

use Carbon\Carbon;

class Datetime extends Date
{
    public function format($value)
    {
        return $value?->format("Y/m/d H:i");
    }

    public function formatDataSave($value)
    {
        if ($value) {
            $data = Carbon::createFromFormat('Y/m/d H:i', $value);

            return $data;
        }

        return null;
    }
}

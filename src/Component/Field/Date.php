<?php

namespace CorepulseBundle\Component\Field;

use Carbon\Carbon;

class Date extends AbstractField
{
    public function format($value)
    {
        return $value?->format("Y/m/d");
    }

    public function formatBlock($value)
    {
        return $value?->format("Y/m/d");
    }

    public function formatDocument($value)
    {
        return $value?->format("Y/m/d");
    }

    public function formatDataSave($value)
    {
        if ($value) {
            $data = Carbon::createFromFormat('Y/m/d', $value);

            return $data;
        }

        return null;
    }

    public function formatDocumentSave($value)
    {
        return $value;
    }

    public function getFrontEndType()
    {
        return 'date';
    }
}

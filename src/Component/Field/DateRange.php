<?php

namespace CorepulseBundle\Component\Field;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DateRange extends Date
{
    public function format($value)
    {
        if ($value) {
            $data = $value->getStartDate()?->format("Y/m/d") . " - " . $value->getEndDate()?->format("Y/m/d");

            return $data;
        }

        return null;
    }

    public function formatDataSave($value)
    {
        if ($value) {
            $convert = explode_and_trim('-', $value);
            $startDate = Carbon::createFromFormat('Y/m/d', trim($convert[0]));
            $endDate = Carbon::createFromFormat('Y/m/d', trim($convert[1]));

            $data = CarbonPeriod::create($startDate, $endDate);

            return $data;
        }

        return null;
    }
}

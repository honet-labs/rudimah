<?php

namespace App\Shared\Helpers;

use Carbon\Carbon;

class DateHelper
{
    public static function formatLocal(string|Carbon $date, string $format = 'date'): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        $carbon->locale('id');

        return match ($format) {
            'time' => $carbon->isoFormat('HH:mm'),
            'datetime' => $carbon->isoFormat('D MMM YYYY, HH:mm'),
            default => $carbon->isoFormat('D MMM YYYY'),
        };
    }
}

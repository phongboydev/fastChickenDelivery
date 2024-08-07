<?php


namespace App\Support;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class FormatHelper
{
    public static function date($date, $format = 'd-m-Y')
    {
        try {
            if (!empty($date) && $date !== '0000-00-00' && $date !== '0000-00-00 00:00:00' && strtotime($date) !== false) {
                return !empty($date) && Carbon::parse($date) instanceof Carbon ? Carbon::parse($date)->format($format) : "";
            } else {
                return "";
            }
        } catch (\Exception $e) {
            return "";
        }
    }

    /**
     * Transform a date value into a Carbon object.
     *
     * @return String|null
     */
    public static function transformDate($value, $format = 'Y-m-d')
    {
        if ((is_int((int)$value)) && (strlen((string)$value) == 5)) {

            $value = Date::excelToDateTimeObject($value);

            return $value->format($format);
        }

        try {
            return Carbon::parse($value)->format($format);
        } catch (\Exception $e1) {
            try {
                return Carbon::instance(Date::excelToDateTimeObject($value));
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    public static function gender($status)
    {
        $status = strtolower($status);
        switch ($status) {
            case 'nam':
            case '男性':
            case '男':
                return 'male';
            case 'nữ':
            case '女性':
            case '女':
                return 'female';
            default:
                return $status;
        }
    }
}

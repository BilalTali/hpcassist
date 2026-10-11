<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolWorkingDay extends Model
{
    use HasFactory;

    protected $table = 'school_working_days';

    protected $fillable = [
        'academic_year',
        'apr',
        'may',
        'jun',
        'jul',
        'aug',
        'sep',
        'oct',
        'nov',
        'dec',
        'jan',
        'feb',
        'mar',
        'total_days',
        'is_default',
        'remarks',
    ];

    protected $casts = [
        'apr' => 'integer',
        'may' => 'integer',
        'jun' => 'integer',
        'jul' => 'integer',
        'aug' => 'integer',
        'sep' => 'integer',
        'oct' => 'integer',
        'nov' => 'integer',
        'dec' => 'integer',
        'jan' => 'integer',
        'feb' => 'integer',
        'mar' => 'integer',
        'total_days' => 'integer',
        'is_default' => 'boolean',
    ];

    public static function months(): array
    {
        return ['apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'];
    }

    public static function defaultDays(): array
    {
        return [
            'apr' => 20,
            'may' => 22,
            'jun' => 10,
            'jul' => 20,
            'aug' => 22,
            'sep' => 21,
            'oct' => 19,
            'nov' => 20,
            'dec' => 15,
            'jan' => 15,
            'feb' => 20,
            'mar' => 18,
        ];
    }

    public function calculateTotal(): int
    {
        $sum = 0;
        foreach (self::months() as $m) {
            $sum += intval($this->{$m} ?? 0);
        }
        return $sum;
    }

    public function toMonthMap(): array
    {
        $map = [];
        foreach (self::months() as $m) {
            $map[$m] = intval($this->{$m} ?? 0);
        }
        return $map;
    }

    public static function getDefault(): self
    {
        $record = self::where('is_default', true)->first();

        if (! $record) {
            $record = self::first();
        }

        if (! $record) {
            $defaults = self::defaultDays();
            $record = self::create(array_merge([
                'academic_year' => '2025-2026',
                'is_default'    => true,
                'total_days'    => array_sum($defaults),
                'remarks'       => 'Default Standard Academic Calendar',
            ], $defaults));
        }

        return $record;
    }
}

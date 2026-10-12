<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolMaxMark extends Model
{
    use HasFactory;

    protected $table = 'school_max_marks';

    protected $fillable = [
        'school_id',
        'stage',
        'academic_year',
        'l1_max',
        'l2_max',
        'l3_max',
        'm_max',
        'sci_max',
        'soc_max',
        'art_max',
        'pe_max',
        'is_default',
        'remarks',
    ];

    protected $casts = [
        'l1_max'     => 'integer',
        'l2_max'     => 'integer',
        'l3_max'     => 'integer',
        'm_max'      => 'integer',
        'sci_max'    => 'integer',
        'soc_max'    => 'integer',
        'art_max'    => 'integer',
        'pe_max'     => 'integer',
        'is_default' => 'boolean',
    ];

    public static function subjects(): array
    {
        return ['l1', 'l2', 'l3', 'm', 'sci', 'soc', 'art', 'pe'];
    }

    public static function subjectLabels(?string $stage = 'middle'): array
    {
        if ($stage === 'preparatory') {
            return [
                'l1'  => 'Language 1 (R1)',
                'l2'  => 'Language 2 (R2)',
                'l3'  => 'Language 3 / Regional',
                'm'   => 'Mathematics',
                'sci' => 'The World Around Us (EVS)',
                'soc' => 'Social & Environmental',
                'art' => 'Art Education',
                'pe'  => 'Physical & Health Education',
            ];
        }

        return [
            'l1'  => 'Language 1 (R1)',
            'l2'  => 'Language 2 (R2)',
            'l3'  => 'Language 3 (R3)',
            'm'   => 'Mathematics',
            'sci' => 'Science',
            'soc' => 'Social Science',
            'art' => 'Art Education',
            'pe'  => 'Physical Education & Well Being',
        ];
    }

    public static function defaultMarks(): array
    {
        return [
            'l1_max'  => 50,
            'l2_max'  => 50,
            'l3_max'  => 50,
            'm_max'   => 50,
            'sci_max' => 50,
            'soc_max' => 50,
            'art_max' => 50,
            'pe_max'  => 50,
        ];
    }

    public static function getDefault(?string $stage = 'middle'): self
    {
        $stage = $stage ?: 'middle';
        $record = self::where('stage', $stage)->first();

        if (! $record) {
            $record = self::create(array_merge(
                [
                    'stage'         => $stage,
                    'academic_year' => '2025-2026',
                    'is_default'    => true,
                    'remarks'       => ucfirst($stage) . ' Stage Curricular Max Marks'
                ],
                self::defaultMarks()
            ));
        }

        return $record;
    }

    public function toSubjectMap(): array
    {
        return [
            'l1'  => $this->l1_max,
            'l2'  => $this->l2_max,
            'l3'  => $this->l3_max,
            'm'   => $this->m_max,
            'sci' => $this->sci_max,
            'soc' => $this->soc_max,
            'art' => $this->art_max,
            'pe'  => $this->pe_max,
        ];
    }
}

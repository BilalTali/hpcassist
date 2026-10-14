<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'first_name',
        'last_name',
        'name',
        'dob',
        'email',
        'grade',
        'section',
        'apaar_id',
        'pen_code',
        'admission_no',
        'udise_student_id',
        'gender',
        'source',
        'status',
        'remark',
        'failure_reason',
        'hpc_submitted_at',
    ];

    protected $casts = [
        'hpc_submitted_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Scope for querying students of a particular school.
     */
    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope for successfully synced or submitted students.
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed or invalid students.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get effective gender (infer from name if not stored).
     */
    public function getEffectiveGender(): string
    {
        if (! empty($this->gender)) {
            $g = strtolower(trim($this->gender));
            if (str_starts_with($g, 'f') || str_contains($g, 'girl')) return 'Female';
            if (str_starts_with($g, 'm') || str_contains($g, 'boy')) return 'Male';
        }

        $name = strtolower(trim($this->name ?: "{$this->first_name} {$this->last_name}"));
        $femaleIndicators = [
            'jan', 'akhter', 'begum', 'bano', 'fatima', 'zehra', 'kousar', 'maryam',
            'aabroo', 'aaliya', 'adeeba', 'hafsa', 'insha', 'rabia', 'toiba', 'zaira',
            'afreen', 'asma', 'nimrah', 'jannat', 'misbah', 'safoora', 'tahira', 'zenab',
            'shazia', 'ayaz', 'ayat', 'muntaha', 'abreena', 'bilkeesa', 'bisma', 'farhat',
            'mehnaz', 'ronak', 'rubeena', 'rukhsana', 'saimah', 'simran', 'tasleema', 'towseer', 'uzma'
        ];
        foreach (preg_split('/\s+/', $name) as $w) {
            if (in_array($w, $femaleIndicators)) return 'Female';
        }
        return 'Male';
    }

    /**
     * Format student model into preview array structure expected by dashboard and automation scripts.
     */
    public function toPreviewArray(int $rowNum = 1): array
    {
        $errors = [];
        if (! empty($this->failure_reason)) {
            $errors = array_filter(array_map('trim', explode(',', $this->failure_reason)));
        }

        return [
            'id'         => $this->id,
            'row'        => $rowNum,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name ?? '',
            'name'       => $this->name,
            'gender'     => $this->getEffectiveGender(),
            'dob'        => $this->dob ?? '',
            'email'      => $this->email ?? '',
            'grade'      => $this->grade,
            'section'    => $this->section ?? 'A',
            'apaar_id'     => $this->apaar_id ?? '',
            'pen_code'     => $this->pen_code ?? '',
            'admission_no' => $this->admission_no ?? '',
            'status'       => $this->status ?? 'pending',
            'remark'     => $this->remark ?? '',
            'errors'     => array_values($errors),
        ];
    }
}

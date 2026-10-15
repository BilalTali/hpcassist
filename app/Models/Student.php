<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    use HasFactory;

    // ─── Source Constants ────────────────────────────────────────────────────
    // Where did this student record originate?
    const SOURCE_UDISE   = 'udise_sync';     // Fetched from UDISE+ portal
    const SOURCE_EXCEL   = 'excel_upload';   // Uploaded via Excel spreadsheet
    const SOURCE_MANUAL  = 'manual';         // Added manually on dashboard
    const SOURCE_HPC     = 'hpc_portal';     // Already exists on PARAKH HPC portal

    // ─── Status Constants ────────────────────────────────────────────────────
    const STATUS_PENDING = 'pending';        // Not yet submitted to HPC
    const STATUS_SUCCESS = 'success';        // Successfully submitted/confirmed on HPC
    const STATUS_FAILED  = 'failed';         // Submission attempted but failed

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
        'hpc_student_id',
        'gender',
        'source',
        'status',
        'remark',
        'failure_reason',
        'hpc_submitted_at',
        'hpc_fetched_at',
    ];

    protected $casts = [
        'hpc_submitted_at' => 'datetime',
        'hpc_fetched_at'   => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────────────────────────────

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    // ─── Query Scopes ────────────────────────────────────────────────────────

    /** Students belonging to a particular school. */
    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Step 2 students: sourced from UDISE, Excel, or manual entry and still
     * pending submission. These are shown on the dashboard roster and queued
     * for the automation runner. Includes failed rows so users can inspect
     * and correct validation errors on the roster.
     */
    public function scopeStep2($query)
    {
        return $query->whereIn('source', [self::SOURCE_UDISE, self::SOURCE_EXCEL, self::SOURCE_MANUAL])
                     ->where(function ($q) {
                         $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_FAILED])
                           ->orWhere(function ($sub) {
                               // Recovery for unsubmitted rows previously saved with status='success'
                               $sub->where('status', self::STATUS_SUCCESS)
                                   ->whereNull('hpc_submitted_at')
                                   ->whereNull('hpc_fetched_at');
                           });
                     });
    }

    /**
     * Step 3 students: already confirmed on the PARAKH HPC portal, either
     * fetched from the portal list or successfully submitted via HPC Assist.
     */
    public function scopeStep3($query)
    {
        return $query->where(function ($q) {
            $q->where('source', self::SOURCE_HPC)
              ->orWhereNotNull('hpc_submitted_at')
              ->orWhereNotNull('hpc_fetched_at');
        });
    }

    /** All students that have been successfully handled (submitted or already on portal). */
    public function scopeSuccess($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /** Students that failed validation or submission. */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Normalise and return the stored gender value.
     * Returns 'Unknown' when gender is absent or unrecognised — callers are
     * responsible for treating 'Unknown' as a validation error rather than
     * guessing from the student's name.
     */
    public function getEffectiveGender(): string
    {
        if (empty($this->gender)) {
            return 'Unknown';
        }
        $g = strtolower(trim($this->gender));
        if (str_starts_with($g, 'f') || str_contains($g, 'girl')) return 'Female';
        if (str_starts_with($g, 'm') || str_contains($g, 'boy'))  return 'Male';
        if (str_contains($g, 'other') || $g === '3')              return 'Other';
        return 'Unknown';
    }

    /**
     * Whether this student is already confirmed on the HPC portal
     * (either fetched from portal list or successfully submitted).
     */
    public function isHpcConfirmed(): bool
    {
        return $this->source === self::SOURCE_HPC
            || ! empty($this->hpc_submitted_at)
            || ! empty($this->hpc_fetched_at);
    }

    /**
     * Format student model into preview array structure expected by dashboard
     * and automation scripts.
     */
    public function toPreviewArray(int $rowNum = 1): array
    {
        $errors = [];
        if (! empty($this->failure_reason)) {
            $errors = array_filter(array_map('trim', explode(',', $this->failure_reason)));
        }

        return [
            'id'           => $this->id,
            'row'          => $rowNum,
            'first_name'   => $this->first_name,
            'last_name'    => $this->last_name ?? '',
            'name'         => $this->name,
            'gender'       => $this->getEffectiveGender(),
            'dob'          => $this->dob ?? '',
            'email'        => $this->email ?? '',
            'grade'        => $this->grade,
            'section'      => $this->section ?? 'A',
            'apaar_id'     => $this->apaar_id ?? '',
            'pen_code'     => $this->pen_code ?? '',
            'admission_no' => $this->admission_no ?? '',
            'hpc_student_id' => $this->hpc_student_id ?? '',
            'source'       => $this->source ?? self::SOURCE_UDISE,
            'status'       => $this->status ?? self::STATUS_PENDING,
            'remark'       => $this->remark ?? '',
            'errors'       => array_values($errors),
        ];
    }
}

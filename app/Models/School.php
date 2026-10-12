<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'udise_code',
        'category',
        'principal_name',
        'phone',
        'district',
        'state',
        'address',
        'udise_status',
        'udise_synced_at',
        'udise_students_count',
        'udise_apaar_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'udise_synced_at' => 'datetime',
        'udise_students_count' => 'integer',
        'udise_apaar_count' => 'integer',
    ];

    public function isUdiseSynced(): bool
    {
        return $this->udise_status === 'synced';
    }

    public function isUdiseSkipped(): bool
    {
        return $this->udise_status === 'skipped';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function activeTeachers(): HasMany
    {
        return $this->hasMany(Teacher::class)->where('is_active', true);
    }

    public function workingDays(): HasMany
    {
        return $this->hasMany(SchoolWorkingDay::class);
    }

    public function maxMarks(): HasMany
    {
        return $this->hasMany(SchoolMaxMark::class);
    }

    public function getStorageDirAttribute(): string
    {
        $dir = storage_path("app/hpc/schools/{$this->id}");
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public function getProfileDirAttribute(): string
    {
        $dir = storage_path("app/hpc/schools/{$this->id}/chrome-profile");
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }
}

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

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
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

    /**
     * Terminate all browser processes and delete all session files for this school.
     */
    public function killAllHpcSessions(): void
    {
        $schoolStorageDir = storage_path('app/hpc/schools/' . $this->id);

        // 1. Terminate running browser/ChromeDriver processes associated with this school
        if (function_exists('exec')) {
            @exec("pkill -f " . escapeshellarg($schoolStorageDir) . " 2>/dev/null");
            if ($this->id === 1) {
                @exec("pkill -f " . escapeshellarg(storage_path('app/hpc/chrome-profile')) . " 2>/dev/null");
                @exec("pkill -f " . escapeshellarg(storage_path('app/hpc/teacher-profile')) . " 2>/dev/null");
                @exec("pkill -f " . escapeshellarg(storage_path('app/hpc/teacher-profiles')) . " 2>/dev/null");
            }
        }

        // 2. Wipe active School HPC session profile
        $schoolProfileDir = $schoolStorageDir . '/chrome-profile';
        if (is_dir($schoolProfileDir)) {
            self::wipeDirectory($schoolProfileDir);
            @rmdir($schoolProfileDir);
        }

        // 3. Wipe all Teacher HPC session profiles for this school
        $teacherProfilesBase = $schoolStorageDir . '/teacher-profiles';
        if (is_dir($teacherProfilesBase)) {
            self::wipeDirectory($teacherProfilesBase);
            @rmdir($teacherProfilesBase);
        }

        // 4. Legacy clean up for School #1 if present
        if ($this->id === 1) {
            $legacyDirs = [
                storage_path('app/hpc/chrome-profile'),
                storage_path('app/hpc/teacher-profile'),
                storage_path('app/hpc/teacher-profiles'),
            ];
            foreach ($legacyDirs as $ldir) {
                if (is_dir($ldir)) {
                    self::wipeDirectory($ldir);
                    @rmdir($ldir);
                }
            }
        }
    }

    public static function wipeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        try {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($items as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getRealPath());
                } else {
                    @unlink($item->getRealPath());
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Error wiping directory ' . $dir . ': ' . $e->getMessage());
        }
    }
}

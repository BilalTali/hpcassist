<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SCHOOL  = 'school';
    public const ROLE_TEACHER = 'teacher';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'school_id',
        'teacher_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function isSchool(): bool
    {
        return empty($this->role) || $this->role === self::ROLE_SCHOOL;
    }

    public function teacher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function school(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(School::class);
    }

    public function teachers(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Teacher::class, School::class);
    }

    public function getEffectiveSchool(): ?School
    {
        if ($this->isTeacher()) {
            return $this->teacher?->school 
                ?: ($this->school_id ? School::find($this->school_id) : School::first());
        }

        return $this->school 
            ?: ($this->school_id ? School::find($this->school_id) : School::first());
    }

    public function getEffectiveTeacher(): ?Teacher
    {
        if ($this->teacher) {
            return $this->teacher;
        }

        if ($this->teacher_id) {
            return Teacher::find($this->teacher_id);
        }

        $school = $this->getEffectiveSchool();
        return $school?->teachers()->first();
    }
}

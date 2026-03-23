<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Twin extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'description',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($twin) {
            if (empty($twin->uuid)) {
                $twin->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function files(): HasMany
    {
        return $this->hasMany(TwinFile::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwinFile extends Model
{
    protected $fillable = [
        'twin_id',
        'filename',
        'filepath',
        'file_size',
        'status',
        'is_system_file',
        'processing_notes',
    ];

    protected $casts = [
        'is_system_file' => 'boolean',
    ];

    public function twin(): BelongsTo
    {
        return $this->belongsTo(Twin::class);
    }
}

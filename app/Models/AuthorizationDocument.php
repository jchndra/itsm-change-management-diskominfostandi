<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationDocument extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'change_request_id',
        'decision_id',
        'uploaded_by_id',
        'document_type',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum_sha256',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \BadMethodCallException('Dokumen otorisasi bersifat append-only.'));
        static::deleting(fn () => throw new \BadMethodCallException('Dokumen otorisasi bersifat append-only.'));
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}

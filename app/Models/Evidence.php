<?php

namespace App\Models;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evidence extends Model
{
    use HasFactory;

    protected $table = 'evidences';

    public $timestamps = false;

    protected $fillable = [
        'change_request_id',
        'release_task_id',
        'uploaded_by_id',
        'evidence_type',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum_sha256',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new BadMethodCallException('Berkas bukti rilis bersifat append-only.');
        });

        static::deleting(function () {
            throw new BadMethodCallException('Berkas bukti rilis bersifat append-only.');
        });
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    public function releaseTask(): BelongsTo
    {
        return $this->belongsTo(ReleaseTask::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}

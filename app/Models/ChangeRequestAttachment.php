<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRequestAttachment extends Model
{
    use HasFactory;

    public const TYPE_SUPPORTING = 'supporting';

    public const TYPE_APPROVAL_LETTER = 'approval_letter';

    protected $fillable = [
        'change_request_id',
        'uploaded_by_id',
        'document_type',
        'original_name',
        'path',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function scopeSupporting(Builder $query): Builder
    {
        return $query->where('document_type', self::TYPE_SUPPORTING);
    }

    public function scopeApprovalLetters(Builder $query): Builder
    {
        return $query->where('document_type', self::TYPE_APPROVAL_LETTER);
    }

    public function isApprovalLetter(): bool
    {
        return $this->document_type === self::TYPE_APPROVAL_LETTER;
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Decision extends Model
{
    use HasFactory;

    // Matikan pembaruan kolom updated_at karena tabel tidak memilikinya
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function changeRequest()
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    /**
     * Relasi legacy; keputusan baru menyimpan authority_id null.
     */
    public function authority()
    {
        return $this->belongsTo(Authority::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
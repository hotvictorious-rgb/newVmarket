<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnEvidence extends Model
{
    protected $table = 'return_evidence';

    protected $fillable = [
        'return_case_id',
        'uploaded_by_id',
        'uploaded_by_role',
        'file_path',
        'evidence_type',
        'caption',
    ];

    public function returnCase()
    {
        return $this->belongsTo(ReturnCase::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}

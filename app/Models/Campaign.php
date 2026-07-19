<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection_id',
        'name',
        'discount',
        'profit',
        'commission'
    ];

    public $timestamps = false;

    public function collection()
    {
        return $this->belongsTo(Collection::class, 'collection_id', 'ikas_category_id');
    }
}

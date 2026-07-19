<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use HasFactory;
    protected $guarded = [];
    public $timestamps = false;

    public function campaign()
    {
        return $this->hasOne(Campaign::class, 'collection_id', 'shopify_collection_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, null, null, null)
            ->using(function ($collection) {
                return json_decode($collection->product_list ?? '[]', true);
            });
    }
}

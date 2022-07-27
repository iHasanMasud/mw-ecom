<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Variant extends Model
{
    protected $fillable = ['title', 'description'];

    public function product_variant_price()
    {
        return $this->hasMany(\App\Models\ProductVariantPrice::class, 'product_id');
    }

    public function product_variants()
    {
        return $this->hasMany(\App\Models\ProductVariant::class);
    }

}

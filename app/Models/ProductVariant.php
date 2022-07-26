<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    public function variants()
    {
        return $this->belongsTo(\App\Models\Variant::class);
    }

    public function product_variant_prices()
    {
        return $this->hasMany(\App\Models\ProductVariantPrice::class, 'product_id');
    }

    public function product_variant_one()
    {
        return $this->hasMany(\App\Models\ProductVariantPrice::class);
    }

    public function product_variant_two()
    {
        return $this->hasMany(\App\Models\ProductVariantPrice::class);
    }

    public function product_variant_three()
    {
        return $this->hasMany(\App\Models\ProductVariantPrice::class);
    }
}

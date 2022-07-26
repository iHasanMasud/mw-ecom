<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantPrice extends Model
{
    public function product(){
        return $this->belongsTo(\App\Models\Product::class);
    }

    public function product_variant()
    {
        return $this->belongsTo(\App\Models\ProductVariant::class, 'product_id');
    }

    public function variant_one()
    {
        return $this->belongsTo(\App\Models\ProductVariant::class, 'product_variant_one');
    }

    public function variant_two()
    {
        return $this->belongsTo(\App\Models\ProductVariant::class, 'product_variant_two');
    }

    public function variant_three()
    {
        return $this->belongsTo(\App\Models\ProductVariant::class, 'product_variant_three');
    }
}

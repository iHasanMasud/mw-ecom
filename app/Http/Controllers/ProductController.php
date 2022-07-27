<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\Variant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     * Author: https://github.com/iHasanMasud
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        $date = $request->date;
        $price_from = $request->price_from;
        $price_to = $request->price_to;
        $variant = $request->variant;
        $title = $request->title;

        //DB::enableQueryLog(); // Enable query log
        $products = Product::when(request()->filled('date'), function ($query) use ($date) {
            return $query->whereDate('created_at', $date);
        })->when(request()->filled('title'), function ($query) use ($title) {
            return $query->where('title', "LIKE", "%" . $title . "%");
        })->when(request()->filled('price_from'), function ($query) use ($price_from) {
            return $query->where(function ($query1) use ($price_from) {
                $query1->whereHas('variants', function ($query2) use ($price_from) {
                    return $query2->where('price', '>=', $price_from);
                });
            });
        })->when(request()->filled('price_to'), function ($query) use ($price_to) {
            return $query->where(function ($query1) use ($price_to) {
                $query1->whereHas('variants', function ($query2) use ($price_to) {
                    return $query2->where('price', '<=', $price_to);
                });
            });
        })->when(request()->filled('variant'), function ($query) use ($variant) {
            return  $query->where(function ($query1) use ($variant) {
                $query1->whereHas('variants.variant_one', function ($query2) use ($variant) {
                    return $query2->where('id', $variant);
                })->orWhereHas('variants.variant_two', function ($query2) use ($variant) {
                    return $query2->where('id', $variant);
                })->orWhereHas('variants.variant_three', function ($query2) use ($variant) {
                    return $query2->where('id', $variant);
                });
            });
        })->with('variants')->paginate(5);
        //dd(DB::getQueryLog()); // Show results of log

        $variant_groups = $this->getVariantGroups();
        return view('products.index', compact('products', 'variant_groups'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function create()
    {
        $variants = Variant::all();
        return view('products.create', compact('variants'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => "required",
            'sku' => "required|unique:products,sku",
            'description' => "required",
            'product_image' => "required|array",
            'product_variant' => "required|array",
            'product_variant_prices' => "required|array",
        ]);

        try {
            DB::beginTransaction();
            $product = Product::create([
                'title' => $request->title,
                'sku' => $request->sku,
                'description' => $request->description,
            ]);
            $product->product_images()->createMany($request->product_image);
            $product->variants()->createMany($request->product_variant);
            $product->variants()->each(function ($variant) use ($request) {
                $variant->variant_prices()->createMany($request->product_variant_prices);
            });
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Product created successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function show($product)
    {

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function edit(Product $product)
    {
        $variants = Variant::all();
        return view('products.edit', compact('variants'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $product)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function destroy(Product $product)
    {
        //
    }

    /**
     * Get variant groups
     */

    public function getVariantGroups(){
        $variants = Variant::select('id', 'title')->get()->toArray();
        $i = 0;
        foreach($variants as $variant){
            $variants[$i]['variants'] = ProductVariant::select('variant_id','variant')
                                        ->where('variant_id', $variant['id'])
                                        ->get()->unique('variant')
                                        ->toArray();
            $i++;
        }
        return $variants;
    }


}

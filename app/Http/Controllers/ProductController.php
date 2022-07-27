<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\Variant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;

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
        })->with('variants')
            ->orderBy('id', 'desc')
            ->paginate(5);
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
            'title' => "required|string|max:255",
            'sku' => "required|unique:products,sku|string|max:255",
            'description' => "required",
            'product_image' => "required|array",
            'product_variant' => "required|array|min:1",
            'product_variant_prices' => "required|array|min:1",
        ]);

        try {
            DB::beginTransaction();
            $product = Product::create([
                'title' => $request->title,
                'sku' => $request->sku,
                'description' => $request->description,
            ]);
            // store product variants in an array to use to store product variant price
            $product_variants = [];
            foreach ($request->product_variant as $key => $variant) {
                foreach ($variant['tags'] as $tag) {
                    $productVariant = new ProductVariant();
                    $productVariant->variant_id = $variant['option'];
                    $productVariant->variant = $tag;
                    $productVariant->product_id = $product->id;
                    $productVariant->save();
                    $product_variants[$key][$tag] = $productVariant;
                }
            }

            // store product variant price
            foreach ($request->product_variant_prices as $product_variant_price) {
                $productVariantPrice = new ProductVariantPrice();
                $productVariantPrice->stock = $product_variant_price['stock'];
                $productVariantPrice->price = $product_variant_price['price'];
                $productVariantPrice->product_id = $product->id;
                foreach (explode('/', $product_variant_price['title']) as $key => $variant) {
                    if ($key === 0) {
                        $productVariantPrice->product_variant_one = $product_variants[$key][$variant]->id ?? null;
                    } elseif ($key === 1) {
                        $productVariantPrice->product_variant_two = $product_variants[$key][$variant]->id ?? null;
                    } elseif ($key === 2) {
                        $productVariantPrice->product_variant_three = $product_variants[$key][$variant]->id ?? null;
                    }
                }
                $productVariantPrice->save();
            }

            // product images store
            foreach ($request->product_image as $file) {
                $photo = Image::make($file);
                $image_parts = explode(";base64,", $file);
                $image_extension = explode("image/", $image_parts[0]);
                $file_name = Str::random(16) . '.' . $image_extension[1];

                if (!is_dir(storage_path("app/public/product-images"))) {
                    mkdir(storage_path("app/public/product-images"), 0775, true);
                }
                /*if (!is_dir(storage_path("app/public/product-thumbnails"))) {
                    mkdir(storage_path("app/public/product-thumbnails"), 0775, true);
                }*/
                $photo->resize(50, 50)->save(storage_path('app/public/product-images/' . $file_name), 100);
                //$photo->resize(50, 50)->save(storage_path('app/public/product-thumbnails/' . $file_name), 100);

                $productImage = new ProductImage();
                $productImage->product_id = $product->id;
                $productImage->file_path = $file_name;
                $productImage->thumbnail = null;
                $productImage->save();
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Product created successfully.']);
        } catch (\Exception $e) {
            Log::emergency("File:" . $e->getFile() . " Line:" . $e->getLine() . " Message:" . $e->getMessage());
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Something went wrong'], 500);
        }
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
        $product->load(['images', 'variants', 'variants.variant_one', 'variants.variant_two', 'variants.variant_three'])->get();
        return view('products.edit', compact('variants', 'product'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Product $product)
    {
        $request->validate([
            'title' => "required|string|max:255",
            'sku' => "required|unique:products,sku," . $product->id,
            'description' => "required|max:255",
            'product_variant' => "required|array|min:1",
            'product_variant_prices' => "required|array|min:1",
        ]);

        // Delete previous product variant data
        $product->load([
            'variants.variant_one' => function ($query) {
                $query->delete();
            },
            'variants.variant_two' => function ($query) {
                $query->delete();
            },
            'variants.variant_three' => function ($query) {
                $query->delete();
            },
            'variants' => function ($query) {
                $query->delete();
            }
        ]);

        // update product data
        $product->title = $request->title;
        $product->sku = $request->sku;
        $product->description = $request->description;
        $product->save();

        // Store product variants
        $product_variants = [];
        foreach ($request->product_variant as $key => $variant) {
            foreach ($variant['tags'] as $tag) {
                $productVariant = new ProductVariant();
                $productVariant->variant_id = $variant['option'];
                $productVariant->variant = $tag;
                $productVariant->product_id = $product->id;
                $productVariant->save();
                $product_variants[$key][$tag] = $productVariant;
            }
        }

        // store product variant price
        foreach ($request->product_variant_prices as $product_variant_price) {
            $productVariantPrice = new ProductVariantPrice();
            $productVariantPrice->stock = $product_variant_price['stock'];
            $productVariantPrice->price = $product_variant_price['price'];
            $productVariantPrice->product_id = $product->id;
            foreach (explode('/', $product_variant_price['title']) as $key => $variant) {
                if ($key === 0) {
                    $productVariantPrice->product_variant_one = $product_variants[$key][$variant]->id ?? null;
                } elseif ($key === 1) {
                    $productVariantPrice->product_variant_two = $product_variants[$key][$variant]->id ?? null;
                } elseif ($key === 2) {
                    $productVariantPrice->product_variant_three = $product_variants[$key][$variant]->id ?? null;
                }
            }
            $productVariantPrice->save();
        }
        // product images store
        foreach ($request->product_image as $file) {
            $photo = Image::make($file);
            $image_parts = explode(";base64,", $file);
            $image_extension = explode("image/", $image_parts[0]);
            $file_name = Str::random(16) . '.' . $image_extension[1];
            if (!is_dir(storage_path("app/public/product-images"))) {
                mkdir(storage_path("app/public/product-images"), 0775, true);
            }
            $photo->resize(50, 50)->save(storage_path('app/public/product-images/' . $file_name), 100);


            $productImage = new ProductImage();
            $productImage->product_id = $product->id;
            $productImage->file_path = $file_name;
            $productImage->thumbnail = null;
            $productImage->save();
        }

        return response()->json(['message' => "Product updated successfully."], 200);
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

    /**
     * Delete product image
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteImage($file_name)
    {
        if (file_exists(storage_path('app/public/product-images/' . $file_name))) {
            unlink(storage_path('app/public/product-images/' . $file_name));
        }
        ProductImage::where('file_path', $file_name)->delete();
        return response()->json(['message' => "Successfully deleted image."]);
    }

}

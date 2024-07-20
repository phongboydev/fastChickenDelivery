<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use App\Traits\ResponseTraits;

class ProductController
{
    use  ResponseTraits;

    public function index()
    {
        $request = request()->all();

        $products = Product::query();
        // Get param by condition

        // Search
        if(isset($request['q'])) {
            $products->where('name', 'like', '%'.$request['q'].'%');
        }

        // Status
        if(isset($request['status'])) {
            $products->where('status', $request['status']);
        }

        // Sort
        if(isset($request['sortBy'])) {
            $products->orderBy($request['sortBy'], $request['orderBy']);
        }

        // Pagination
        $products = $products->paginate($request['itemsPerPage'], ['*'], 'page', $request['page']);

        return $this->responseData(200, 'Success', $products);
    }

    public function show($id)
    {
        $product = Product::find($id);
        if($product) {
            return $this->responseData(200, 'Success', $product);
        }
        return $this->responseData(404, 'Product not found');
    }

    public function update($id)
    {
        $request = request()->all();
        $product = Product::find($id);
        if($product) {
            $product->update($request);
            return $this->responseData(200, 'Product updated', $product);
        }
        return $this->responseData(404, 'Product not found');
    }

    public function store()
    {
        $request = request()->all();
        $product = new Product($request);
        if($product->save()) {
            return $this->responseData(200, 'Product created', $product);
        }
        return $this->responseData(500, 'Product not created');
    }

    public function destroy($id)
    {
        $product = Product::find($id);
        if($product) {
            $product->delete();
            return $this->responseData(200, 'Product deleted');
        }
        return $this->responseData(404, 'Product not found');
    }

    public function getProductsByCategory($id)
    {
        $products = Product::where('category_id', $id)->get();
        return $this->responseData(200, 'Success', $products);
    }

    public function getProducts()
    {
        $products = Product::selectRaw('name as title, id as value')->get()->toArray();

        logger($products);
        return $this->responseData(200, 'Success', $products);
    }
}

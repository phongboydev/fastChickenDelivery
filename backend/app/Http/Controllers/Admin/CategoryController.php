<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Traits\ResponseTraits;

class CategoryController
{
    use  ResponseTraits;
    public function index()
    {
        $request = request()->all();

        $users = Category::query();
        // Get param by condition

        // Search
        if(isset($request['q'])) {
            $users->where('name', 'like', '%'.$request['q'].'%');
        }

        // Status
        if(isset($request['status'])) {
            $users->where('status', $request['status']);
        }

        // Sort
        if(isset($request['sortBy'])) {
            $users->orderBy($request['sortBy'], $request['orderBy']);
        }

        // Pagination
        $users = $users->paginate($request['itemsPerPage'], ['*'], 'page', $request['page']);

        return $this->responseData(200, 'Success', $users);
    }

    public function show($id)
    {
        $user = Category::find($id);
        if($user) {
            return $this->responseData(200, 'Success', $user);
        }
        return $this->responseData(404, 'Category not found');
    }

    public function update($id)
    {
        $request = request()->all();
        $category = Category::find($id);
        if($category) {
            $category->update($request);
            return $this->responseData(200, 'Category updated', $category);
        }
        return $this->responseData(404, 'Category not found');
    }

    public function store()
    {
        $request = request()->all();
        $category = new Category($request);
        if($category->save()) {
            return $this->responseData(201, 'Category created', $category);
        }
        return $this->responseData(400, 'Category not created');
    }

    public function destroy($id)
    {
        $category = Category::find($id);
        if($category) {
            $category->delete();
            return $this->responseData(200, 'Category deleted');
        }
        return $this->responseData(404, 'Category not found');
    }

    public function getCategories()
    {
        $categories = Category::all();
        return $this->responseData(200, 'Success', $categories);
    }

    public function changeStatus($id)
    {
        $category = Category::find($id);
        if($category) {
            $category->update(['status' => !$category->status]);
            return $this->responseData(200, 'Category status changed', $category);
        }
        return $this->responseData(404, 'Category not found');
    }
}

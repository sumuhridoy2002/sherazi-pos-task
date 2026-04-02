<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $tenant = app('tenant'); # current tenant
        $page = $request->input('page', 1);

        $products = Cache::remember("tenant.{$tenant->id}.products.page.$page", 60, function () {
            return Product::with('category:id,name')
                ->paginate(10, ['id','name','price','stock','category_id']);
        });

        return ProductResource::collection($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id'
        ]);

        $product = Product::create($request->all());

        $tenant = app('tenant');
        Cache::forget("tenant.{$tenant->id}.products.page.1");
        Cache::forget("tenant.{$tenant->id}.dashboard.data");

        return ProductResource::collection(collect([$product]))->response()->setStatusCode(201);
    }

    public function search(Request $request)
    {
        $keyword = $request->input('q');
        $tenant = app('tenant');
        $page = $request->input('page', 1);

        $products = Cache::remember("tenant.{$tenant->id}.search.$keyword.page.$page", 30, function () use ($keyword) {
            return Product::whereFullText('name', $keyword)
                ->orWhereFullText('description', $keyword)
                ->paginate(10);
        });

        return ProductResource::collection($products);
    }

    public function dashboard()
    {
        $tenant = app('tenant');

        $data = Cache::remember("tenant.{$tenant->id}.dashboard.data", 60, function () {
            return [
                'total_products' => Product::count(),
                'total_orders'   => Order::count(),
                'total_revenue'  => Order::sum('total_amount'),
                'categories'     => Category::all(['id','name']),
                'top_products'   => Product::orderByDesc('sold_count')->take(5)->get(['id','name','sold_count'])
            ];
        });

        return response()->json($data);
    }
}
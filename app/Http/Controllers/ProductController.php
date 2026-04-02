<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index()
    {
        $result = Cache::remember('products.index', 60, function () {
            return Product::with('category')->get()->map(function ($product) {
                return [
                    'id'       => $product->id,
                    'name'     => $product->name,
                    'price'    => $product->price,
                    'stock'    => $product->stock,
                    'category' => $product->category?->name
                ];
            });
        });

        return response()->json($result);
    }

    public function salesReport()
    {
        $report = Cache::remember('report.sales', 120, function () {
            $orders = Order::with('items.product', 'customer')->get();

            $data = [];
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $data[] = [
                        'order_id'     => $order->id,
                        'product_name' => $item->product?->name,
                        'qty'          => $item->quantity,
                        'total'        => $item->quantity * $item->product?->price,
                        'customer'     => $order->customer?->name
                    ];
                }
            }
            return $data;
        });

        return response()->json($report);
    }

    public function dashboard()
    {
        $data = Cache::remember('dashboard.data', 60, function () {
            return [
                'total_products' => Product::count(),
                'total_orders'   => Order::count(),
                'total_revenue'  => Order::sum('total_amount'),
                'categories'     => Category::all(),
                'top_products'   => Product::orderByDesc('sold_count')->take(5)->get()
            ];
        });

        return response()->json($data);
    }

    public function search(Request $request)
    {
        $keyword = $request->input('q');

        $products = Cache::remember("search.$keyword", 30, function () use ($keyword) {
            return Product::where('name', 'LIKE', "%$keyword%")
                ->orWhere('description', 'LIKE', "%$keyword%")
                ->get();
        });

        return response()->json($products);
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

        Cache::forget('products.index');
        Cache::forget('dashboard.data');

        return response()->json($product, 201);
    }
}
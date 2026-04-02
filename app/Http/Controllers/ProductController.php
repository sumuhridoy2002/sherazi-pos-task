<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = 10;
        $cacheKey = "products.page.$page";

        $products = Cache::remember($cacheKey, 60, function () use ($perPage) {
            return Product::with('category:id,name')
                ->select(['id', 'name', 'price', 'stock', 'category_id'])
                ->paginate($perPage);
        });

        return ProductResource::collection($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
        ]);

        $product = Product::create($request->all());

        Cache::forget('products.page.1');
        Cache::forget('dashboard.data');

        return ProductResource::collection(collect([$product]))->response()->setStatusCode(201);
    }

    public function search(Request $request)
    {
        $keyword = $request->input('q');
        $page = $request->input('page', 1);

        $products = Cache::remember("search.$keyword.page.$page", 30, function () use ($keyword) {
            return Product::whereFullText('name', $keyword)
                ->orWhereFullText('description', $keyword)
                ->paginate(10);
        });

        return ProductResource::collection($products);
    }

    public function dashboard()
    {
        $data = Cache::remember('dashboard.data', 60, function () {
            return [
                'total_products' => Product::count(),
                'total_orders' => Order::count(),
                'total_revenue' => Order::sum('total_amount'),
                'categories' => Category::all(['id', 'name']),
                'top_products' => Product::orderByDesc('sold_count')->take(5)->get(['id', 'name', 'sold_count']),
            ];
        });

        return response()->json($data);
    }

    public function salesReport()
    {
        $data = Cache::remember('sales.report', 60, function () {
            $summaryByStatus = Order::query()
                ->selectRaw('status, COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as revenue')
                ->groupBy('status')
                ->get()
                ->map(fn ($row) => [
                    'status' => $row->status,
                    'order_count' => (int) $row->order_count,
                    'revenue' => (float) $row->revenue,
                ]);

            $topByRevenue = OrderItem::query()
                ->selectRaw('product_id, SUM(quantity * unit_price) as revenue, SUM(quantity) as units_sold')
                ->whereHas('order', fn ($q) => $q->where('status', 'completed'))
                ->groupBy('product_id')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get();

            $topByRevenue->load(['product:id,name']);

            return [
                'summary_by_status' => $summaryByStatus,
                'top_products_by_revenue' => $topByRevenue->map(fn ($row) => [
                    'product_id' => $row->product_id,
                    'product_name' => $row->product?->name,
                    'revenue' => (float) $row->revenue,
                    'units_sold' => (int) $row->units_sold,
                ]),
            ];
        });

        return response()->json($data);
    }
}

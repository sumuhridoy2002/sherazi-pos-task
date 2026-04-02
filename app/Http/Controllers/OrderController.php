<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items'       => 'required|array'
        ]);

        DB::beginTransaction();

        try {
            $totalAmount = 0;

            $order = Order::create([
                'customer_id'  => $request->customer_id,
                'total_amount' => 0,
                'status'       => 'pending'
            ]);

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                if (!$product || $product->stock < $item['quantity']) {
                    DB::rollBack();
                    return response()->json(['error' => 'Product unavailable'], 422);
                }

                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'unit_price' => $product->price
                ]);

                $product->decrement('stock', $item['quantity']);
                $totalAmount += $product->price * $item['quantity'];
            }

            $order->update(['total_amount' => $totalAmount]);
            DB::commit();

            $tenant = app('tenant');
            Cache::forget("tenant.{$tenant->id}.orders.page.1");

            return OrderResource::collection(collect([$order]))->response()->setStatusCode(201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function index(Request $request)
    {
        $tenant = app('tenant');
        $page = $request->input('page', 1);

        $orders = Cache::remember("tenant.{$tenant->id}.orders.page.$page", 60, function () {
            return Order::with('items.product:id,name,price','customer:id,name')
                ->paginate(10, ['id','customer_id','status','total_amount','created_at']);
        });

        return OrderResource::collection($orders);
    }

    public function filterByStatus(Request $request)
    {
        $status = $request->input('status');
        $tenant = app('tenant');
        $page = $request->input('page', 1);

        $orders = Cache::remember("tenant.{$tenant->id}.orders.status.$status.page.$page", 60, function () use ($status) {
            return Order::with('items.product:id,name,price','customer:id,name')
                ->where('status', $status)
                ->paginate(10, ['id','customer_id','status','total_amount','created_at']);
        });

        return OrderResource::collection($orders);
    }
}
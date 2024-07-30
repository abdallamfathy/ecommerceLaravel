<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1'
        ]);

        DB::beginTransaction();

        try {
            $totalPrice = 0;
            foreach ($validatedData['products'] as $product) {
                $productModel = Product::find($product['product_id']);
                $totalPrice += $productModel->price * $product['quantity'];
            }

            $order = Order::create([
                'user_id' => $validatedData['user_id'] ?? null,
                'total_price' => $totalPrice,
                'status' => 'pending',
            ]);

            foreach ($validatedData['products'] as $product) {
                $order->products()->attach($product['product_id'], [
                    'quantity' => $product['quantity'],
                    'price' => Product::find($product['product_id'])->price,
                ]);

                // Update product quantity
                $productModel->quantity -= $product['quantity'];
                if ($productModel->quantity < 0) {
                    throw new \Exception("Insufficient quantity for product ID: {$product['product_id']}");
                }
                $productModel->save();
            }

            DB::commit();

            return response()->json($order->load('products'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'An unexpected error occurred.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}

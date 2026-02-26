<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CryptoBalanceService;
use Illuminate\Http\Request;

class CryptoBalanceController extends Controller
{
    public function __construct(
        protected CryptoBalanceService $balanceService
    ) {}

    public function credit(Request $request)
    {
        $request->validate([
            'currency' => 'required|in:BTC,ETH,USDT',
            'amount' => 'required|regex:/^\d+\.?\d*$/', // Только числа и точка
            'tx_hash' => 'nullable|string',
            'description' => 'nullable|string'
        ]);

        try {
            $transaction = $this->balanceService->credit(
                auth()->id(),
                $request->currency,
                $request->amount, // Строка!
                $request->only(['tx_hash', 'description', 'fee'])
            );

            return response()->json([
                'success' => true,
                'transaction' => [
                    'id' => $transaction->id,
                    'uuid' => $transaction->uuid,
                    'amount_btc' => $transaction->amount_btc,
                    'status' => $transaction->status
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function debit(Request $request)
    {
        $request->validate([
            'currency' => 'required|in:BTC,ETH,USDT',
            'amount' => 'required|regex:/^\d+\.?\d*$/',
            'type' => 'required|in:withdrawal,payment,fee',
            'to_address' => 'required_if:type,withdrawal|string',
            'description' => 'nullable|string'
        ]);

        try {
            $transaction = $this->balanceService->debit(
                auth()->id(),
                $request->currency,
                $request->amount,
                $request->type,
                $request->only(['to_address', 'description', 'fee'])
            );

            return response()->json([
                'success' => true,
                'transaction' => [
                    'id' => $transaction->id,
                    'uuid' => $transaction->uuid,
                    'amount_btc' => $transaction->amount_btc,
                    'status' => $transaction->status
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
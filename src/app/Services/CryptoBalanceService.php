<?php

namespace App\Services;

use App\Models\CryptoBalance;
use App\Models\CryptoTransaction;
use App\Jobs\ProcessWithdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class CryptoBalanceService
{
    /**
     * Конвертирует BTC в сатоши
     */
    protected function btcToSatoshi(string $btc): int
    {
        return (int) bcmul($btc, '100000000', 0);
    }
    
    /**
     * Зачисление средств
     */
    public function credit(
        int $userId, 
        string $currency, 
        string $amountBtc,  // ВСЕГДА строка!
        array $meta = []
    ): CryptoTransaction {
        $amountSatoshi = $this->btcToSatoshi($amountBtc);
        
        return DB::transaction(function () use ($userId, $currency, $amountSatoshi, $amountBtc, $meta) {
            $balance = CryptoBalance::firstOrCreate(
                ['user_id' => $userId, 'currency' => $currency],
                ['balance_satoshi' => 0, 'locked_satoshi' => 0]
            );
            
            $balanceBefore = $balance->balance_satoshi;
            $lockedBefore = $balance->locked_satoshi;
            
            // Увеличиваем баланс
            $balance->balance_satoshi += $amountSatoshi;
            $balance->save();
            
            $transaction = CryptoTransaction::create([
                'user_id' => $userId,
                'crypto_balance_id' => $balance->id,
                'uuid' => (string) Str::uuid(),
                'currency' => $currency,
                'type' => 'credit',
                'status' => $meta['instant'] ?? false ? 'completed' : 'pending',
                'amount_satoshi' => $amountSatoshi,
                'fee_satoshi' => $this->btcToSatoshi($meta['fee'] ?? '0'),
                'balance_before_satoshi' => $balanceBefore,
                'balance_after_satoshi' => $balance->balance_satoshi,
                'locked_before_satoshi' => $lockedBefore,
                'locked_after_satoshi' => $balance->locked_satoshi,
                'blockchain_tx_hash' => $meta['tx_hash'] ?? null,
                'from_address' => $meta['from'] ?? null,
                'description' => $meta['description'] ?? 'Credit of funds'
            ]);
            
            // Если нужна проверка в блокчейне
            if (!empty($meta['tx_hash']) && !($meta['instant'] ?? false)) {
                \App\Jobs\CheckTransactionConfirmations::dispatch($transaction);
            }
            
            return $transaction;
        });
    }
    
    /**
     * Списание средств
     */
    public function debit(
        int $userId, 
        string $currency, 
        string $amountBtc,  // ВСЕГДА строка!
        string $type, 
        array $meta = []
    ): CryptoTransaction {
        $allowedTypes = ['withdrawal', 'payment', 'fee'];
        if (!in_array($type, $allowedTypes)) {
            throw new \InvalidArgumentException('Недопустимый тип списания');
        }
        
        $amountSatoshi = $this->btcToSatoshi($amountBtc);
        $feeSatoshi = $this->btcToSatoshi($meta['fee'] ?? '0');
        
        return DB::transaction(function () use ($userId, $currency, $amountSatoshi, $feeSatoshi, $type, $meta) {
            $balance = CryptoBalance::where('user_id', $userId)
                ->where('currency', $currency)
                ->firstOrFail();
            
            $available = $balance->balance_satoshi - $balance->locked_satoshi;
            if ($available < $amountSatoshi + $feeSatoshi) {
                throw new \Exception('Недостаточно средств');
            }
            
            $balanceBefore = $balance->balance_satoshi;
            $lockedBefore = $balance->locked_satoshi;
            
            // Блокируем средства
            $balance->locked_satoshi += ($amountSatoshi + $feeSatoshi);
            $balance->save();
            
            $transaction = CryptoTransaction::create([
                'user_id' => $userId,
                'crypto_balance_id' => $balance->id,
                'uuid' => (string) Str::uuid(),
                'currency' => $currency,
                'type' => $type,
                'status' => 'pending',
                'amount_satoshi' => $amountSatoshi,
                'fee_satoshi' => $feeSatoshi,
                'balance_before_satoshi' => $balanceBefore,
                'balance_after_satoshi' => $balanceBefore - $amountSatoshi - $feeSatoshi,
                'locked_before_satoshi' => $lockedBefore,
                'locked_after_satoshi' => $balance->locked_satoshi,
                'to_address' => $meta['to'] ?? null,
                'description' => $meta['description'] ?? ucfirst($type) . ' of funds'
            ]);
            
            if ($type === 'withdrawal') {
                ProcessWithdrawal::dispatch($transaction);
            }
            
            return $transaction;
        });
    }
    
    /**
     * Подтверждение списания
     */
    public function confirmDebit(CryptoTransaction $transaction): CryptoTransaction
    {
        return DB::transaction(function () use ($transaction) {
            $balance = $transaction->cryptoBalance;
            
            // Списываем заблокированные средства
            $totalSatoshi = $transaction->amount_satoshi + $transaction->fee_satoshi;
            
            $balance->locked_satoshi -= $totalSatoshi;
            $balance->balance_satoshi -= $totalSatoshi;
            $balance->save();
            
            $transaction->status = 'completed';
            $transaction->confirmed_at = Carbon::now();
            $transaction->save();
            
            return $transaction;
        });
    }
    
    /**
     * Отмена списания
     */
    public function cancelDebit(CryptoTransaction $transaction, string $reason): CryptoTransaction
    {
        return DB::transaction(function () use ($transaction, $reason) {
            $balance = $transaction->cryptoBalance;
            
            // Разблокируем средства
            $totalSatoshi = $transaction->amount_satoshi + $transaction->fee_satoshi;
            $balance->locked_satoshi -= $totalSatoshi;
            $balance->save();
            
            $transaction->status = 'failed';
            $transaction->fail_reason = $reason;
            $transaction->save();
            
            return $transaction;
        });
    }
    
    /**
     * Подтверждение зачисления
     */
    public function confirmCredit(CryptoTransaction $transaction): CryptoTransaction
    {
        return DB::transaction(function () use ($transaction) {
            $transaction->status = 'completed';
            $transaction->confirmed_at = Carbon::now();
            $transaction->save();
            
            return $transaction;
        });
    }
    
    /**
     * Отмена зачисления
     */
    public function failCredit(CryptoTransaction $transaction, string $reason): CryptoTransaction
    {
        return DB::transaction(function () use ($transaction, $reason) {
            $balance = $transaction->cryptoBalance;
            
            // Откатываем баланс
            $balance->balance_satoshi -= $transaction->amount_satoshi;
            $balance->save();
            
            $transaction->status = 'failed';
            $transaction->fail_reason = $reason;
            $transaction->save();
            
            return $transaction;
        });
    }
}
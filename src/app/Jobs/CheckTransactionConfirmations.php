<?php
namespace App\Jobs;

use App\Models\CryptoTransaction;
use App\Services\CryptoBalanceService;
use App\Services\BlockchainService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckTransactionConfirmations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 20;        // Максимум 20 попыток
    public $backoff = 60;      // Проверяем каждую минуту
    public $timeout = 30;      // Таймаут 30 секунд

    protected CryptoTransaction $transaction;

    public function __construct(CryptoTransaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function handle(
        CryptoBalanceService $balanceService,
        BlockchainService $blockchain
    ): void {
        try {
            // Проверяем, что транзакция еще в обработке
            if ($this->transaction->status !== 'pending') {
                Log::info('Transaction already processed', [
                    'id' => $this->transaction->id,
                    'status' => $this->transaction->status
                ]);
                return;
            }

            // Если нет хеша транзакции, считаем что это внутренняя операция
            if (!$this->transaction->blockchain_tx_hash) {
                if ($this->transaction->type === 'credit') {
                    $balanceService->confirmCredit($this->transaction);
                }
                return;
            }

            // Получаем статус из блокчейна
            $status = $blockchain->checkTransactionStatus(
                $this->transaction->blockchain_tx_hash  // Только один аргумент
            );

            // Логируем результат проверки
            Log::debug('Blockchain status check', [
                'tx_id' => $this->transaction->id,
                'hash' => $this->transaction->blockchain_tx_hash,
                'status' => $status
            ]);

            // Обновляем количество подтверждений
            $this->transaction->confirmations = $status['confirmations'] ?? 0;
            $this->transaction->save();

            // Анализируем результат
            if ($status['status'] === 'confirmed' && $status['success']) {
                // Транзакция подтверждена успешно
                if ($this->transaction->type === 'credit') {
                    $balanceService->confirmCredit($this->transaction);
                } else {
                    $balanceService->confirmDebit($this->transaction);
                }

                Log::info('Transaction confirmed', [
                    'id' => $this->transaction->id,
                    'confirmations' => $status['confirmations']
                ]);

            } elseif ($status['status'] === 'confirmed' && !$status['success']) {
                // Транзакция провалилась в блокчейне
                $reason = 'Blockchain transaction failed';
                
                if ($this->transaction->type === 'credit') {
                    $balanceService->failCredit($this->transaction, $reason);
                } else {
                    $balanceService->cancelDebit($this->transaction, $reason);
                }

                Log::error('Transaction failed in blockchain', [
                    'id' => $this->transaction->id
                ]);

            } else {
                // Еще ждем подтверждений
                $required = $this->getRequiredConfirmations();
                
                Log::info('Waiting for confirmations', [
                    'id' => $this->transaction->id,
                    'current' => $status['confirmations'],
                    'required' => $required
                ]);

                if ($this->attempts() < $this->tries) {
                    // Пробуем снова через backoff секунд
                    $this->release($this->backoff);
                } else {
                    // Исчерпали все попытки
                    $reason = "Max confirmations check attempts exceeded. Current: {$status['confirmations']}, Required: {$required}";
                    
                    if ($this->transaction->type === 'credit') {
                        $balanceService->failCredit($this->transaction, $reason);
                    } else {
                        $balanceService->cancelDebit($this->transaction, $reason);
                    }

                    Log::error('Max confirmation attempts exceeded', [
                        'id' => $this->transaction->id,
                        'attempts' => $this->attempts()
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Error checking transaction confirmations', [
                'id' => $this->transaction->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // В случае ошибки пробуем снова
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff);
            } else {
                // Если все попытки исчерпаны, помечаем как failed
                $reason = 'Error checking confirmations: ' . $e->getMessage();
                
                if ($this->transaction->type === 'credit') {
                    $balanceService->failCredit($this->transaction, $reason);
                } else {
                    $balanceService->cancelDebit($this->transaction, $reason);
                }
            }
        }
    }

    /**
     * Получить необходимое количество подтверждений для валюты
     */
    protected function getRequiredConfirmations(): int
    {
        return config(
            "blockchain.currencies.{$this->transaction->currency}.confirmations",
            config('blockchain.default_confirmations', 12)
        );
    }

    /**
     * Обработка неудачной джобы
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('CheckTransactionConfirmations job failed permanently', [
            'transaction_id' => $this->transaction->id,
            'error' => $exception->getMessage()
        ]);
    }
}
<?php

namespace App\Jobs;

use App\Models\CryptoTransaction;
use App\Services\CryptoBalanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWithdrawal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;
    public $backoff = 60;

    protected CryptoTransaction $transaction;

    public function __construct(CryptoTransaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function handle(CryptoBalanceService $balanceService): void
    {
        try {
            if ($this->transaction->status !== 'pending') {
                return;
            }

            // Имитация отправки в блокчейн
            $success = $this->sendToBlockchain();
            
            if ($success) {
                $balanceService->confirmDebit($this->transaction);
                Log::info('Withdrawal confirmed', ['tx_id' => $this->transaction->id]);
            } else {
                throw new \Exception('Blockchain send failed');
            }

        } catch (\Exception $e) {
            Log::error('Withdrawal failed', [
                'tx_id' => $this->transaction->id,
                'error' => $e->getMessage()
            ]);

            if ($this->attempts() >= $this->tries) {
                $balanceService->cancelDebit($this->transaction, $e->getMessage());
            } else {
                $this->release($this->backoff);
            }
        }
    }

    protected function sendToBlockchain(): bool
    {
        // Здесь реальная интеграция с блокчейном
        sleep(1);
        return rand(1, 100) <= 95; // 95% успеха для тестов
    }
}
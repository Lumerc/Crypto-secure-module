<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CryptoBalance extends Model
{
    protected $table = 'crypto_balances';
    
    protected $fillable = [
        'user_id',
        'currency',
        'balance_satoshi',
        'locked_satoshi'
    ];
    
    // Конвертеры для удобства
    public function getBalanceBtcAttribute(): string
    {
        if ($this->currency !== 'BTC') {
            return (string) $this->balance_satoshi;
        }
        return bcdiv((string)$this->balance_satoshi, '100000000', 8);
    }
    
    public function getLockedBtcAttribute(): string
    {
        if ($this->currency !== 'BTC') {
            return (string) $this->locked_satoshi;
        }
        return bcdiv((string)$this->locked_satoshi, '100000000', 8);
    }
    
    public function getAvailableSatoshiAttribute(): int
    {
        return $this->balance_satoshi - $this->locked_satoshi;
    }
    
    public function getAvailableBtcAttribute(): string
    {
        return bcdiv((string)$this->available_satoshi, '100000000', 8);
    }
    
    // Блокировка средств
    public function lock(int $amountSatoshi): void
    {
        if ($amountSatoshi > $this->available_satoshi) {
            throw new \Exception('Недостаточно средств для блокировки');
        }
        $this->locked_satoshi += $amountSatoshi;
        $this->save();
    }
    
    public function unlock(int $amountSatoshi): void
    {
        $this->locked_satoshi -= $amountSatoshi;
        $this->save();
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function transactions()
    {
        return $this->hasMany(CryptoTransaction::class);
    }
}
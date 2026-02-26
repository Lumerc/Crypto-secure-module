<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CryptoTransaction extends Model
{
    protected $table = 'crypto_transactions';
    
    protected $fillable = [
        'user_id',
        'crypto_balance_id',
        'uuid',
        'currency',
        'type',
        'status',
        'amount_satoshi',
        'fee_satoshi',
        'balance_before_satoshi',
        'balance_after_satoshi',
        'locked_before_satoshi',
        'locked_after_satoshi',
        'blockchain_tx_hash',
        'from_address',
        'to_address',
        'description',
        'confirmations',
        'fail_reason',
        'confirmed_at'
    ];
    
    protected $casts = [
        'confirmed_at' => 'datetime'
    ];
    
    // Конвертеры
    public function getAmountBtcAttribute(): string
    {
        return bcdiv((string)$this->amount_satoshi, '100000000', 8);
    }
    
    public function getFeeBtcAttribute(): string
    {
        return bcdiv((string)$this->fee_satoshi, '100000000', 8);
    }
    
    // Скоупы
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }
    
    // Проверки
    public function hasEnoughConfirmations(): bool
    {
        $required = config("blockchain.currencies.{$this->currency}.confirmations", 12);
        return $this->confirmations >= $required;
    }
    
    // Отношения
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function cryptoBalance()
    {
        return $this->belongsTo(CryptoBalance::class);
    }
}
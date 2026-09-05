<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'type', 'currency',
        'initial_balance', 'current_balance',
        'bank_name', 'account_number', 'card_number',
        'color', 'icon', 'is_active', 'is_archived', 'notes',
    ];

    protected $casts = [
        'initial_balance' => 'integer',
        'current_balance' => 'integer',
    ];

    protected $castsArray = [
        'initial_balance', 'current_balance',
    ];

    public $timestamps = true;
    public $softDeletes = true;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    public function isBank(): bool
    {
        return $this->type === 'bank';
    }

    public function isCash(): bool
    {
        return $this->type === 'cash';
    }

    public function isWallet(): bool
    {
        return $this->type === 'wallet';
    }

    public function isCreditCard(): bool
    {
        return $this->type === 'credit_card';
    }

    public function isInvestment(): bool
    {
        return $this->type === 'investment';
    }

    public function getBalanceAttribute(): int
    {
        return (int) $this->getRawOriginal('current_balance');
    }

    public function addAmount(int $amount): self
    {
        $this->current_balance += $amount;
        return $this;
    }

    public function subtractAmount(int $amount): self
    {
        $this->current_balance -= $amount;
        return $this;
    }

    public function deposit(int $amount): self
    {
        return $this->addAmount($amount);
    }

    public function withdraw(int $amount): self
    {
        return $this->subtractAmount($amount);
    }

    public function getInitialBalanceAttribute(): int
    {
        return (int) $this->getRawOriginal('initial_balance');
    }
}
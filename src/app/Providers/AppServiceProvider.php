<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Jobs\ProcessWithdrawal;  // Добавьте импорт

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Если здесь есть вызов ProcessWithdrawal::dispatch() - УДАЛИТЕ ЕГО!
        // Или замените на правильный вызов с параметром
    }
}
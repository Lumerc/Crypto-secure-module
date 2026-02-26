<?php


return [
    /*
    |--------------------------------------------------------------------------
    | Blockchain RPC Connections
    |--------------------------------------------------------------------------
    */
    'rpc_url' => env('BLOCKCHAIN_RPC_URL', 'https://mainnet.infura.io/v3/your-project-id'),
    'network' => env('BLOCKCHAIN_NETWORK', 'ethereum'),
    
    /*
    |--------------------------------------------------------------------------
    | Default Confirmations
    |--------------------------------------------------------------------------
    | Количество подтверждений по умолчанию для разных валют
    */
    'default_confirmations' => 12,
    
    /*
    |--------------------------------------------------------------------------
    | Currency Specific Settings
    |--------------------------------------------------------------------------
    */
    'currencies' => [
        'BTC' => [
            'name' => 'Bitcoin',
            'decimals' => 8,              // Сатоши (1 BTC = 100,000,000 satoshi)
            'min_withdrawal' => '0.001',   // Минимальная сумма вывода в BTC (как строка!)
            'max_withdrawal' => '10',       // Максимальная сумма вывода в BTC
            'confirmations' => 3,           // Кол-во подтверждений для BTC
            'fee' => '0.0005',              // Комиссия в BTC
            'network' => 'bitcoin',
            'rpc_url' => env('BTC_RPC_URL', 'https://btc-mainnet.example.com'),
        ],
        
        'ETH' => [
            'name' => 'Ethereum',
            'decimals' => 18,              // Wei (1 ETH = 10^18 wei)
            'min_withdrawal' => '0.01',
            'max_withdrawal' => '100',
            'confirmations' => 12,          // Кол-во подтверждений для ETH
            'fee' => '0.005',
            'network' => 'ethereum',
            'rpc_url' => env('ETH_RPC_URL', 'https://mainnet.infura.io/v3/your-project-id'),
        ],
        
        'USDT' => [
            'name' => 'Tether USD',
            'decimals' => 6,               // Для ERC-20 обычно 6 знаков
            'min_withdrawal' => '10',
            'max_withdrawal' => '10000',
            'confirmations' => 12,
            'fee' => '5',
            'network' => 'ethereum',        // или 'tron' для TRC-20
            'contract_address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
            'rpc_url' => env('ETH_RPC_URL', 'https://mainnet.infura.io/v3/your-project-id'),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Blacklisted Addresses
    |--------------------------------------------------------------------------
    */
    'blacklisted_addresses' => [
        '0x0000000000000000000000000000000000000000',  // zero address
        '0x000000000000000000000000000000000000dEaD',  // burn address
        // Добавьте другие адреса из черного списка
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'daily_withdrawal_per_user' => '50000',  // Суточный лимит вывода на пользователя в USD
        'min_withdrawal_interval' => 5,           // Минимальный интервал между выводами (минуты)
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    */
    'max_retry_attempts' => env('BLOCKCHAIN_MAX_RETRY_ATTEMPTS', 3),
    'retry_delay_seconds' => env('BLOCKCHAIN_RETRY_DELAY', 60),
];
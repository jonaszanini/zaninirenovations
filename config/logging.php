<?php

use App\Logging\ConfigureMonologProcessors;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('LOG_STACK', 'single'))
            ))),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
            'tap' => [ConfigureMonologProcessors::class],
        ],

        'json' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => RotatingFileHandler::class,
            'handler_with' => [
                'filename' => storage_path('logs/app.json'),
                'maxFiles' => (int) env('LOG_MAX_FILES', 14),
            ],
            'formatter' => JsonFormatter::class,
            'formatter_with' => [
                'appendNewline' => true,
                'ignoreEmptyContextAndExtra' => false,
                'includeStacktraces' => true,
            ],
            'tap' => [ConfigureMonologProcessors::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_STDERR_LEVEL', 'error'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => JsonFormatter::class,
            'formatter_with' => [
                'appendNewline' => true,
                'ignoreEmptyContextAndExtra' => false,
                'includeStacktraces' => true,
            ],
            'tap' => [ConfigureMonologProcessors::class],
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => (int) env('LOG_MAX_FILES', 14),
            'replace_placeholders' => true,
            'tap' => [ConfigureMonologProcessors::class],
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
            'tap' => [ConfigureMonologProcessors::class],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];

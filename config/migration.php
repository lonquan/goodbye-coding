<?php

declare(strict_types=1);

/**
 * Coding 到 GitHub 迁移工具配置文件.
 *
 * 此文件包含默认配置，运行时将从 .env 文件加载环境变量并合并
 */

return [
    'coding' => [
        'access_token' => null, // 从环境变量 CODING_ACCESS_TOKEN 获取
        'base_url' => 'https://e.coding.net',
    ],
    'github' => [
        'access_token' => null, // 从环境变量 GITHUB_ACCESS_TOKEN 获取
        'base_url' => 'https://api.github.com',
        'organization' => null, // 从环境变量 GITHUB_ORGANIZATION 获取
        'overwrite_existing' => false, // 是否覆盖已存在的仓库
    ],
    'migration' => [
        'concurrent_limit' => 3,
        'temp_directory' => './temp',
        'max_retry_attempts' => 3,
        'retry_delay_seconds' => 5,
        'debug_mode' => false,
        'verbose_output' => false,
        'timeout' => 300,
        'rate_limit' => 60,
    ],
    'exclude_repositories' => [
    ],
    'logging' => [
        'level' => 'info',
        'file' => './logs/migration.log',
        'max_file_size' => 10,
        'max_files' => 5,
        'format' => '[%datetime%] %level_name%: %message% %context%',
        'timezone' => 'PRC',
    ],
];

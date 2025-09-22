#!/usr/bin/env php
<?php

declare(strict_types=1);

use GoodbyeCoding\Migration\Commands\ConfigCommand;
use GoodbyeCoding\Migration\Commands\MigrateCommand;
use GoodbyeCoding\Migration\Commands\StatusCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// 检查并加载环境变量
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $dotenv = new Dotenv();
    $dotenv->load($envFile);
} else {
    // 如果 .env 文件不存在，显示提示信息（仅在非静默模式下显示）
    if (!in_array('--silent', $argv) && !in_array('-q', $argv) && !in_array('--quiet', $argv)) {
        echo "⚠️  提示: .env 文件不存在，将使用默认配置和环境变量\n";
        echo "   建议复制 .env.example 为 .env 并配置必要的环境变量\n";
        echo "   命令: cp .env.example .env\n\n";
    }
}

$application = new Application('Coding to GitHub Migration Tool', '1.0.0');

// 注册命令
$application->add(new MigrateCommand());
$application->add(new ConfigCommand());
$application->add(new StatusCommand());

$application->run();

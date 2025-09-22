<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Commands;

use GoodbyeCoding\Migration\Services\ConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 配置命令.
 *
 * 管理迁移工具的配置
 */
class ConfigCommand extends Command
{
    protected static $defaultName = 'config';
    protected static $defaultDescription = '管理迁移工具配置';

    public function __construct()
    {
        parent::__construct('config');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('管理迁移工具配置')
            ->setHelp('此命令帮助您查看、设置和验证迁移工具的配置')
            ->addOption(
                'show',
                's',
                InputOption::VALUE_NONE,
                '显示当前配置'
            )
            ->addOption(
                'validate',
                null,
                InputOption::VALUE_NONE,
                '验证配置'
            )
            ->addOption(
                'set',
                null,
                InputOption::VALUE_REQUIRED,
                '设置配置值（格式：key=value）'
            )
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_OPTIONAL,
                '配置文件路径',
                './config/migration.php'
            )
            ->addOption(
                'masked',
                'm',
                InputOption::VALUE_NONE,
                '显示配置时隐藏敏感信息'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $configFile = $input->getOption('file');
            $configService = new ConfigService();

            // 加载配置文件
            if (file_exists($configFile)) {
                $configService->loadFromFile($configFile);
                $io->info("已加载配置文件: {$configFile}");
            } else {
                $io->warning("配置文件不存在: {$configFile}，使用默认配置");
            }

            // 从环境变量加载配置
            $configService->loadFromEnvironment();

            // 根据选项执行相应操作
            if ($input->getOption('show')) {
                $this->showConfig($io, $configService, $input->getOption('masked'));
            } elseif ($input->getOption('validate')) {
                $this->validateConfig($io, $configService);
            } elseif ($input->getOption('set')) {
                $this->setConfig($io, $configService, $input->getOption('set'), $configFile);
            } else {
                // 默认显示帮助信息
                $this->showHelp($io);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('配置操作失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * 显示配置.
     */
    private function showConfig(SymfonyStyle $io, ConfigService $configService, bool $masked): void
    {
        $io->title('当前配置');

        if ($masked) {
            $config = $configService->getMaskedConfig();
        } else {
            $config = $configService->getAll();
        }

        $this->displayConfigTree($io, $config, '');
    }

    /**
     * 验证配置.
     */
    private function validateConfig(SymfonyStyle $io, ConfigService $configService): void
    {
        $io->title('配置验证');

        if ($configService->isValid()) {
            $io->success('配置验证通过！');
        } else {
            $io->error('配置验证失败！');
            $errors = $configService->getErrors();
            foreach ($errors as $error) {
                $io->error($error);
            }
        }
    }

    /**
     * 设置配置.
     */
    private function setConfig(SymfonyStyle $io, ConfigService $configService, string $keyValue, string $configFile): void
    {
        $parts = explode('=', $keyValue, 2);
        if (2 !== count($parts)) {
            $io->error('配置格式错误，请使用 key=value 格式');

            return;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        // 处理布尔值
        if (in_array(strtolower($value), ['true', 'false'])) {
            $value = 'true' === strtolower($value);
        }

        // 处理数字
        if (is_numeric($value)) {
            $value = false !== strpos($value, '.') ? (float) $value : (int) $value;
        }

        $configService->set($key, $value);

        // 保存到文件
        $this->saveConfigToFile($configService, $configFile);

        $io->success("配置已设置: {$key} = {$value}");
    }

    /**
     * 显示帮助信息.
     */
    private function showHelp(SymfonyStyle $io): void
    {
        $io->title('配置管理帮助');

        $io->section('可用选项');
        $io->listing([
            '--show, -s: 显示当前配置',
            '--validate, -v: 验证配置',
            '--set key=value: 设置配置值',
            '--file, -f: 指定配置文件路径',
            '--masked, -m: 显示配置时隐藏敏感信息',
        ]);

        $io->section('配置项说明');
        $io->table(
            ['配置项', '说明', '示例'],
            [
                ['coding.access_token', 'Coding访问令牌', 'your_coding_token'],
                ['github.access_token', 'GitHub访问令牌', 'your_github_token'],
                ['github.organization', 'GitHub组织名称', 'your_org'],
                ['migration.repository_prefix', '仓库名称前缀', 'migrated-'],
                ['migration.concurrent_limit', '并发限制', '3'],
                ['migration.temp_directory', '临时目录', './temp'],
                ['migration.max_retry_attempts', '最大重试次数', '3'],
                ['migration.retry_delay_seconds', '重试延迟秒数', '5'],
                ['migration.debug_mode', '调试模式', 'false'],
                ['migration.verbose_output', '详细输出', 'false'],
            ]
        );

        $io->section('示例');
        $io->listing([
            'php bin/migration.php config --show',
            'php bin/migration.php config --validate',
            'php bin/migration.php config --set coding.access_token=your_token',
            'php bin/migration.php config --set migration.concurrent_limit=5',
            'php bin/migration.php config --show --masked',
        ]);
    }

    /**
     * 显示配置树.
     */
    private function displayConfigTree(SymfonyStyle $io, array $config, string $prefix): void
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $io->writeln("<info>{$prefix}{$key}:</info>");
                $this->displayConfigTree($io, $value, $prefix . '  ');
            } else {
                $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                $io->writeln("{$prefix}{$key}: <comment>{$displayValue}</comment>");
            }
        }
    }

    /**
     * 保存配置到文件.
     */
    private function saveConfigToFile(ConfigService $configService, string $configFile): void
    {
        $config = $configService->getAll();

        // 确保目录存在
        $dir = dirname($configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 保存为PHP数组格式
        $phpContent = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Coding 到 GitHub 迁移工具配置文件\n * \n * 此文件包含默认配置，运行时将从 .env 文件加载环境变量并合并\n */\n\nreturn [\n";
        $phpContent .= $this->arrayToPhp($config);
        $phpContent .= "];\n";
        file_put_contents($configFile, $phpContent);
    }

    /**
     * 将数组转换为PHP格式.
     */
    private function arrayToPhp(array $array, int $indent = 1): string
    {
        $php = '';
        $spaces = str_repeat('    ', $indent);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $php .= "{$spaces}'{$key}' => [\n";
                $php .= $this->arrayToPhp($value, $indent + 1);
                $php .= "{$spaces}],\n";
            } else {
                $formattedValue = $this->formatPhpValue($value);
                $php .= "{$spaces}'{$key}' => {$formattedValue},\n";
            }
        }

        return $php;
    }

    /**
     * 格式化PHP值.
     */
    private function formatPhpValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_array($value)) {
            if (empty($value)) {
                return '[]';
            }

            $items = [];
            foreach ($value as $item) {
                $items[] = $this->formatPhpValue($item);
            }

            return '[' . implode(', ', $items) . ']';
        }

        return (string) $value;
    }
}

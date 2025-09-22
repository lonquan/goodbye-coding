<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Commands;

use GoodbyeCoding\Migration\Services\CodingApiService;
use GoodbyeCoding\Migration\Services\ConfigService;
use GoodbyeCoding\Migration\Services\GitHubApiService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

/**
 * 状态命令.
 *
 * 检查迁移工具的状态和连接
 */
class StatusCommand extends Command
{
    protected static $defaultName = 'status';
    protected static $defaultDescription = '检查迁移工具状态';

    public function __construct()
    {
        parent::__construct('status');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('检查迁移工具状态')
            ->setHelp('此命令检查迁移工具的状态、配置和API连接')
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_OPTIONAL,
                '配置文件路径',
                './config/migration.php'
            )
            ->addOption(
                'check-api',
                'a',
                InputOption::VALUE_NONE,
                '检查API连接'
            )
            ->addOption(
                'check-git',
                'g',
                InputOption::VALUE_NONE,
                '检查Git环境'
            )
            ->addOption(
                'detailed',
                'd',
                InputOption::VALUE_NONE,
                '详细输出'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $io->title('迁移工具状态检查');

            // 检查配置
            $this->checkConfiguration($io, $input->getOption('config'));

            // 检查API连接（默认启用）
            $this->checkApiConnections($io, $input->getOption('config'));

            // 检查Git环境
            if ($input->getOption('check-git')) {
                $this->checkGitEnvironment($io);
            }

            // 检查系统环境
            $this->checkSystemEnvironment($io);

            // 检查临时目录
            $this->checkTempDirectory($io);

            $io->success('状态检查完成！');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('状态检查失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * 检查配置.
     */
    private function checkConfiguration(SymfonyStyle $io, string $configFile): void
    {
        $io->section('配置检查');

        $configService = new ConfigService();

        // 加载配置文件
        if (file_exists($configFile)) {
            $configService->loadFromFile($configFile);
            $io->info("✓ 配置文件已加载: {$configFile}");
        } else {
            $io->warning("⚠ 配置文件不存在: {$configFile}");
        }

        // 从环境变量加载配置
        $configService->loadFromEnvironment();
        $io->info('✓ 环境变量配置已加载');

        // 验证配置
        if ($configService->isValid()) {
            $io->success('✓ 配置验证通过');
        } else {
            $io->error('✗ 配置验证失败');
            $errors = $configService->getErrors();
            foreach ($errors as $error) {
                $io->error("  - {$error}");
            }
        }

        // 显示关键配置
        $config = $configService->getAll();
        $io->table(
            ['配置项', '状态', '值'],
            [
                ['Coding访问令牌', $this->getConfigStatus($config['coding']['access_token']), $this->maskSensitiveValue($config['coding']['access_token'])],
                ['GitHub访问令牌', $this->getConfigStatus($config['github']['access_token']), $this->maskSensitiveValue($config['github']['access_token'])],
                ['GitHub组织', $this->getConfigStatus($config['github']['organization']), $config['github']['organization'] ?? '未设置'],
                ['并发限制', '✓', $config['migration']['concurrent_limit'] ?? '3'],
                ['临时目录', '✓', $config['migration']['temp_directory'] ?? './temp'],
            ]
        );
    }

    /**
     * 检查API连接.
     */
    private function checkApiConnections(SymfonyStyle $io, string $configFile): void
    {
        $io->section('API连接检查');

        $configService = new ConfigService();
        if (file_exists($configFile)) {
            $configService->loadFromFile($configFile);
        }
        $configService->loadFromEnvironment();

        $config = $configService->getAll();
        $httpClient = HttpClient::create([
            'timeout' => 10, // 设置超时时间
        ]);

        // 检查Coding API
        $this->checkCodingApi($io, $httpClient, $config);

        // 检查GitHub API
        $this->checkGitHubApi($io, $httpClient, $config);
    }

    /**
     * 检查Coding API.
     */
    private function checkCodingApi(SymfonyStyle $io, $httpClient, array $config): void
    {
        $io->writeln('检查Coding API连接...');

        if (empty($config['coding']['access_token'])) {
            $io->error('✗ Coding访问令牌未配置');
            $io->writeln('  请设置 CODING_ACCESS_TOKEN 或 CODING_API_TOKEN 环境变量');

            return;
        }

        try {
            $codingApi = new CodingApiService($httpClient, $config['coding']['base_url'] ?? 'https://e.coding.net');
            $codingApi->setAuthToken($config['coding']['access_token']);

            // 测试API连接 - 使用正确的POST请求格式
            $response = $codingApi->post('/open-api', [
                'Action' => 'DescribeCodingCurrentUser',
            ]);

            if (isset($response['Response']['User'])) {
                $io->success('✓ Coding API连接正常');

                $userData = $response['Response']['User'];
                if (isset($userData['Name'])) {
                    $io->info("  用户: {$userData['Name']}");
                }

                // 获取并显示仓库列表
                $this->displayRepositoriesList($io, $codingApi);
            } else {
                // 检查是否有错误信息
                if (isset($response['Response']['Error'])) {
                    $error = $response['Response']['Error'];
                    if (isset($error['Code']) && 'AuthFailure' === $error['Code']) {
                        $io->error('✗ Coding API认证失败');
                        $io->writeln('  可能原因: 访问令牌无效或已过期');
                    } else {
                        $io->error('✗ Coding API调用失败');
                        $io->writeln('  错误代码: ' . ($error['Code'] ?? 'Unknown'));
                        $io->writeln('  错误信息: ' . ($error['Message'] ?? 'Unknown error'));
                    }
                } else {
                    $io->warning('⚠ Coding API响应格式异常');
                    $io->writeln('  响应结构: ' . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
        } catch (\Exception $e) {
            $io->error('✗ Coding API连接失败: ' . $e->getMessage());

            // 提供更详细的错误信息
            if (false !== strpos($e->getMessage(), '401')) {
                $io->writeln('  可能原因: 访问令牌无效或已过期');
            } elseif (false !== strpos($e->getMessage(), '403')) {
                $io->writeln('  可能原因: 访问令牌权限不足');
            } elseif (false !== strpos($e->getMessage(), 'timeout')) {
                $io->writeln('  可能原因: 网络连接超时');
            }
        }
    }

    /**
     * 显示仓库列表.
     */
    private function displayRepositoriesList(SymfonyStyle $io, CodingApiService $codingApi): void
    {
        try {
            $io->writeln('');
            $io->writeln('📋 正在获取仓库列表...');

            $repositories = $codingApi->getAllTeamDepotInfoList();

            if (empty($repositories)) {
                $io->warning('  没有找到任何仓库');

                return;
            }

            $io->writeln(sprintf('  发现 %d 个仓库:', count($repositories)));
            $io->newLine();

            // 创建表格显示仓库列表
            $table = $io->createTable();
            $table->setHeaders(['项目名', '仓库名', '描述', '创建时间', '最后更新']);

            foreach ($repositories as $repository) {
                $projectName = $repository['ProjectName'] ?? $repository['project_name'] ?? 'Unknown';
                $repoName = $repository['Name'] ?? $repository['name'] ?? 'Unknown';
                $description = $repository['Description'] ?? '';
                $createdAt = $repository['CreatedAt'] ?? 0;
                $updatedAt = $repository['LastPushAt'] ?? $repository['UpdatedAt'] ?? 0;

                // 格式化创建时间
                $createdTime = 'Unknown';
                if ($createdAt > 0) {
                    $createdTime = date('Y-m-d H:i:s', $createdAt / 1000);
                }

                // 格式化更新时间
                $updatedTime = 'Unknown';
                if ($updatedAt > 0) {
                    $updatedTime = date('Y-m-d H:i:s', $updatedAt / 1000);
                }

                $table->addRow([
                    $projectName,
                    $repoName,
                    $description ?: '无描述',
                    $createdTime,
                    $updatedTime,
                ]);
            }

            $table->render();
        } catch (\Exception $e) {
            $io->warning('  获取仓库列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 检查GitHub API.
     */
    private function checkGitHubApi(SymfonyStyle $io, $httpClient, array $config): void
    {
        $io->writeln('检查GitHub API连接...');

        if (empty($config['github']['access_token'])) {
            $io->error('✗ GitHub访问令牌未配置');
            $io->writeln('  请设置 GITHUB_ACCESS_TOKEN 环境变量');

            return;
        }

        try {
            $githubApi = new GitHubApiService($httpClient, $config['github']['base_url'] ?? 'https://api.github.com');
            $githubApi->setAuthToken($config['github']['access_token']);

            // 测试API连接
            $response = $githubApi->get('/user');

            if (isset($response['login'])) {
                $io->success('✓ GitHub API连接正常');

                $io->info("  用户: {$response['login']}");

                // 检查组织访问权限
                if (!empty($config['github']['organization'])) {
                    $this->checkOrganizationAccess($io, $githubApi, $config['github']['organization']);
                }
            } else {
                $io->warning('⚠ GitHub API响应格式异常');
            }
        } catch (\Exception $e) {
            $io->error('✗ GitHub API连接失败: ' . $e->getMessage());

            // 提供更详细的错误信息
            if (false !== strpos($e->getMessage(), '401')) {
                $io->writeln('  可能原因: 访问令牌无效或已过期');
            } elseif (false !== strpos($e->getMessage(), '403')) {
                $io->writeln('  可能原因: 访问令牌权限不足');
            } elseif (false !== strpos($e->getMessage(), 'timeout')) {
                $io->writeln('  可能原因: 网络连接超时');
            }
        }
    }

    /**
     * 检查组织访问权限.
     */
    private function checkOrganizationAccess(SymfonyStyle $io, GitHubApiService $githubApi, string $organization): void
    {
        $io->writeln("检查GitHub组织访问权限: {$organization}");

        try {
            $response = $githubApi->get("/orgs/{$organization}");

            if (isset($response['login'])) {
                $io->success("✓ 可以访问组织: {$organization}");

                if (isset($response['name'])) {
                    $io->info("  组织名称: {$response['name']}");
                }
                if (isset($response['description'])) {
                    $io->info("  描述: {$response['description']}");
                }
                if (isset($response['public_repos'])) {
                    $io->info("  公开仓库数: {$response['public_repos']}");
                }
            } else {
                $io->warning('⚠ 组织信息响应格式异常');
            }
        } catch (\Exception $e) {
            $io->error("✗ 无法访问组织 {$organization}: " . $e->getMessage());

            // 提供更详细的错误信息
            if (false !== strpos($e->getMessage(), '404')) {
                $io->writeln('  可能原因: 组织不存在或拼写错误');
            } elseif (false !== strpos($e->getMessage(), '403')) {
                $io->writeln('  可能原因: 没有访问该组织的权限');
            }
        }
    }

    /**
     * 检查Git环境.
     */
    private function checkGitEnvironment(SymfonyStyle $io): void
    {
        $io->section('Git环境检查');

        // 检查Git是否安装
        $process = new \Symfony\Component\Process\Process(['git', '--version']);
        $process->run();

        if ($process->isSuccessful()) {
            $version = trim($process->getOutput());
            $io->success("✓ Git已安装: {$version}");
        } else {
            $io->error('✗ Git未安装或不在PATH中');

            return;
        }

        // 检查Git配置
        $this->checkGitConfig($io);
    }

    /**
     * 检查Git配置.
     */
    private function checkGitConfig(SymfonyStyle $io): void
    {
        $io->writeln('检查Git配置...');

        $process = new \Symfony\Component\Process\Process(['git', 'config', '--global', 'user.name']);
        $process->run();
        $userName = trim($process->getOutput());

        $process = new \Symfony\Component\Process\Process(['git', 'config', '--global', 'user.email']);
        $process->run();
        $userEmail = trim($process->getOutput());

        if ($userName && $userEmail) {
            $io->success('✓ Git用户配置正常');
            $io->info("  用户名: {$userName}");
            $io->info("  邮箱: {$userEmail}");
        } else {
            $io->warning('⚠ Git用户配置不完整');
            if (!$userName) {
                $io->warning('  用户名未设置');
            }
            if (!$userEmail) {
                $io->warning('  邮箱未设置');
            }
        }
    }

    /**
     * 检查系统环境.
     */
    private function checkSystemEnvironment(SymfonyStyle $io): void
    {
        $io->section('系统环境检查');

        // PHP版本
        $phpVersion = PHP_VERSION;
        $io->info("PHP版本: {$phpVersion}");

        if (version_compare($phpVersion, '8.4.0', '>=')) {
            $io->success('✓ PHP版本满足要求');
        } else {
            $io->error('✗ PHP版本过低，需要8.4+');
        }

        // 内存限制
        $memoryLimit = ini_get('memory_limit');
        $io->info("内存限制: {$memoryLimit}");

        // 扩展检查
        $requiredExtensions = ['curl', 'json', 'zip'];
        $missingExtensions = [];

        foreach ($requiredExtensions as $ext) {
            if (extension_loaded($ext)) {
                $io->success("✓ {$ext}扩展已加载");
            } else {
                $io->error("✗ {$ext}扩展未加载");
                $missingExtensions[] = $ext;
            }
        }

        if (!empty($missingExtensions)) {
            $io->error('缺少必需扩展: ' . implode(', ', $missingExtensions));
        }
    }

    /**
     * 检查临时目录.
     */
    private function checkTempDirectory(SymfonyStyle $io): void
    {
        $io->section('临时目录检查');

        $tempDir = './temp';

        if (!is_dir($tempDir)) {
            if (mkdir($tempDir, 0755, true)) {
                $io->success("✓ 临时目录已创建: {$tempDir}");
            } else {
                $io->error("✗ 无法创建临时目录: {$tempDir}");

                return;
            }
        } else {
            $io->success("✓ 临时目录存在: {$tempDir}");
        }

        // 检查写入权限
        if (is_writable($tempDir)) {
            $io->success('✓ 临时目录可写');
        } else {
            $io->error('✗ 临时目录不可写');
        }

        // 检查子目录
        $subDirs = ['repositories'];
        foreach ($subDirs as $subDir) {
            $path = $tempDir . '/' . $subDir;
            if (!is_dir($path)) {
                if (mkdir($path, 0755, true)) {
                    $io->info("✓ 子目录已创建: {$path}");
                } else {
                    $io->warning("⚠ 无法创建子目录: {$path}");
                }
            } else {
                $io->info("✓ 子目录存在: {$path}");
            }
        }
    }

    /**
     * 获取配置状态.
     */
    private function getConfigStatus($value): string
    {
        return $value ? '✓' : '✗';
    }

    /**
     * 隐藏敏感值.
     */
    private function maskSensitiveValue($value): string
    {
        if (!$value) {
            return '未设置';
        }

        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4) . str_repeat('*', strlen($value) - 8) . substr($value, -4);
    }
}

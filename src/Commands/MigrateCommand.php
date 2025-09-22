<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Commands;

use GoodbyeCoding\Migration\Services\CodingApiService;
use GoodbyeCoding\Migration\Services\ConfigService;
use GoodbyeCoding\Migration\Services\GitHubApiService;
use GoodbyeCoding\Migration\Services\GitService;
use GoodbyeCoding\Migration\Services\LogService;
use GoodbyeCoding\Migration\Services\MigrationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

/**
 * 迁移命令.
 *
 * 执行从Coding到GitHub的代码仓库迁移
 */
class MigrateCommand extends Command
{
    protected static $defaultName = 'migrate';
    protected static $defaultDescription = '迁移Coding代码仓库到GitHub';

    private MigrationService $migrationService;
    private ConfigService $configService;
    private array $repositoryData = [];

    public function __construct()
    {
        parent::__construct('migrate');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('迁移Coding代码仓库到GitHub')
            ->setHelp('此命令将帮助您将Coding平台的代码仓库迁移到GitHub平台')
            ->addOption(
                'config',
                null,
                InputOption::VALUE_OPTIONAL,
                '配置文件路径',
                './config/migration.php'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // 初始化服务
            $this->initializeServices($input, $io);

            // 验证配置
            if (!$this->configService->isValid()) {
                $io->error('配置验证失败: ' . implode(', ', $this->configService->getErrors()));

                return Command::FAILURE;
            }

            // 解析选项
            $options = $this->parseOptions($input);

            // 步骤1: 获取所有仓库列表，让用户选择
            $selectedRepositories = $this->selectRepositories($io, $options);
            if (empty($selectedRepositories)) {
                $io->info('没有选择任何仓库，迁移已取消');

                return Command::SUCCESS;
            }

            // 步骤2: 显示迁移计划预览
            $this->displayMigrationPlan($io, $selectedRepositories, $options);

            // 步骤3: 最终确认
            if (!$io->confirm('确定要开始迁移吗？', false)) {
                $io->info('迁移已取消');

                return Command::SUCCESS;
            }

            // 执行迁移
            $result = $this->executeMigration($options, $io, $selectedRepositories);

            // 显示结果
            $this->displayResults($io, $result, false);

            return $result->isSuccess() ? Command::SUCCESS : Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('迁移失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * 初始化服务.
     */
    private function initializeServices(InputInterface $input, SymfonyStyle $io): void
    {
        $configFile = $input->getOption('config');

        // 加载配置
        $this->configService = new ConfigService();
        if (file_exists($configFile)) {
            $this->configService->loadFromFile($configFile);
        } else {
            $io->warning("配置文件不存在: {$configFile}，使用默认配置");
        }

        // 从环境变量加载配置
        $this->configService->loadFromEnvironment();

        // 创建HTTP客户端
        $httpClient = HttpClient::create();

        // 创建API服务
        $config = $this->configService->getAll();
        $codingApi = new CodingApiService($httpClient, $config['coding']['base_url'] ?? 'https://e.coding.net');
        $githubApi = new GitHubApiService($httpClient, $config['github']['base_url'] ?? 'https://api.github.com');

        // 设置访问令牌
        if (!empty($config['coding']['access_token'])) {
            $codingApi->setAuthToken($config['coding']['access_token']);
        }
        if (!empty($config['github']['access_token'])) {
            $githubApi->setAuthToken($config['github']['access_token']);
        }

        // 创建其他服务
        $gitService = new GitService();
        $logService = new LogService(
            $config['logging']['file'] ?? './logs/migration.log',
            $config['logging']['level'] ?? 'info',
            $config['migration']['debug_mode'] ?? false,
            $config['logging']['timezone'] ?? 'PRC'
        );

        // 创建迁移服务
        $this->migrationService = new MigrationService(
            $codingApi,
            $githubApi,
            $gitService,
            $this->configService,
            $logService
        );
    }

    /**
     * 解析选项.
     */
    private function parseOptions(InputInterface $input): array
    {
        return [
            'config' => $input->getOption('config'),
        ];
    }

    /**
     * 执行迁移.
     */
    private function executeMigration(array $options, SymfonyStyle $io, array $selectedRepositories): \GoodbyeCoding\Migration\Contracts\MigrationResultInterface
    {
        $io->title('步骤 3: 开始迁移');

        // 迁移选中的仓库
        $result = new \GoodbyeCoding\Migration\Services\MigrationResult(['total_repositories' => count($selectedRepositories)]);
        $totalRepositories = count($selectedRepositories);
        $currentIndex = 0;

        foreach ($selectedRepositories as $repository) {
            $currentIndex++;
            // 从团队仓库数据结构中提取正确的字段
            $projectId = $repository['ProjectId'] ?? $repository['project_id'] ?? null;
            $repositoryId = $repository['Id'] ?? $repository['id'] ?? null;
            $projectName = $repository['ProjectName'] ?? $repository['project_name'] ?? 'Unknown';
            $repoName = $repository['Name'] ?? $repository['name'] ?? 'Unknown';

            if (null === $projectId || null === $repositoryId) {
                $result->addError(sprintf(
                    '仓库信息不完整: %s/%s (ProjectId: %s, RepositoryId: %s)',
                    $projectName,
                    $repoName,
                    $projectId ?? 'null',
                    $repositoryId ?? 'null'
                ));
                continue;
            }

            // 显示当前进度
            $io->section(sprintf('[%d/%d] 正在迁移: %s/%s', $currentIndex, $totalRepositories, $projectName, $repoName));

            // 创建进度回调函数
            $progressCallback = function (string $message, string $repoName) use ($io) {
                $io->writeln(sprintf('  %s', $message));
            };

            $repoResult = $this->migrationService->migrateRepositoryWithInfo(
                $repository,
                $options,
                $progressCallback
            );

            // 显示迁移结果
            if ($repoResult->isSuccess()) {
                $io->writeln(sprintf('  <info>✅ %s 迁移成功</info>', $repoName));
            } else {
                $errors = $repoResult->getErrors();
                $io->writeln(sprintf('  <error>❌ %s 迁移失败: %s</error>', $repoName, implode(', ', $errors)));
            }

            $result->merge($repoResult);

            // 在仓库之间添加分隔线
            if ($currentIndex < $totalRepositories) {
                $io->newLine();
            }
        }

        return $result;
    }

    /**
     * 选择要迁移的仓库.
     */
    private function selectRepositories(SymfonyStyle $io, array $options): array
    {
        $io->title('步骤 1: 选择要迁移的仓库');

        // 默认使用团队仓库列表API
        return $this->selectTeamRepositories($io, $options);
    }

    /**
     * 检查仓库是否被排除.
     */
    private function isRepositoryExcluded(array $repository): bool
    {
        $excludeRepositories = $this->configService->get('exclude_repositories', []);

        // 确保 $excludeRepositories 是数组
        if (!is_array($excludeRepositories) || empty($excludeRepositories)) {
            return false;
        }

        $projectName = $repository['ProjectName'] ?? $repository['project_name'] ?? '';
        $repoName = $repository['Name'] ?? $repository['name'] ?? '';
        $fullName = sprintf('%s/%s', $projectName, $repoName);

        // 直接检查仓库全名是否在排除列表中
        return in_array($fullName, $excludeRepositories, true);
    }

    /**
     * 选择团队仓库.
     */
    private function selectTeamRepositories(SymfonyStyle $io, array $options): array
    {
        $io->writeln('🔍 正在获取所有仓库列表...');
        $allRepositories = $this->migrationService->getAllTeamRepositories();

        if (empty($allRepositories)) {
            $io->warning('没有找到任何仓库');

            return [];
        }

        $io->writeln(sprintf('📋 发现 %d 个仓库', count($allRepositories)));

        // 显示仓库列表和迁移后的地址
        $this->displayRepositoryListWithMigrationInfo($io, $allRepositories, $options);

        // 应用排除规则
        $repositories = $this->applyExcludeRules($io, $allRepositories, $options);

        // 直接让用户确认是否开始迁移
        $selectedRepositories = $this->askUserToConfirmMigration($io, $repositories, $options);

        return $selectedRepositories;
    }

    /**
     * 让用户确认是否开始迁移.
     */
    private function askUserToConfirmMigration(SymfonyStyle $io, array $repositories, array $options): array
    {
        $io->newLine();

        // 统计排除的仓库数量
        $excludedCount = 0;
        $normalCount = 0;
        foreach ($this->repositoryData as $data) {
            if ($data['isExcluded']) {
                $excludedCount++;
            } else {
                $normalCount++;
            }
        }

        $io->writeln(sprintf('📊 仓库统计: 正常 %d 个, 已排除 %d 个', $normalCount, $excludedCount));
        $io->newLine();

        // 直接询问是否开始迁移
        $confirm = $io->confirm('是否开始迁移这些仓库？', true);

        if (!$confirm) {
            $io->writeln('❌ 用户取消迁移');

            return [];
        }

        // 返回未排除的仓库
        $selectedRepositories = [];
        foreach ($this->repositoryData as $data) {
            if (!$data['isExcluded']) {
                $selectedRepositories[] = $data['repository'];
            }
        }

        $io->writeln(sprintf('✅ 已选择 %d 个仓库进行迁移', count($selectedRepositories)));

        return $selectedRepositories;
    }

    /**
     * 显示仓库列表和迁移信息.
     */
    private function displayRepositoryListWithMigrationInfo(SymfonyStyle $io, array $repositories, array $options): void
    {
        $io->newLine();
        $io->writeln('仓库列表:');

        // 获取 GitHub 组织名称
        $githubOrg = $this->configService->get('github.organization', 'ant-cool');

        $table = $io->createTable();
        $table->setHeaders(['源仓库', '→', '目标仓库', '描述', '创建时间', '更新时间', '排除状态']);

        $repositoryData = [];

        foreach ($repositories as $index => $repository) {
            $projectName = $repository['ProjectName'] ?? $repository['project_name'] ?? 'Unknown';
            $repoName = $repository['Name'] ?? $repository['name'] ?? 'Unknown';
            $sourceRepo = sprintf('%s/%s', $projectName, $repoName);
            $targetRepo = sprintf('%s/%s-%s', $githubOrg, $projectName, $repoName);

            // 获取描述
            $description = $repository['Description'] ?? '';
            $description = $description ?: '无描述';

            // 格式化时间
            $createdAt = $repository['CreatedAt'] ?? 0;
            $updatedAt = $repository['LastPushAt'] ?? $repository['UpdatedAt'] ?? 0;

            $createdTime = $this->formatDate($createdAt);
            $updatedTime = $this->formatDate($updatedAt);

            // 检查是否被排除
            $isExcluded = $this->isRepositoryExcluded($repository);
            $excludeStatus = $isExcluded ? '❌ 已排除' : '✅ 迁移';

            // 存储仓库数据
            $repositoryData[] = [
                'repository' => $repository,
                'sourceRepo' => $sourceRepo,
                'targetRepo' => $targetRepo,
                'isExcluded' => $isExcluded,
            ];

            $table->addRow([
                $sourceRepo,
                '→',
                $targetRepo,
                $description,
                $createdTime,
                $updatedTime,
                $excludeStatus,
            ]);
        }

        $table->render();

        // 存储仓库数据供后续使用
        $this->repositoryData = $repositoryData;
    }

    /**
     * 应用配置中的排除规则.
     */
    private function applyExcludeRules(SymfonyStyle $io, array $repositories, array $options): array
    {
        $excludeRepositories = $this->configService->get('exclude_repositories', []);

        // 如果没有排除配置，直接返回原列表
        if (empty($excludeRepositories)) {
            return $repositories;
        }

        $excludedCount = 0;
        $filteredRepositories = [];

        foreach ($repositories as $repository) {
            $repoName = $repository['Name'] ?? $repository['name'] ?? '';
            $projectName = $repository['ProjectName'] ?? $repository['project_name'] ?? '';
            $fullName = sprintf('%s/%s', $projectName, $repoName);

            // 检查仓库是否在排除列表中
            if (in_array($fullName, $excludeRepositories, true)) {
                $excludedCount++;
            } else {
                $filteredRepositories[] = $repository;
            }
        }

        if ($excludedCount > 0) {
            $io->writeln(sprintf('🔧 配置排除: 已排除 %d 个仓库', $excludedCount));
        }

        return $filteredRepositories;
    }

    /**
     * 显示迁移计划.
     */
    private function displayMigrationPlan(SymfonyStyle $io, array $selectedRepositories, array $options): void
    {
        $io->title('步骤 2: 迁移计划预览');

        $githubOrg = $this->configService->get('github.organization', 'ant-cool');

        $io->writeln(sprintf('目标 GitHub 组织: %s', $githubOrg));
        $io->newLine();

        $io->writeln('📋 迁移计划:');

        $table = $io->createTable();
        $table->setHeaders(['源仓库', '→', '目标仓库']);

        foreach ($selectedRepositories as $repository) {
            $projectName = $repository['ProjectName'] ?? $repository['project_name'] ?? 'Unknown';
            $repoName = $repository['Name'] ?? $repository['name'] ?? 'Unknown';
            $sourceRepo = sprintf('%s/%s', $projectName, $repoName);
            $targetRepo = sprintf('%s/%s-%s', $githubOrg, $projectName, $repoName);

            $table->addRow([
                $sourceRepo,
                '→',
                $targetRepo,
            ]);
        }

        $table->render();

        $io->newLine();
        $io->writeln(sprintf('📊 总计: %d 个仓库', count($selectedRepositories)));

        // 显示重要配置信息
        $this->displayImportantConfigs($io);
    }

    /**
     * 显示重要配置信息.
     */
    private function displayImportantConfigs(SymfonyStyle $io): void
    {
        $io->newLine();
        $io->section('🔧 重要配置信息');

        // 获取配置
        $overwriteExisting = $this->configService->get('github.overwrite_existing', false);
        $excludeRepositories = $this->configService->get('exclude_repositories', []);

        // 显示 overwrite_existing 配置
        $overwriteStatus = $overwriteExisting ? '✅ 启用' : '❌ 禁用';
        $overwriteColor = $overwriteExisting ? 'fg=red' : 'fg=green';
        $io->writeln(sprintf(
            '<%s>📌 覆盖已存在仓库 (overwrite_existing): %s</%s>',
            $overwriteColor,
            $overwriteStatus,
            $overwriteColor
        ));

        // 显示 exclude_repositories 配置
        $excludeCount = count($excludeRepositories);
        if ($excludeCount > 0) {
            $io->writeln(sprintf(
                '<fg=yellow>📌 排除仓库列表 (exclude_repositories): %d 个仓库</fg=yellow>',
                $excludeCount
            ));
            
            // 显示被排除的仓库列表（最多显示5个）
            $displayCount = min(5, $excludeCount);
            $io->writeln('   被排除的仓库:');
            for ($i = 0; $i < $displayCount; $i++) {
                $io->writeln(sprintf('   - %s', $excludeRepositories[$i]));
            }
            if ($excludeCount > 5) {
                $io->writeln(sprintf('   ... 还有 %d 个仓库', $excludeCount - 5));
            }
        } else {
            $io->writeln('<fg=green>📌 排除仓库列表 (exclude_repositories): 无</fg=green>');
        }

        $io->newLine();
        $io->writeln('<comment>💡 提示: 如需修改配置，请编辑配置文件或使用 config 命令</comment>');
    }

    /**
     * 格式化日期.
     */
    private function formatDate($dateInput): string
    {
        if (empty($dateInput)) {
            return 'Unknown';
        }

        try {
            // 如果是时间戳（毫秒）
            if (is_numeric($dateInput)) {
                $timestamp = $dateInput > 10000000000 ? $dateInput / 1000 : $dateInput; // 转换为秒
                $date = new \DateTime('@' . $timestamp);

                return $date->format('Y-m-d');
            }

            // 如果是日期字符串
            $date = new \DateTime($dateInput);

            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * 显示结果.
     */
    private function displayResults(SymfonyStyle $io, \GoodbyeCoding\Migration\Contracts\MigrationResultInterface $result, bool $dryRun): void
    {
        $io->newLine();
        $io->title('迁移结果');

        if ($result->isSuccess()) {
            $io->success('迁移完成！');
        } else {
            $io->error('迁移失败！');
        }

        $io->table(
            ['项目', '值'],
            [
                ['成功数量', $result->getSuccessCount()],
                ['总数量', $result->getTotalCount()],
                ['错误数量', count($result->getErrors())],
            ]
        );

        if ($result->hasErrors()) {
            $io->section('错误详情');
            foreach ($result->getErrors() as $error) {
                $io->error($error);
            }
        }

        if ($dryRun) {
            $io->note('这是干运行模式，没有执行实际迁移操作');
        }
    }
}

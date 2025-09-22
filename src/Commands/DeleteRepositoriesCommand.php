<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Commands;

use GoodbyeCoding\Migration\Services\ConfigService;
use GoodbyeCoding\Migration\Services\GitHubApiService;
use GoodbyeCoding\Migration\Services\LogService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use function dd;

/**
 * 删除 GitHub 组织下所有仓库的命令.
 *
 * 此命令用于删除指定 GitHub 组织下的所有仓库
 */
class DeleteRepositoriesCommand extends Command
{
    protected static $defaultName = 'delete-repos';
    protected static $defaultDescription = '删除 GitHub 组织下的所有仓库';

    private GitHubApiService $githubApi;
    private ConfigService $configService;
    private LogService $logService;

    public function __construct()
    {
        parent::__construct('delete-repos');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('删除 GitHub 组织下的所有仓库')
            ->setHelp('此命令将删除指定 GitHub 组织下的所有仓库。请谨慎使用！')
            ->addOption(
                'config',
                null,
                InputOption::VALUE_OPTIONAL,
                '配置文件路径',
                './config/migration.php'
            )
            ->addOption(
                'org',
                null,
                InputOption::VALUE_OPTIONAL,
                'GitHub 组织名称（如果不指定，将使用配置文件中的值）'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                '强制执行删除操作，跳过确认步骤'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                '干运行模式，只显示将要删除的仓库，不实际删除'
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

            // 获取组织名称
            $orgName = $this->getOrganizationName($input, $io);
            if (!$orgName) {
                return Command::FAILURE;
            }

            // 检查组织是否存在
            if (!$this->checkOrganizationExists($orgName, $io)) {
                return Command::FAILURE;
            }

            // 获取组织下的所有仓库
            $repositories = $this->getOrganizationRepositories($orgName, $io);
            if (empty($repositories)) {
                $io->info('该组织下没有找到任何仓库');
                return Command::SUCCESS;
            }

            // 显示仓库列表
            $this->displayRepositoriesList($io, $repositories);

            // 干运行模式
            if ($input->getOption('dry-run')) {
                $io->note('这是干运行模式，没有执行实际删除操作');
                return Command::SUCCESS;
            }

            // 确认删除
            if (!$this->confirmDeletion($input, $io, $repositories)) {
                $io->info('删除操作已取消');
                return Command::SUCCESS;
            }

            // 执行删除
            $result = $this->executeDeletion($io, $repositories, $orgName);

            // 显示结果
            $this->displayResults($io, $result);

            return $result['success'] ? Command::SUCCESS : Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('删除失败: ' . $e->getMessage());
            $this->logService->error('删除仓库时发生错误', ['error' => $e->getMessage()]);

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

        // 创建GitHub API服务
        $config = $this->configService->getAll();
        $this->githubApi = new GitHubApiService($httpClient, $config['github']['base_url'] ?? 'https://api.github.com');

        // 设置访问令牌
        if (!empty($config['github']['access_token'])) {
            $this->githubApi->setAuthToken($config['github']['access_token']);
        }

        // 生成基于启动时间的日志文件路径
        $logFilePath = $this->configService->generateLogFilePath();

        $this->logService = new LogService(
            $logFilePath,
            $config['logging']['level'] ?? 'info',
            $config['migration']['debug_mode'] ?? false,
            $config['logging']['timezone'] ?? 'PRC',
            $config['migration']['verbose_output'] ?? true
        );
    }

    /**
     * 获取组织名称.
     */
    private function getOrganizationName(InputInterface $input, SymfonyStyle $io): ?string
    {
        $orgName = $input->getOption('org');

        if (!$orgName) {
            $orgName = $this->configService->get('github.organization');
        }

        if (!$orgName) {
            $io->error('未指定 GitHub 组织名称。请使用 --org 选项或在配置文件中设置 github.organization');
            return null;
        }

        return $orgName;
    }

    /**
     * 检查组织是否存在.
     */
    private function checkOrganizationExists(string $orgName, SymfonyStyle $io): bool
    {
        try {
            $this->githubApi->getOrganization($orgName);
            $io->writeln(sprintf('✅ 组织 "%s" 存在', $orgName));
            return true;
        } catch (\Exception $e) {
            $io->error(sprintf('❌ 组织 "%s" 不存在或无法访问: %s', $orgName, $e->getMessage()));
            return false;
        }
    }

    /**
     * 获取组织下的所有仓库.
     */
    private function getOrganizationRepositories(string $orgName, SymfonyStyle $io): array
    {
        $io->writeln(sprintf('🔍 正在获取组织 "%s" 下的所有仓库...', $orgName));

        try {
            $repositories = $this->githubApi->getOrganizationRepositories($orgName, [
                'type' => 'all',
                'per_page' => 100,
                'sort' => 'created',
                'direction' => 'desc'
            ]);

            $io->writeln(sprintf('📋 找到 %d 个仓库', count($repositories)));
            return $repositories;
        } catch (\Exception $e) {
            $io->error(sprintf('获取仓库列表失败: %s', $e->getMessage()));
            return [];
        }
    }

    /**
     * 显示仓库列表.
     */
    private function displayRepositoriesList(SymfonyStyle $io, array $repositories): void
    {
        $io->newLine();
        $io->writeln('📋 将要删除的仓库列表:');

        $table = $io->createTable();
        $table->setHeaders(['仓库名称', '描述', '创建时间', '更新时间', '语言', '大小']);

        foreach ($repositories as $repo) {
            $table->addRow([
                $repo['name'],
                $repo['description'] ?: '无描述',
                $this->formatDate($repo['created_at']),
                $this->formatDate($repo['updated_at']),
                $repo['language'] ?: 'Unknown',
                $this->formatSize($repo['size'])
            ]);
        }

        $table->render();
    }

    /**
     * 确认删除操作.
     */
    private function confirmDeletion(InputInterface $input, SymfonyStyle $io, array $repositories): bool
    {
        $io->newLine();
        $io->section('⚠️  危险操作确认');

        $io->writeln(sprintf('您即将删除 <fg=red>%d 个仓库</fg=red>', count($repositories)));
        $io->writeln('<fg=red>此操作不可逆！删除后无法恢复！</fg=red>');
        $io->newLine();

        if ($input->getOption('force')) {
            $io->writeln('🔧 使用 --force 选项，跳过确认步骤');
            return true;
        }

        // 要求用户输入组织名称进行二次确认
        $orgName = $this->configService->get('github.organization');
        $io->writeln(sprintf('请输入组织名称 "%s" 来确认删除操作:', $orgName));

        $confirmation = $io->ask('确认删除');

        if ($confirmation !== $orgName) {
            $io->error('组织名称不匹配，删除操作已取消');
            return false;
        }

        return $io->confirm('确定要删除这些仓库吗？', false);
    }

    /**
     * 执行删除操作.
     */
    private function executeDeletion(SymfonyStyle $io, array $repositories, string $orgName): array
    {
        $io->newLine();
        $io->title('🗑️  开始删除仓库');

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        $totalRepositories = count($repositories);
        $currentIndex = 0;

        foreach ($repositories as $repo) {
            $currentIndex++;
            $repoName = $repo['name'];

            $io->section(sprintf('[%d/%d] 正在删除: %s', $currentIndex, $totalRepositories, $repoName));

            try {
                $this->githubApi->deleteRepository($orgName, $repoName);
                $io->writeln(sprintf('  <info>✅ %s 删除成功</info>', $repoName));
                $successCount++;

                $this->logService->info('仓库删除成功', [
                    'organization' => $orgName,
                    'repository' => $repoName
                ]);
            } catch (\Exception $e) {
                $errorMessage = sprintf('%s 删除失败: %s', $repoName, $e->getMessage());
                $io->writeln(sprintf('  <error>❌ %s</error>', $errorMessage));
                $errors[] = $errorMessage;
                $errorCount++;

                $this->logService->error('仓库删除失败', [
                    'organization' => $orgName,
                    'repository' => $repoName,
                    'error' => $e->getMessage()
                ]);
            }

            // 在仓库之间添加分隔线
            if ($currentIndex < $totalRepositories) {
                $io->newLine();
            }
        }

        return [
            'success' => $errorCount === 0,
            'successCount' => $successCount,
            'errorCount' => $errorCount,
            'totalCount' => $totalRepositories,
            'errors' => $errors
        ];
    }

    /**
     * 显示删除结果.
     */
    private function displayResults(SymfonyStyle $io, array $result): void
    {
        $io->newLine();
        $io->title('删除结果');

        if ($result['success']) {
            $io->success('所有仓库删除完成！');
        } else {
            $io->error('部分仓库删除失败！');
        }

        $io->table(
            ['项目', '值'],
            [
                ['成功删除', $result['successCount']],
                ['删除失败', $result['errorCount']],
                ['总数量', $result['totalCount']],
            ]
        );

        if ($result['errorCount'] > 0) {
            $io->section('错误详情');
            foreach ($result['errors'] as $error) {
                $io->error($error);
            }
        }
    }

    /**
     * 格式化日期.
     */
    private function formatDate(string $dateString): string
    {
        try {
            $date = new \DateTime($dateString);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * 格式化文件大小.
     */
    private function formatSize(int $sizeInKB): string
    {
        if ($sizeInKB < 1024) {
            return $sizeInKB . ' KB';
        } elseif ($sizeInKB < 1024 * 1024) {
            return round($sizeInKB / 1024, 2) . ' MB';
        } else {
            return round($sizeInKB / (1024 * 1024), 2) . ' GB';
        }
    }
}

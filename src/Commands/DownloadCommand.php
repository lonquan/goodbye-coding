<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Commands;

use GoodbyeCoding\Migration\Services\CodingApiService;
use GoodbyeCoding\Migration\Services\ConfigService;
use GoodbyeCoding\Migration\Services\GitService;
use GoodbyeCoding\Migration\Services\LogService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

/**
 * 下载命令.
 *
 * 将Coding的所有仓库下载到指定文件夹，按照 projectName/repo 结构存放
 */
class DownloadCommand extends Command
{
    protected static $defaultName = 'download';
    protected static $defaultDescription = '下载Coding代码仓库到本地指定文件夹';

    private ConfigService $configService;
    private CodingApiService $codingApi;
    private GitService $gitService;
    private LogService $logService;

    public function __construct()
    {
        parent::__construct('download');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('下载Coding代码仓库到本地指定文件夹')
            ->setHelp('此命令将帮助您将Coding平台的所有代码仓库下载到本地，按照 projectName/repo 的结构进行存放')
            ->addOption(
                'config',
                null,
                InputOption::VALUE_OPTIONAL,
                '配置文件路径',
                './config/migration.php'
            )
            ->addOption(
                'output-dir',
                'o',
                InputOption::VALUE_REQUIRED,
                '输出目录路径',
                './downloads'
            )
            ->addOption(
                'exclude-empty',
                null,
                InputOption::VALUE_NONE,
                '排除空仓库'
            )
            ->addOption(
                'concurrent',
                'c',
                InputOption::VALUE_OPTIONAL,
                '并发下载数量',
                '3'
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

            // 步骤1: 获取所有仓库列表
            $repositories = $this->getAllRepositories($io);
            if (empty($repositories)) {
                $io->warning('没有找到任何仓库');
                return Command::SUCCESS;
            }

            // 步骤2: 显示下载计划
            $this->displayDownloadPlan($io, $repositories, $options);

            // 步骤3: 最终确认
            if (!$io->confirm('确定要开始下载吗？', false)) {
                $io->info('下载已取消');
                return Command::SUCCESS;
            }

            // 执行下载
            $result = $this->executeDownload($options, $io, $repositories);

            // 显示结果
            $this->displayResults($io, $result);

            return $result['success'] ? Command::SUCCESS : Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('下载失败: ' . $e->getMessage());
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
        $this->codingApi = new CodingApiService($httpClient, $config['coding']['base_url'] ?? 'https://e.coding.net');

        // 设置访问令牌
        if (!empty($config['coding']['access_token'])) {
            $this->codingApi->setAuthToken($config['coding']['access_token']);
        }

        // 创建其他服务
        $this->gitService = new GitService();
        
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
     * 解析选项.
     */
    private function parseOptions(InputInterface $input): array
    {
        return [
            'config' => $input->getOption('config'),
            'output_dir' => $input->getOption('output-dir'),
            'exclude_empty' => $input->getOption('exclude-empty'),
            'concurrent' => (int) $input->getOption('concurrent'),
        ];
    }

    /**
     * 获取所有仓库列表.
     */
    private function getAllRepositories(SymfonyStyle $io): array
    {
        $io->writeln('🔍 正在获取所有仓库列表...');
        
        $repositories = $this->codingApi->getAllTeamDepotInfoList();
        
        if (empty($repositories)) {
            $io->warning('没有找到任何仓库');
            return [];
        }

        $io->writeln(sprintf('📋 发现 %d 个仓库', count($repositories)));
        
        return $repositories;
    }

    /**
     * 显示下载计划.
     */
    private function displayDownloadPlan(SymfonyStyle $io, array $repositories, array $options): void
    {
        $io->title('下载计划预览');

        $outputDir = $options['output_dir'];
        $excludeEmpty = $options['exclude_empty'];
        $concurrent = $options['concurrent'];

        $io->writeln(sprintf('📁 输出目录: %s', $outputDir));
        $io->writeln(sprintf('🚫 排除空仓库: %s', $excludeEmpty ? '是' : '否'));
        $io->writeln(sprintf('⚡ 并发数量: %d', $concurrent));
        $io->newLine();

        $io->writeln('📋 仓库列表:');

        $table = $io->createTable();
        $table->setHeaders(['源仓库', '→', '本地路径', '描述', '创建时间', '更新时间']);

        foreach ($repositories as $repository) {
            $projectName = $repository['ProjectName'] ?? $repository['project_name'] ?? 'Unknown';
            $repoName = $repository['Name'] ?? $repository['name'] ?? 'Unknown';
            $sourceRepo = sprintf('%s/%s', $projectName, $repoName);
            $localPath = sprintf('%s/%s/%s', $outputDir, $projectName, $repoName);

            // 获取描述
            $description = $repository['Description'] ?? '';
            $description = $description ?: '无描述';

            // 格式化时间
            $createdAt = $repository['CreatedAt'] ?? 0;
            $updatedAt = $repository['LastPushAt'] ?? $repository['UpdatedAt'] ?? 0;

            $createdTime = $this->formatDate($createdAt);
            $updatedTime = $this->formatDate($updatedAt);

            $table->addRow([
                $sourceRepo,
                '→',
                $localPath,
                $description,
                $createdTime,
                $updatedTime,
            ]);
        }

        $table->render();

        $io->newLine();
        $io->writeln(sprintf('📊 总计: %d 个仓库', count($repositories)));
    }

    /**
     * 执行下载.
     */
    private function executeDownload(array $options, SymfonyStyle $io, array $repositories): array
    {
        $io->title('开始下载');

        $outputDir = $options['output_dir'];
        $excludeEmpty = $options['exclude_empty'];
        $concurrent = $options['concurrent'];

        // 确保输出目录存在
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                throw new \Exception("无法创建输出目录: {$outputDir}");
            }
        }

        $result = [
            'success' => true,
            'total' => count($repositories),
            'downloaded' => 0,
            'skipped' => 0,
            'errors' => [],
            'details' => [],
        ];

        $totalRepositories = count($repositories);
        $currentIndex = 0;

        // 按项目分组
        $projectGroups = [];
        foreach ($repositories as $repository) {
            $projectName = $repository['ProjectName'] ?? $repository['project_name'] ?? 'Unknown';
            if (!isset($projectGroups[$projectName])) {
                $projectGroups[$projectName] = [];
            }
            $projectGroups[$projectName][] = $repository;
        }

        foreach ($projectGroups as $projectName => $projectRepositories) {
            // 创建项目目录
            $projectDir = $outputDir . '/' . $projectName;
            if (!is_dir($projectDir)) {
                if (!mkdir($projectDir, 0755, true)) {
                    $io->error("无法创建项目目录: {$projectDir}");
                    continue;
                }
            }

            foreach ($projectRepositories as $repository) {
                $currentIndex++;
                $repoName = $repository['Name'] ?? $repository['name'] ?? 'Unknown';

                // 显示当前进度
                $io->section(sprintf('[%d/%d] 正在下载: %s/%s', $currentIndex, $totalRepositories, $projectName, $repoName));

                try {
                    $downloadResult = $this->downloadRepository($repository, $projectDir, $excludeEmpty, $io);
                    
                    if ($downloadResult['success']) {
                        $result['downloaded']++;
                        $io->writeln(sprintf('  <info>✅ %s 下载成功</info>', $repoName));
                    } else {
                        $result['skipped']++;
                        $io->writeln(sprintf('  <comment>⏭️  %s 跳过: %s</comment>', $repoName, $downloadResult['reason']));
                    }
                    
                    $result['details'][] = $downloadResult;
                } catch (\Exception $e) {
                    $result['errors'][] = sprintf('%s/%s: %s', $projectName, $repoName, $e->getMessage());
                    $io->writeln(sprintf('  <error>❌ %s 下载失败: %s</error>', $repoName, $e->getMessage()));
                }

                // 在仓库之间添加分隔线
                if ($currentIndex < $totalRepositories) {
                    $io->newLine();
                }
            }
        }

        $result['success'] = empty($result['errors']);

        return $result;
    }

    /**
     * 下载单个仓库.
     */
    private function downloadRepository(array $repository, string $projectDir, bool $excludeEmpty, SymfonyStyle $io): array
    {
        $repoName = $repository['Name'] ?? $repository['name'] ?? 'Unknown';
        $repoDir = $projectDir . '/' . $repoName;

        // 检查仓库是否已存在
        if (is_dir($repoDir)) {
            return [
                'success' => false,
                'reason' => '目录已存在',
                'path' => $repoDir,
            ];
        }

        // 获取克隆URL
        $cloneUrl = $repository['SshUrl'] ?? $repository['ssh_url'] ?? $repository['HttpsUrl'] ?? $repository['git_url'];
        
        if (empty($cloneUrl)) {
            return [
                'success' => false,
                'reason' => '无法获取克隆URL',
                'path' => $repoDir,
            ];
        }

        $io->writeln(sprintf('  📥 正在克隆: %s', $cloneUrl));

        try {
            // 克隆仓库
            $this->gitService->clone($cloneUrl, $repoDir);

            // 检查是否为空仓库
            if ($excludeEmpty && $this->gitService->isEmpty($repoDir)) {
                // 清理空仓库
                $this->gitService->cleanup($repoDir);
                return [
                    'success' => false,
                    'reason' => '空仓库（已排除）',
                    'path' => $repoDir,
                ];
            }

            $io->writeln(sprintf('  📁 保存到: %s', $repoDir));

            return [
                'success' => true,
                'path' => $repoDir,
                'clone_url' => $cloneUrl,
            ];
        } catch (\Exception $e) {
            // 清理失败的克隆
            if (is_dir($repoDir)) {
                $this->gitService->cleanup($repoDir);
            }
            throw $e;
        }
    }

    /**
     * 显示结果.
     */
    private function displayResults(SymfonyStyle $io, array $result): void
    {
        $io->newLine();
        $io->title('下载结果');

        if ($result['success']) {
            $io->success('下载完成！');
        } else {
            $io->error('下载过程中出现错误！');
        }

        $io->table(
            ['项目', '值'],
            [
                ['总数量', $result['total']],
                ['下载成功', $result['downloaded']],
                ['跳过数量', $result['skipped']],
                ['错误数量', count($result['errors'])],
            ]
        );

        if (!empty($result['errors'])) {
            $io->section('错误详情');
            foreach ($result['errors'] as $error) {
                $io->error($error);
            }
        }
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
}

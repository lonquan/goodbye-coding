<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Services;

use GoodbyeCoding\Migration\Contracts\ApiClientInterface;
use GoodbyeCoding\Migration\Contracts\GitServiceInterface;
use GoodbyeCoding\Migration\Contracts\MigrationResultInterface;
use GoodbyeCoding\Migration\Contracts\MigrationServiceInterface;
use GoodbyeCoding\Migration\Exceptions\ApiException;
use GoodbyeCoding\Migration\Exceptions\MigrationException;

/**
 * 迁移服务.
 *
 * 负责协调整个迁移流程
 */
class MigrationService implements MigrationServiceInterface
{
    private ApiClientInterface $codingApi;
    private ApiClientInterface $githubApi;
    private GitServiceInterface $gitService;
    private ConfigService $configService;
    private LogService $logService;
    private $progressCallback;
    private array $options = [];

    public function __construct(
        ApiClientInterface $codingApi,
        ApiClientInterface $githubApi,
        GitServiceInterface $gitService,
        ConfigService $configService,
        LogService $logService
    ) {
        $this->codingApi = $codingApi;
        $this->githubApi = $githubApi;
        $this->gitService = $gitService;
        $this->configService = $configService;
        $this->logService = $logService;
    }

    /**
     * 迁移所有项目.
     */
    public function migrateAll(array $options = []): MigrationResultInterface
    {
        $this->options = $options;
        $this->logService->info('开始迁移所有项目', ['options' => $options]);

        try {
            $this->validateConfiguration();

            $projects = $this->getProjects();
            $result = new MigrationResult(['total_projects' => count($projects)]);

            if (empty($projects)) {
                $this->logService->warning('没有找到需要迁移的项目');

                return $result;
            }

            $concurrentLimit = $options['concurrent_limit'] ?? 3;

            if ($concurrentLimit > 1) {
                return $this->migrateConcurrently($projects, $concurrentLimit);
            } else {
                return $this->migrateSequentially($projects);
            }
        } catch (\Exception $e) {
            $this->logService->error('迁移所有项目失败', ['error' => $e->getMessage()]);

            return (new MigrationResult())->addError('迁移失败: ' . $e->getMessage());
        }
    }

    /**
     * 迁移指定项目.
     */
    public function migrateProject(int $projectId, array $options = []): MigrationResultInterface
    {
        $this->options = $options;
        $this->logService->info('开始迁移项目', ['project_id' => $projectId]);

        try {
            $this->validateConfiguration();

            $repositories = $this->getProjectRepositories($projectId);
            $result = new MigrationResult(['project_id' => $projectId]);

            if (empty($repositories)) {
                $this->logService->warning('项目没有仓库需要迁移', ['project_id' => $projectId]);

                return $result;
            }

            foreach ($repositories as $repository) {
                $repoResult = $this->migrateRepository($projectId, $repository['id'], $options);
                $result->merge($repoResult);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logService->error('迁移项目失败', ['project_id' => $projectId, 'error' => $e->getMessage()]);

            return (new MigrationResult())->addError('迁移项目失败: ' . $e->getMessage());
        }
    }

    /**
     * 迁移指定仓库（使用仓库信息）.
     *
     * 优化的迁移流程：
     * 1. 检查 GitHub 仓库是否存在，存在且不覆盖则跳过
     * 2. 如果仓库不存在或需要覆盖，则从 Coding 克隆项目到临时目录
     * 3. 通过 SSH 推送项目到 GitHub
     */
    public function migrateRepositoryWithInfo(array $repository, array $options = [], ?callable $progressCallback = null): MigrationResultInterface
    {
        $this->options = $options;
        $projectId = $repository['ProjectId'] ?? $repository['project_id'] ?? null;
        $repositoryId = $repository['Id'] ?? $repository['id'] ?? null;
        $repositoryName = $repository['Name'] ?? $repository['name'] ?? 'unknown';

        $this->logService->info('开始迁移仓库', ['project_id' => $projectId, 'repository_id' => $repositoryId]);

        try {
            $this->validateConfiguration();

            if (empty($repository)) {
                throw new MigrationException('仓库信息为空');
            }

            $result = new MigrationResult([
                'project_id' => $projectId,
                'repository_id' => $repositoryId,
                'repository_name' => $repositoryName,
            ]);

            // 步骤1: 检查 GitHub 仓库是否存在
            $this->updateProgress($progressCallback, '🔄 正在检查GitHub仓库...', $repositoryName);
            $this->logService->info('步骤1: 检查GitHub仓库', ['repository' => $repositoryName]);

            $githubRepo = $this->checkGitHubRepository($repository, $options);
            $result->addDetail('github_repo', $githubRepo);

            // 检查是否跳过了仓库创建
            if (isset($githubRepo['_skipped']) && $githubRepo['_skipped']) {
                $this->updateProgress($progressCallback, '⏭️  跳过仓库迁移（已存在且不覆盖）', $repositoryName);
                $this->logService->info('跳过仓库迁移', [
                    'repository' => $repositoryName,
                    'reason' => 'GitHub仓库已存在且配置为不覆盖',
                ]);
                $result->addDetail('skipped', true);
                $result->addDetail('skip_reason', 'GitHub仓库已存在且配置为不覆盖');

                return $result;
            }

            // 步骤2: 使用 SSH 从 Coding 克隆项目到临时目录
            $this->updateProgress($progressCallback, '🔄 正在克隆代码...', $repositoryName);
            $this->logService->info('步骤2: 克隆Coding仓库', ['repository' => $repositoryName]);

            // 使用 projectName + repoName 组合命名，避免重复
            $projectName = $repository['ProjectName'] ?? $repository['project_name'] ?? 'unknown';
            $localFolderName = $projectName . '_' . $repositoryName;

            $localPath = $this->gitService->cloneRepository(
                $repository['SshUrl'] ?? $repository['ssh_url'] ?? $repository['HttpsUrl'] ?? $repository['git_url'],
                $localFolderName
            );
            $result->addDetail('local_path', $localPath);
            $this->updateProgress($progressCallback, '✅  代码克隆完成', $repositoryName);

            // 步骤3: 通过 SSH 推送项目到 GitHub
            $this->updateProgress($progressCallback, '🔄 正在推送代码到GitHub...', $repositoryName);
            $this->logService->info('步骤3: 推送代码到GitHub', ['repository' => $repositoryName]);

            // 添加GitHub远程仓库
            $this->gitService->addRemote($localPath, 'github', $githubRepo['ssh_url'] ?? $githubRepo['clone_url']);

            // 检测默认分支
            $defaultBranch = $this->gitService->getDefaultBranch($localPath);
            $this->logService->info('检测到默认分支', ['repository' => $repositoryName, 'default_branch' => $defaultBranch]);

            // 推送代码到GitHub（如果仓库已存在且配置为覆盖，则使用强制推送）
            $forcePush = $this->configService->get('github.overwrite_existing', false);
            $this->gitService->pushToRemote($localPath, 'github', $defaultBranch, $forcePush);

            // 清理本地仓库
            $this->updateProgress($progressCallback, '🔄 正在清理临时文件...', $repositoryName);
            $this->gitService->cleanup($localPath);

            $result->addSuccess($repositoryName);
            $this->updateProgress($progressCallback, '🎉 仓库迁移完成！', $repositoryName);
            $this->logService->info('仓库迁移成功', ['repository' => $repositoryName]);

            return $result;
        } catch (\Exception $e) {
            $this->updateProgress($progressCallback, '❌ 迁移失败: ' . $e->getMessage(), $repositoryName);
            $this->logService->error('迁移仓库失败', [
                'project_id' => $projectId,
                'repository_id' => $repositoryId,
                'error' => $e->getMessage(),
            ]);

            return (new MigrationResult())->addError('迁移仓库失败: ' . $e->getMessage());
        }
    }

    /**
     * 迁移指定仓库.
     *
     * 正确的迁移流程：
     * 1. 使用 SSH 从 Coding 克隆项目到临时目录
     * 2. 通过 API 在 GitHub 创建仓库
     * 3. 通过 SSH 推送项目到 GitHub
     */
    public function migrateRepository(int $projectId, int $repositoryId, array $options = []): MigrationResultInterface
    {
        $this->options = $options;
        $this->logService->info('开始迁移仓库', ['project_id' => $projectId, 'repository_id' => $repositoryId]);

        try {
            $this->validateConfiguration();

            // 获取仓库详情
            $repository = $this->codingApi->getRepositoryDetails($projectId, $repositoryId);

            if (empty($repository)) {
                throw new MigrationException("仓库不存在: {$repositoryId}");
            }

            $result = new MigrationResult([
                'project_id' => $projectId,
                'repository_id' => $repositoryId,
                'repository_name' => $repository['name'] ?? 'unknown',
            ]);

            // 步骤1: 使用 SSH 从 Coding 克隆项目到临时目录
            $this->logService->info('步骤1: 克隆Coding仓库', ['repository' => $repository['name']]);
            $localPath = $this->gitService->cloneRepository(
                $repository['ssh_url'] ?? $repository['git_url'],
                $repository['name']
            );
            $result->addDetail('local_path', $localPath);
            /*
                        // 步骤2: 通过 API 在 GitHub 创建仓库
                        $this->logService->info('步骤2: 创建GitHub仓库', ['repository' => $repository['name']]);
                        $githubRepo = $this->createGitHubRepository($repository, $options);
                        $result->addDetail('github_repo', $githubRepo);

                        // 检查是否跳过了仓库创建
                        if (isset($githubRepo['_skipped']) && $githubRepo['_skipped']) {
                            $this->logService->info('跳过仓库迁移', [
                                'repository' => $repository['name'],
                                'reason' => 'GitHub仓库已存在且配置为不覆盖'
                            ]);
                            $result->addDetail('skipped', true);
                            $result->addDetail('skip_reason', 'GitHub仓库已存在且配置为不覆盖');

                            // 清理本地仓库
                            $this->gitService->cleanup($localPath);
                            return $result;
                        }

                        // 步骤3: 通过 SSH 推送项目到 GitHub
                        $this->logService->info('步骤3: 推送代码到GitHub', ['repository' => $repository['name']]);

                        // 添加GitHub远程仓库
                        $this->gitService->addRemote($localPath, 'github', $githubRepo['ssh_url'] ?? $githubRepo['clone_url']);

                        // 检测默认分支
                        $defaultBranch = $this->gitService->getDefaultBranch($localPath);
                        $this->logService->info('检测到默认分支', ['repository' => $repository['name'], 'default_branch' => $defaultBranch]);

                        // 推送代码到GitHub（如果仓库已存在且配置为覆盖，则使用强制推送）
                        $forcePush = $this->configService->get('github.overwrite_existing', false);
                        $this->gitService->pushToRemote($localPath, 'github', $defaultBranch, $forcePush);*/

            // 清理本地仓库
            $this->gitService->cleanup($localPath);

            $result->addSuccess($repository['name']);
            $this->logService->info('仓库迁移成功', ['repository' => $repository['name']]);

            return $result;
        } catch (\Exception $e) {
            $this->logService->error('迁移仓库失败', [
                'project_id' => $projectId,
                'repository_id' => $repositoryId,
                'error' => $e->getMessage(),
            ]);

            return (new MigrationResult())->addError('迁移仓库失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取项目列表.
     */
    public function getProjects(): array
    {
        try {
            return $this->codingApi->getProjects();
        } catch (ApiException $e) {
            $this->logService->error('获取项目列表失败', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * 获取项目仓库列表.
     */
    public function getProjectRepositories(int $projectId): array
    {
        try {
            return $this->codingApi->getRepositories($projectId);
        } catch (ApiException $e) {
            $this->logService->error('获取项目仓库列表失败', ['project_id' => $projectId, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * 获取所有团队仓库列表.
     *
     * 使用 DescribeTeamDepotInfoList API 获取团队下的所有仓库
     */
    public function getAllTeamRepositories(): array
    {
        try {
            return $this->codingApi->getAllTeamDepotInfoList();
        } catch (ApiException $e) {
            $this->logService->error('获取团队仓库列表失败', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * 获取团队仓库列表（分页）.
     *
     * 使用 DescribeTeamDepotInfoList API 获取团队下的仓库（支持分页）
     */
    public function getTeamRepositories(int $pageNumber = 1, int $pageSize = 100): array
    {
        try {
            return $this->codingApi->getTeamDepotInfoList($pageNumber, $pageSize);
        } catch (ApiException $e) {
            $this->logService->error('获取团队仓库列表失败', [
                'page_number' => $pageNumber,
                'page_size' => $pageSize,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 验证配置.
     */
    public function validateConfiguration(): bool
    {
        if (!$this->configService->isValid()) {
            throw new MigrationException('配置验证失败');
        }

        $config = $this->configService->getAll();

        if (empty($config['coding']['access_token'])) {
            throw new MigrationException('Coding访问令牌未配置');
        }

        if (empty($config['github']['access_token'])) {
            throw new MigrationException('GitHub访问令牌未配置');
        }

        return true;
    }

    /**
     * 设置进度回调.
     */
    public function setProgressCallback(callable $callback): self
    {
        $this->progressCallback = $callback;

        return $this;
    }

    /**
     * 更新进度状态.
     */
    private function updateProgress(?callable $callback, string $message, string $repositoryName): void
    {
        if ($callback) {
            $callback($message, $repositoryName);
        } elseif ($this->progressCallback) {
            ($this->progressCallback)($message, $repositoryName);
        }
    }

    /**
     * 并发迁移项目.
     */
    private function migrateConcurrently(array $projects, int $concurrentLimit): MigrationResultInterface
    {
        $this->logService->info('开始并发迁移', ['projects_count' => count($projects), 'concurrent_limit' => $concurrentLimit]);

        $result = new MigrationResult(['concurrent_limit' => $concurrentLimit]);
        $projectChunks = array_chunk($projects, $concurrentLimit);

        foreach ($projectChunks as $chunk) {
            $chunkResults = [];

            foreach ($chunk as $project) {
                $projectId = $project['id'] ?? null;
                if (null === $projectId) {
                    $this->logService->warning('项目ID为空，跳过迁移', ['project' => $project]);
                    continue;
                }

                $projectResult = $this->migrateProject($projectId, $this->options);
                $chunkResults[] = $projectResult;
                $result->merge($projectResult);

                if ($this->progressCallback) {
                    ($this->progressCallback)($project['name'] ?? $projectId, $result);
                }
            }

            // 检查是否有错误需要重试
            $this->handleRetryLogic($chunkResults);
        }

        return $result;
    }

    /**
     * 顺序迁移项目.
     */
    private function migrateSequentially(array $projects): MigrationResultInterface
    {
        $this->logService->info('开始顺序迁移', ['projects_count' => count($projects)]);

        $result = new MigrationResult();

        foreach ($projects as $project) {
            $projectId = $project['id'] ?? null;
            if (null === $projectId) {
                $this->logService->warning('项目ID为空，跳过迁移', ['project' => $project]);
                continue;
            }

            $projectResult = $this->migrateProject($projectId, $this->options);
            $result->merge($projectResult);

            if ($this->progressCallback) {
                ($this->progressCallback)($project['name'] ?? $projectId, $result);
            }
        }

        return $result;
    }

    /**
     * 检查GitHub仓库是否存在并处理.
     */
    private function checkGitHubRepository(array $repository, array $options): array
    {
        $prefix = $options['prefix'] ?? '';
        $repoName = $prefix . ($repository['Name'] ?? $repository['name']);
        $githubOrg = $this->configService->get('github.organization');
        $overwriteExisting = $this->configService->get('github.overwrite_existing', false);

        // 检查仓库是否已存在
        $repositoryExists = $this->githubApi->repositoryExists($githubOrg, $repoName);

        if ($repositoryExists) {
            if ($overwriteExisting) {
                $this->logService->info('GitHub仓库已存在，将覆盖', [
                    'repository' => $repoName,
                    'organization' => $githubOrg,
                ]);

                // 获取现有仓库信息
                $existingRepo = $this->githubApi->getRepository($githubOrg, $repoName);

                return $existingRepo;
            } else {
                $this->logService->warning('GitHub仓库已存在，跳过创建', [
                    'repository' => $repoName,
                    'organization' => $githubOrg,
                ]);

                // 返回现有仓库信息，但标记为跳过
                $existingRepo = $this->githubApi->getRepository($githubOrg, $repoName);
                $existingRepo['_skipped'] = true;

                return $existingRepo;
            }
        }

        // 仓库不存在，需要创建
        $this->logService->info('GitHub仓库不存在，将创建新仓库', [
            'repository' => $repoName,
            'organization' => $githubOrg,
        ]);

        // 创建新仓库
        $data = [
            'name' => $repoName,
            'description' => $repository['Description'] ?? $repository['description'] ?? '',
            'private' => !($repository['IsShared'] ?? $repository['is_public'] ?? true),
            'auto_init' => false,
        ];

        $response = $this->githubApi->createRepository($githubOrg, $data);

        if (empty($response['clone_url'])) {
            throw new MigrationException('创建GitHub仓库失败');
        }

        $this->logService->info('GitHub仓库创建成功', [
            'repository' => $repoName,
            'organization' => $githubOrg,
        ]);

        return $response;
    }

    /**
     * 创建GitHub仓库.
     */
    private function createGitHubRepository(array $repository, array $options): array
    {
        $prefix = $options['prefix'] ?? '';
        $repoName = $prefix . ($repository['Name'] ?? $repository['name']);
        $githubOrg = $this->configService->get('github.organization');
        $overwriteExisting = $this->configService->get('github.overwrite_existing', false);

        // 检查仓库是否已存在
        $repositoryExists = $this->githubApi->repositoryExists($githubOrg, $repoName);

        if ($repositoryExists) {
            if ($overwriteExisting) {
                $this->logService->info('GitHub仓库已存在，将覆盖', [
                    'repository' => $repoName,
                    'organization' => $githubOrg,
                ]);

                // 获取现有仓库信息
                $existingRepo = $this->githubApi->getRepository($githubOrg, $repoName);

                return $existingRepo;
            } else {
                $this->logService->warning('GitHub仓库已存在，跳过创建', [
                    'repository' => $repoName,
                    'organization' => $githubOrg,
                ]);

                // 返回现有仓库信息，但标记为跳过
                $existingRepo = $this->githubApi->getRepository($githubOrg, $repoName);
                $existingRepo['_skipped'] = true;

                return $existingRepo;
            }
        }

        // 创建新仓库
        $data = [
            'name' => $repoName,
            'description' => $repository['Description'] ?? $repository['description'] ?? '',
            'private' => !($repository['IsShared'] ?? $repository['is_public'] ?? true),
            'auto_init' => false,
        ];

        $response = $this->githubApi->createRepository($githubOrg, $data);

        if (empty($response['clone_url'])) {
            throw new MigrationException('创建GitHub仓库失败');
        }

        $this->logService->info('GitHub仓库创建成功', [
            'repository' => $repoName,
            'organization' => $githubOrg,
        ]);

        return $response;
    }

    /**
     * 处理重试逻辑.
     */
    private function handleRetryLogic(array $results): void
    {
        $retryCount = $this->options['retry_count'] ?? 3;
        $retryDelay = $this->options['retry_delay'] ?? 5;

        foreach ($results as $result) {
            if (!$result->isSuccess() && $retryCount > 0) {
                $this->logService->info('准备重试失败的迁移', ['retry_count' => $retryCount]);
                sleep($retryDelay);
                // 这里可以实现具体的重试逻辑
            }
        }
    }
}

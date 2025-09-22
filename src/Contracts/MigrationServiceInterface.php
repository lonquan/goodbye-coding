<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Contracts;

/**
 * 迁移服务接口.
 *
 * 定义迁移操作的基本契约
 */
interface MigrationServiceInterface
{
    /**
     * 迁移所有项目.
     */
    public function migrateAll(array $options = []): MigrationResultInterface;

    /**
     * 迁移指定项目.
     */
    public function migrateProject(int $projectId, array $options = []): MigrationResultInterface;

    /**
     * 迁移指定仓库.
     */
    public function migrateRepository(int $projectId, int $repositoryId, array $options = []): MigrationResultInterface;

    /**
     * 获取项目列表.
     */
    public function getProjects(): array;

    /**
     * 获取项目仓库列表.
     */
    public function getProjectRepositories(int $projectId): array;

    /**
     * 验证配置.
     */
    public function validateConfiguration(): bool;

    /**
     * 设置进度回调.
     */
    public function setProgressCallback(callable $callback): self;
}

/**
 * 迁移结果接口.
 */
interface MigrationResultInterface
{
    /**
     * 是否成功.
     */
    public function isSuccess(): bool;

    /**
     * 是否有错误.
     */
    public function hasErrors(): bool;

    /**
     * 获取错误列表.
     */
    public function getErrors(): array;

    /**
     * 获取成功数量.
     */
    public function getSuccessCount(): int;

    /**
     * 获取总数量.
     */
    public function getTotalCount(): int;

    /**
     * 获取详细信息.
     */
    public function getDetails(): array;

    /**
     * 添加错误.
     */
    public function addError(string $error): self;

    /**
     * 添加成功.
     */
    public function addSuccess(string $item): self;
}

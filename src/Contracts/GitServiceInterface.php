<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Contracts;

/**
 * Git 服务接口.
 *
 * 定义 Git 操作的基本契约
 */
interface GitServiceInterface
{
    /**
     * 克隆仓库.
     */
    public function clone(string $repository, string $targetPath): string;

    /**
     * 克隆仓库到临时目录.
     */
    public function cloneRepository(string $repository, string $name): string;

    /**
     * 推送代码到远程仓库.
     */
    public function pushToRemote(string $repositoryPath, string $remote, string $branch): void;

    /**
     * 添加远程仓库.
     */
    public function addRemote(string $repositoryPath, string $name, string $url): void;

    /**
     * 推送代码到远程仓库.
     */
    public function push(string $repositoryPath, string $remote = 'origin', string $branch = 'main'): void;

    /**
     * 拉取代码.
     */
    public function pull(string $repositoryPath, string $remote = 'origin', string $branch = 'main'): void;

    /**
     * 检出分支.
     */
    public function checkout(string $repositoryPath, string $branch): void;

    /**
     * 创建分支.
     */
    public function createBranch(string $repositoryPath, string $branch): void;

    /**
     * 获取当前分支.
     */
    public function getCurrentBranch(string $repositoryPath): string;

    /**
     * 获取所有分支.
     */
    public function getBranches(string $repositoryPath): array;

    /**
     * 检查仓库是否存在.
     */
    public function exists(string $repositoryPath): bool;

    /**
     * 清理仓库.
     */
    public function cleanup(string $repositoryPath): void;
}

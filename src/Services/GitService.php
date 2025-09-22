<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Services;

use GoodbyeCoding\Migration\Contracts\GitServiceInterface;
use GoodbyeCoding\Migration\Exceptions\GitException;
use Symfony\Component\Process\Process;

/**
 * Git 操作服务.
 *
 * 负责执行 Git 相关操作
 */
class GitService implements GitServiceInterface
{
    private string $gitCommand = 'git';

    public function __construct(string $gitCommand = 'git')
    {
        $this->gitCommand = $gitCommand;
    }

    /**
     * 克隆仓库.
     */
    public function clone(string $repository, string $targetPath): string
    {
        $this->ensureDirectoryExists(dirname($targetPath));

        $process = new Process([
            $this->gitCommand,
            'clone',
            $repository,
            $targetPath,
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw GitException::cloneFailed($repository, $process->getOutput(), $process->getExitCode());
        }

        return $targetPath;
    }

    /**
     * 添加远程仓库.
     */
    public function addRemote(string $repositoryPath, string $name, string $url): void
    {
        $this->ensureRepositoryExists($repositoryPath);

        $process = new Process([
            $this->gitCommand,
            'remote',
            'add',
            $name,
            $url,
        ], $repositoryPath);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new GitException("Failed to add remote '{$name}' to repository", 0, null, ['repository' => $repositoryPath, 'remote' => $name, 'url' => $url], $process->getCommandLine(), $process->getOutput(), $process->getExitCode());
        }
    }

    /**
     * 推送代码到远程仓库.
     */
    public function push(string $repositoryPath, string $remote = 'origin', string $branch = 'main', bool $force = false): void
    {
        $this->ensureRepositoryExists($repositoryPath);

        $command = [$this->gitCommand, 'push'];

        if ($force) {
            $command[] = '--force';
        }

        $command[] = $remote;
        $command[] = $branch;

        $process = new Process($command, $repositoryPath);

        $process->run();

        if (!$process->isSuccessful()) {
            throw GitException::pushFailed("{$remote}/{$branch}" . ($force ? ' (force)' : ''), $process->getOutput(), $process->getExitCode());
        }
    }

    /**
     * 拉取代码.
     */
    public function pull(string $repositoryPath, string $remote = 'origin', string $branch = 'main'): void
    {
        $this->ensureRepositoryExists($repositoryPath);

        $process = new Process([
            $this->gitCommand,
            'pull',
            $remote,
            $branch,
        ], $repositoryPath);

        $process->run();

        if (!$process->isSuccessful()) {
            throw GitException::pullFailed("{$remote}/{$branch}", $process->getOutput(), $process->getExitCode());
        }
    }

    /**
     * 检出分支.
     */
    public function checkout(string $repositoryPath, string $branch): void
    {
        $this->ensureRepositoryExists($repositoryPath);

        $process = new Process([
            $this->gitCommand,
            'checkout',
            $branch,
        ], $repositoryPath);

        $process->run();

        if (!$process->isSuccessful()) {
            throw GitException::checkoutFailed($branch, $process->getOutput(), $process->getExitCode());
        }
    }

    /**
     * 创建分支.
     */
    public function createBranch(string $repositoryPath, string $branch): void
    {
        $this->ensureRepositoryExists($repositoryPath);

        $process = new Process([
            $this->gitCommand,
            'checkout',
            '-b',
            $branch,
        ], $repositoryPath);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new GitException("Failed to create branch '{$branch}'", 0, null, ['repository' => $repositoryPath, 'branch' => $branch], $process->getCommandLine(), $process->getOutput(), $process->getExitCode());
        }
    }

    /**
     * 获取当前分支.
     */
    public function getCurrentBranch(string $repositoryPath): string
    {
        $this->ensureRepositoryExists($repositoryPath);

        $process = new Process([
            $this->gitCommand,
            'branch',
            '--show-current',
        ], $repositoryPath);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new GitException('Failed to get current branch', 0, null, ['repository' => $repositoryPath], $process->getCommandLine(), $process->getOutput(), $process->getExitCode());
        }

        return trim($process->getOutput());
    }

    /**
     * 获取所有分支.
     */
    public function getBranches(string $repositoryPath): array
    {
        $this->ensureRepositoryExists($repositoryPath);

        $process = new Process([
            $this->gitCommand,
            'branch',
            '-a',
        ], $repositoryPath);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new GitException('Failed to get branches', 0, null, ['repository' => $repositoryPath], $process->getCommandLine(), $process->getOutput(), $process->getExitCode());
        }

        $branches = [];
        $output = trim($process->getOutput());

        if ($output) {
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line) {
                    // 移除 * 标记和 remote/ 前缀
                    $branch = preg_replace('/^\*\s*/', '', $line);
                    $branch = preg_replace('/^remotes\/[^\/]+\//', '', $branch);
                    $branches[] = $branch;
                }
            }
        }

        return array_unique($branches);
    }

    /**
     * 获取默认分支.
     * 优先检查 main，如果不存在则检查 master.
     */
    public function getDefaultBranch(string $repositoryPath): string
    {
        $this->ensureRepositoryExists($repositoryPath);

        // 首先尝试获取远程默认分支
        $process = new Process([
            $this->gitCommand,
            'symbolic-ref',
            'refs/remotes/origin/HEAD',
        ], $repositoryPath);

        $process->run();

        if ($process->isSuccessful()) {
            $output = trim($process->getOutput());
            if ($output) {
                // 提取分支名，例如 refs/remotes/origin/main -> main
                $branch = basename($output);
                if ($branch) {
                    return $branch;
                }
            }
        }

        // 如果无法获取远程默认分支，则检查本地分支
        $branches = $this->getBranches($repositoryPath);

        // 优先返回 main，如果不存在则返回 master
        if (in_array('main', $branches)) {
            return 'main';
        }

        if (in_array('master', $branches)) {
            return 'master';
        }

        // 如果都没有，返回第一个分支或默认的 main
        return !empty($branches) ? $branches[0] : 'main';
    }

    /**
     * 检查仓库是否存在.
     */
    public function exists(string $repositoryPath): bool
    {
        return is_dir($repositoryPath) && is_dir($repositoryPath . '/.git');
    }

    /**
     * 清理仓库.
     */
    public function cleanup(string $repositoryPath): void
    {
        if (!$this->exists($repositoryPath)) {
            return;
        }

        $this->removeDirectory($repositoryPath);
    }

    /**
     * 确保目录存在.
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new GitException("Failed to create directory: {$directory}");
            }
        }
    }

    /**
     * 确保仓库存在.
     */
    private function ensureRepositoryExists(string $repositoryPath): void
    {
        if (!$this->exists($repositoryPath)) {
            throw new GitException("Repository does not exist: {$repositoryPath}");
        }
    }

    /**
     * 克隆仓库到临时目录.
     */
    public function cloneRepository(string $repository, string $name): string
    {
        $tempDir = './temp/repositories';
        $this->ensureDirectoryExists($tempDir);

        $targetPath = $tempDir . '/' . $name;

        // 如果目录已存在，先清理
        if (is_dir($targetPath)) {
            $this->cleanup($targetPath);
        }

        return $this->clone($repository, $targetPath);
    }

    /**
     * 推送代码到远程仓库.
     */
    public function pushToRemote(string $repositoryPath, string $remote, string $branch, bool $force = false): void
    {
        $this->push($repositoryPath, $remote, $branch, $force);
    }

    /**
     * 递归删除目录.
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}

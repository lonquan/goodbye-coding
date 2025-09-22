<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Exceptions;

/**
 * Git 操作异常类.
 *
 * 处理所有 Git 操作相关的异常
 */
class GitException extends MigrationException
{
    protected ?string $command = null;
    protected ?string $output = null;
    protected ?int $exitCode = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        array $context = [],
        ?string $command = null,
        ?string $output = null,
        ?int $exitCode = null
    ) {
        parent::__construct($message, $code, $previous, $context);
        $this->command = $command;
        $this->output = $output;
        $this->exitCode = $exitCode;
    }

    public function getCommand(): ?string
    {
        return $this->command;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    public static function cloneFailed(string $repository, string $output = '', int $exitCode = 1): self
    {
        return new self(
            "Failed to clone repository: {$repository}",
            0,
            null,
            ['repository' => $repository],
            "git clone {$repository}",
            $output,
            $exitCode
        );
    }

    public static function pushFailed(string $repository, string $output = '', int $exitCode = 1): self
    {
        return new self(
            "Failed to push to repository: {$repository}",
            0,
            null,
            ['repository' => $repository],
            "git push {$repository}",
            $output,
            $exitCode
        );
    }

    public static function pullFailed(string $repository, string $output = '', int $exitCode = 1): self
    {
        return new self(
            "Failed to pull from repository: {$repository}",
            0,
            null,
            ['repository' => $repository],
            "git pull {$repository}",
            $output,
            $exitCode
        );
    }

    public static function checkoutFailed(string $branch, string $output = '', int $exitCode = 1): self
    {
        return new self(
            "Failed to checkout branch: {$branch}",
            0,
            null,
            ['branch' => $branch],
            "git checkout {$branch}",
            $output,
            $exitCode
        );
    }
}

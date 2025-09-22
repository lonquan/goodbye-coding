<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Exceptions;

/**
 * 迁移基础异常类.
 *
 * 所有迁移相关的异常都继承自此类
 */
class MigrationException extends \Exception
{
    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?\Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;

        return $this;
    }
}

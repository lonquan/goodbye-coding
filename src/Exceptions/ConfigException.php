<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Exceptions;

/**
 * 配置异常类.
 *
 * 处理所有配置相关的异常
 */
class ConfigException extends MigrationException
{
    protected ?string $configKey = null;
    protected mixed $configValue = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        array $context = [],
        ?string $configKey = null,
        mixed $configValue = null
    ) {
        parent::__construct($message, $code, $previous, $context);
        $this->configKey = $configKey;
        $this->configValue = $configValue;
    }

    public function getConfigKey(): ?string
    {
        return $this->configKey;
    }

    public function getConfigValue(): mixed
    {
        return $this->configValue;
    }

    public static function missing(string $key, array $context = []): self
    {
        return new self(
            "Required configuration key '{$key}' is missing",
            0,
            null,
            $context,
            $key
        );
    }

    public static function invalid(string $key, mixed $value, string $reason = '', array $context = []): self
    {
        $message = "Invalid configuration value for '{$key}'";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self($message, 0, null, $context, $key, $value);
    }

    public static function fileNotFound(string $file, array $context = []): self
    {
        return new self(
            "Configuration file not found: {$file}",
            0,
            null,
            $context
        );
    }

    public static function parseError(string $file, string $error, array $context = []): self
    {
        return new self(
            "Failed to parse configuration file '{$file}': {$error}",
            0,
            null,
            $context
        );
    }

    public static function validationFailed(array $errors, array $context = []): self
    {
        $message = 'Configuration validation failed: ' . implode(', ', $errors);

        return new self($message, 0, null, $context);
    }
}

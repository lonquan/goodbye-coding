<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Services;

use GoodbyeCoding\Migration\Exceptions\ConfigException;
use Symfony\Component\Dotenv\Dotenv;

/**
 * 配置管理服务.
 *
 * 负责加载、验证和管理应用程序配置
 */
class ConfigService
{
    private array $config = [];
    private array $errors = [];
    private bool $isValid = false;

    public function __construct(array $defaultConfig = [])
    {
        $this->config = $this->getDefaultConfig();
        $this->config = array_merge($this->config, $defaultConfig);

        // 自动加载 .env 文件
        $this->loadFromEnvironment();
    }

    /**
     * 从文件加载配置.
     */
    public function loadFromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw ConfigException::fileNotFound($filePath);
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        try {
            switch ($extension) {
                case 'php':
                    $fileConfig = require $filePath;
                    if (!is_array($fileConfig)) {
                        throw new \InvalidArgumentException('PHP config file must return an array');
                    }
                    break;
                case 'json':
                    $content = file_get_contents($filePath);
                    $fileConfig = json_decode($content, true);
                    if (JSON_ERROR_NONE !== json_last_error()) {
                        throw new \JsonException('Invalid JSON: ' . json_last_error_msg());
                    }
                    break;
                default:
                    throw ConfigException::parseError($filePath, 'Unsupported file format. Supported: .php, .json');
            }

            $this->config = array_merge($this->config, $fileConfig);
        } catch (\Exception $e) {
            throw ConfigException::parseError($filePath, $e->getMessage());
        }

        return $this;
    }

    /**
     * 从环境变量加载配置.
     */
    public function loadFromEnvironment(): self
    {
        // 加载 .env 文件
        $envFile = getcwd() . '/.env';
        if (file_exists($envFile)) {
            $dotenv = new Dotenv();
            $dotenv->load($envFile);
        }

        $envMappings = [
            'CODING_ACCESS_TOKEN' => 'coding.access_token',
            'GITHUB_ACCESS_TOKEN' => 'github.access_token',
            'GITHUB_ORGANIZATION' => 'github.organization',
        ];

        foreach ($envMappings as $envKey => $configKey) {
            $value = $_ENV[$envKey] ?? getenv($envKey);
            if (false !== $value && null !== $value) {
                $this->set($configKey, $this->castValue($value));
            }
        }

        return $this;
    }

    /**
     * 加载默认配置.
     */
    public function loadDefaults(): self
    {
        $this->config = $this->getDefaultConfig();

        return $this;
    }

    /**
     * 获取配置值.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 设置配置值.
     */
    public function set(string $key, mixed $value): self
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!is_array($config)) {
                $config = [];
            }
            if (!array_key_exists($k, $config)) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;

        return $this;
    }

    /**
     * 验证配置.
     */
    public function isValid(): bool
    {
        $this->errors = [];
        $this->isValid = true;

        // 验证必需的配置项
        $requiredKeys = [
            'coding.access_token',
            'github.access_token',
            'github.organization',
        ];

        foreach ($requiredKeys as $key) {
            if (null === $this->get($key)) {
                $this->errors[] = "Required configuration key '{$key}' is missing";
                $this->isValid = false;
            }
        }

        // 验证类型
        $this->validateType('migration.concurrent_limit', 'integer', 1, 10);
        $this->validateType('migration.max_retry_attempts', 'integer', 1, 10);
        $this->validateType('migration.retry_delay_seconds', 'integer', 1, 60);

        // 验证 URL 格式
        $this->validateUrl('coding.base_url');
        $this->validateUrl('github.base_url');

        if (!$this->isValid) {
            throw ConfigException::validationFailed($this->errors);
        }

        return $this->isValid;
    }

    /**
     * 获取错误列表.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 获取所有配置.
     */
    public function getAll(): array
    {
        return $this->config;
    }

    /**
     * 获取掩码配置（隐藏敏感信息）.
     */
    public function getMaskedConfig(): array
    {
        $maskedConfig = $this->config;
        $sensitiveKeys = [
            'coding.access_token',
            'github.access_token',
        ];

        foreach ($sensitiveKeys as $key) {
            $value = $this->get($key);
            if (null !== $value) {
                $this->setMaskedValue($maskedConfig, $key, '***' . substr((string) $value, -4));
            }
        }

        return $maskedConfig;
    }

    /**
     * 获取默认配置.
     */
    private function getDefaultConfig(): array
    {
        return [
            'coding' => [
                'access_token' => null,
                'base_url' => 'https://e.coding.net',
            ],
            'github' => [
                'access_token' => null,
                'base_url' => 'https://api.github.com',
                'organization' => null,
            ],
            'migration' => [
                'repository_prefix' => '',
                'concurrent_limit' => 3,
                'temp_directory' => './temp',
                'max_retry_attempts' => 3,
                'retry_delay_seconds' => 5,
                'debug_mode' => false,
                'verbose_output' => false,
                'timeout' => 300,
                'rate_limit' => 60,
            ],
            'logging' => [
                'level' => 'info',
                'file' => './logs/migration.log',
                'max_file_size' => 10,
                'max_files' => 5,
                'format' => '[%datetime%] %level_name%: %message% %context%',
            ],
        ];
    }

    /**
     * 验证类型.
     */
    private function validateType(string $key, string $type, ?int $min = null, ?int $max = null): void
    {
        $value = $this->get($key);
        if (null === $value) {
            return;
        }

        $isValid = match ($type) {
            'integer' => is_int($value),
            'string' => is_string($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            default => false,
        };

        if (!$isValid) {
            $this->errors[] = "Configuration key '{$key}' must be of type {$type}";
            $this->isValid = false;

            return;
        }

        if ('integer' === $type && null !== $min && $value < $min) {
            $this->errors[] = "Configuration key '{$key}' must be at least {$min}";
            $this->isValid = false;
        }

        if ('integer' === $type && null !== $max && $value > $max) {
            $this->errors[] = "Configuration key '{$key}' must be at most {$max}";
            $this->isValid = false;
        }
    }

    /**
     * 验证 URL 格式.
     */
    private function validateUrl(string $key): void
    {
        $value = $this->get($key);
        if (null === $value) {
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->errors[] = "Configuration key '{$key}' must be a valid URL";
            $this->isValid = false;
        }
    }

    /**
     * 转换值类型.
     */
    private function castValue(string $value): mixed
    {
        // 处理数组类型（逗号分隔的字符串）
        if (str_contains($value, ',')) {
            return array_map('trim', explode(',', $value));
        }

        // 处理 JSON 数组
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $decoded = json_decode($value, true);
            if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
                return $decoded;
            }
        }

        // 处理数字
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // 处理布尔值
        if (in_array(strtolower($value), ['true', 'false'], true)) {
            return 'true' === strtolower($value);
        }

        // 处理空字符串
        if ('' === $value) {
            return null;
        }

        return $value;
    }

    /**
     * 设置掩码值.
     */
    private function setMaskedValue(array &$config, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $ref = &$config;

        foreach ($keys as $k) {
            if (!is_array($ref)) {
                $ref = [];
            }
            if (!array_key_exists($k, $ref)) {
                $ref[$k] = [];
            }
            $ref = &$ref[$k];
        }

        $ref = $value;
    }
}

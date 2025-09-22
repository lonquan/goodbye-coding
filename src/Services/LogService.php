<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Services;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * 日志服务.
 *
 * 负责应用程序的日志记录
 */
class LogService
{
    private Logger $logger;
    private string $logFile;
    private string $logLevel;
    private bool $debugMode;
    private bool $consoleOutput;

    public function __construct(
        string $logFile = './logs/migration.log',
        string $logLevel = 'info',
        bool $debugMode = false,
        string $timezone = 'PRC',
        bool $consoleOutput = true
    ) {
        $this->logFile = $logFile;
        $this->logLevel = $logLevel;
        $this->debugMode = $debugMode;
        $this->consoleOutput = $consoleOutput;

        // 设置时区
        date_default_timezone_set($timezone);

        $this->initializeLogger();
    }

    /**
     * 记录信息日志.
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * 记录警告日志.
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    /**
     * 记录错误日志.
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * 记录调试日志.
     */
    public function debug(string $message, array $context = []): void
    {
        if ($this->debugMode) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * 记录迁移开始.
     */
    public function migrationStart(string $projectName, string $repositoryName): void
    {
        $this->info('Migration started', [
            'project' => $projectName,
            'repository' => $repositoryName,
            'timestamp' => date('Y-m-d H:i:s', time()),
        ]);
    }

    /**
     * 记录迁移成功.
     */
    public function migrationSuccess(string $projectName, string $repositoryName, string $githubUrl): void
    {
        $this->info('Migration completed successfully', [
            'project' => $projectName,
            'repository' => $repositoryName,
            'github_url' => $githubUrl,
            'timestamp' => date('Y-m-d H:i:s', time()),
        ]);
    }

    /**
     * 记录迁移失败.
     */
    public function migrationFailed(string $projectName, string $repositoryName, string $error): void
    {
        $this->error('Migration failed', [
            'project' => $projectName,
            'repository' => $repositoryName,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s', time()),
        ]);
    }

    /**
     * 记录 API 调用.
     */
    public function apiCall(string $method, string $url, int $statusCode, float $duration): void
    {
        $this->debug('API call completed', [
            'method' => $method,
            'url' => $url,
            'status_code' => $statusCode,
            'duration' => $duration . 's',
        ]);
    }

    /**
     * 记录 Git 操作.
     */
    public function gitOperation(string $operation, string $path, bool $success, ?string $output = null): void
    {
        $level = $success ? 'debug' : 'error';
        $this->$level('Git operation completed', [
            'operation' => $operation,
            'path' => $path,
            'success' => $success,
            'output' => $output,
        ]);
    }

    /**
     * 记录进度更新.
     */
    public function progress(int $current, int $total, string $message = ''): void
    {
        $percentage = $total > 0 ? round(($current / $total) * 100, 2) : 0;
        $this->info('Progress update', [
            'current' => $current,
            'total' => $total,
            'percentage' => $percentage . '%',
            'message' => $message,
        ]);
    }

    /**
     * 记录配置加载.
     */
    public function configLoaded(string $source, bool $success, ?string $error = null): void
    {
        if ($success) {
            $this->info('Configuration loaded', ['source' => $source]);
        } else {
            $this->error('Configuration loading failed', [
                'source' => $source,
                'error' => $error,
            ]);
        }
    }

    /**
     * 记录异常.
     */
    public function exception(\Throwable $exception, array $context = []): void
    {
        $this->error('Exception occurred', [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context,
        ]);
    }

    /**
     * 获取日志文件路径.
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * 设置日志级别.
     */
    public function setLogLevel(string $level): self
    {
        $this->logLevel = $level;
        $this->initializeLogger();

        return $this;
    }

    /**
     * 设置调试模式.
     */
    public function setDebugMode(bool $debugMode): self
    {
        $this->debugMode = $debugMode;

        return $this;
    }

    /**
     * 设置控制台输出.
     */
    public function setConsoleOutput(bool $consoleOutput): self
    {
        $this->consoleOutput = $consoleOutput;
        $this->initializeLogger();

        return $this;
    }

    /**
     * 初始化日志记录器.
     */
    private function initializeLogger(): void
    {
        $this->logger = new Logger('migration');

        // 确保日志目录存在
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // 创建文件格式化器
        $fileFormatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s'
        );

        // 创建控制台格式化器（更简洁的格式）
        $consoleFormatter = new LineFormatter(
            "[%datetime%] %level_name%: %message%\n",
            'H:i:s'
        );

        // 添加文件处理器
        $fileHandler = new RotatingFileHandler($this->logFile, 0, $this->getLogLevelConstant());
        $fileHandler->setFormatter($fileFormatter);
        $this->logger->pushHandler($fileHandler);

        // 添加控制台处理器（如果启用）
        if ($this->consoleOutput) {
            $consoleHandler = new StreamHandler('php://stdout', $this->getLogLevelConstant());
            $consoleHandler->setFormatter($consoleFormatter);
            $this->logger->pushHandler($consoleHandler);
        }

        // 添加 PSR 日志消息处理器
        $this->logger->pushProcessor(new PsrLogMessageProcessor());
    }

    /**
     * 获取日志级别常量.
     */
    private function getLogLevelConstant(): int
    {
        return match (strtolower($this->logLevel)) {
            'debug' => Logger::DEBUG,
            'info' => Logger::INFO,
            'notice' => Logger::NOTICE,
            'warning' => Logger::WARNING,
            'error' => Logger::ERROR,
            'critical' => Logger::CRITICAL,
            'alert' => Logger::ALERT,
            'emergency' => Logger::EMERGENCY,
            default => Logger::INFO,
        };
    }
}

<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Services;

use GoodbyeCoding\Migration\Contracts\MigrationResultInterface;

/**
 * 迁移结果类.
 *
 * 封装迁移操作的结果信息
 */
class MigrationResult implements MigrationResultInterface
{
    private bool $success = true;
    private array $errors = [];
    private array $successItems = [];
    private array $details = [];

    public function __construct(array $details = [])
    {
        $this->details = $details;
    }

    /**
     * 是否成功.
     */
    public function isSuccess(): bool
    {
        return $this->success && empty($this->errors);
    }

    /**
     * 是否有错误.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * 获取错误列表.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 获取成功数量.
     */
    public function getSuccessCount(): int
    {
        return count($this->successItems);
    }

    /**
     * 获取总数量.
     */
    public function getTotalCount(): int
    {
        return $this->getSuccessCount() + count($this->errors);
    }

    /**
     * 获取详细信息.
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * 添加错误.
     */
    public function addError(string $error): self
    {
        $this->errors[] = $error;
        $this->success = false;

        return $this;
    }

    /**
     * 添加成功.
     */
    public function addSuccess(string $item): self
    {
        $this->successItems[] = $item;

        return $this;
    }

    /**
     * 设置成功状态.
     */
    public function setSuccess(bool $success): self
    {
        $this->success = $success;

        return $this;
    }

    /**
     * 设置错误列表.
     */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        $this->success = empty($errors);

        return $this;
    }

    /**
     * 设置成功项目列表.
     */
    public function setSuccessItems(array $items): self
    {
        $this->successItems = $items;

        return $this;
    }

    /**
     * 设置详细信息.
     */
    public function setDetails(array $details): self
    {
        $this->details = $details;

        return $this;
    }

    /**
     * 添加详细信息.
     */
    public function addDetail(string $key, mixed $value): self
    {
        $this->details[$key] = $value;

        return $this;
    }

    /**
     * 合并另一个结果.
     */
    public function merge(MigrationResultInterface $other): self
    {
        $this->errors = array_merge($this->errors, $other->getErrors());
        $this->successItems = array_merge($this->successItems, $other->getSuccessCount() > 0 ? $other->getDetails() : []);
        $this->success = $this->success && $other->isSuccess();

        return $this;
    }

    /**
     * 转换为数组.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->isSuccess(),
            'has_errors' => $this->hasErrors(),
            'success_count' => $this->getSuccessCount(),
            'total_count' => $this->getTotalCount(),
            'errors' => $this->errors,
            'success_items' => $this->successItems,
            'details' => $this->details,
        ];
    }
}

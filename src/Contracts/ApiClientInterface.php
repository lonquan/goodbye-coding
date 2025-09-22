<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Contracts;

/**
 * API 客户端接口.
 *
 * 定义 API 客户端的基本契约
 */
interface ApiClientInterface
{
    /**
     * 发送 GET 请求.
     */
    public function get(string $endpoint, array $query = []): array;

    /**
     * 发送 POST 请求.
     */
    public function post(string $endpoint, array $data = []): array;

    /**
     * 发送 PUT 请求.
     */
    public function put(string $endpoint, array $data = []): array;

    /**
     * 发送 DELETE 请求.
     */
    public function delete(string $endpoint): array;

    /**
     * 设置认证令牌.
     */
    public function setAuthToken(string $token): self;

    /**
     * 获取基础 URL.
     */
    public function getBaseUrl(): string;

    /**
     * 设置基础 URL.
     */
    public function setBaseUrl(string $url): self;
}

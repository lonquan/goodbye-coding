<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Services;

use GoodbyeCoding\Migration\Contracts\ApiClientInterface;
use GoodbyeCoding\Migration\Exceptions\ApiException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * GitHub API 客户端服务.
 *
 * 负责与 GitHub 平台 API 的交互
 */
class GitHubApiService implements ApiClientInterface
{
    private HttpClientInterface $httpClient;
    private string $baseUrl;
    private ?string $accessToken = null;

    public function __construct(?HttpClientInterface $httpClient = null, string $baseUrl = 'https://api.github.com')
    {
        $this->httpClient = $httpClient ?? HttpClient::create();
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * 发送 GET 请求.
     */
    public function get(string $endpoint, array $query = []): array
    {
        $url = $this->buildUrl($endpoint);
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $response = $this->httpClient->request('GET', $url, [
            'headers' => $this->getHeaders(),
        ]);

        return $this->handleResponse($response);
    }

    /**
     * 发送 POST 请求.
     */
    public function post(string $endpoint, array $data = []): array
    {
        $url = $this->buildUrl($endpoint);
        $response = $this->httpClient->request('POST', $url, [
            'headers' => $this->getHeaders(),
            'json' => $data,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * 发送 PUT 请求.
     */
    public function put(string $endpoint, array $data = []): array
    {
        $url = $this->buildUrl($endpoint);
        $response = $this->httpClient->request('PUT', $url, [
            'headers' => $this->getHeaders(),
            'json' => $data,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * 发送 DELETE 请求.
     */
    public function delete(string $endpoint): array
    {
        $url = $this->buildUrl($endpoint);
        $response = $this->httpClient->request('DELETE', $url, [
            'headers' => $this->getHeaders(),
        ]);

        return $this->handleResponse($response);
    }

    /**
     * 设置认证令牌.
     */
    public function setAuthToken(string $token): self
    {
        $this->accessToken = $token;

        return $this;
    }

    /**
     * 获取基础 URL.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * 设置基础 URL.
     */
    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/');

        return $this;
    }

    /**
     * 获取用户信息.
     */
    public function getUser(): array
    {
        return $this->get('/user');
    }

    /**
     * 获取组织信息.
     */
    public function getOrganization(string $org): array
    {
        return $this->get("/orgs/{$org}");
    }

    /**
     * 获取仓库信息.
     */
    public function getRepository(string $owner, string $repo): array
    {
        return $this->get("/repos/{$owner}/{$repo}");
    }

    /**
     * 创建仓库.
     */
    public function createRepository(string $org, array $data): array
    {
        return $this->post("/orgs/{$org}/repos", $data);
    }

    /**
     * 获取组织仓库列表.
     */
    public function getOrganizationRepositories(string $org, array $query = []): array
    {
        return $this->get("/orgs/{$org}/repos", $query);
    }

    /**
     * 检查仓库是否存在.
     */
    public function repositoryExists(string $owner, string $repo): bool
    {
        try {
            $this->getRepository($owner, $repo);

            return true;
        } catch (ApiException $e) {
            if (404 === $e->getHttpCode()) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * 构建完整 URL.
     */
    private function buildUrl(string $endpoint): string
    {
        $endpoint = ltrim($endpoint, '/');

        return $this->baseUrl . '/' . $endpoint;
    }

    /**
     * 获取请求头.
     */
    private function getHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'GoodbyeCoding-Migration-Tool/1.0.0',
        ];

        if ($this->accessToken) {
            $headers['Authorization'] = 'token ' . $this->accessToken;
        }

        return $headers;
    }

    /**
     * 处理响应.
     */
    private function handleResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $content = $response->toArray(false);

        // 处理 HTTP 错误状态码
        if ($statusCode >= 400) {
            $this->handleHttpError($statusCode, $content);
        }

        return $content;
    }

    /**
     * 处理 HTTP 错误.
     */
    private function handleHttpError(int $statusCode, array $content): void
    {
        $message = $content['message'] ?? 'Unknown error';
        $context = ['status_code' => $statusCode, 'response' => $content];

        match ($statusCode) {
            401 => throw ApiException::unauthorized($message, $context),
            404 => throw ApiException::notFound($message, $context),
            403 => $this->handleRateLimitError($message, $context),
            422 => throw ApiException::validation($message, $context),
            default => throw ApiException::server($message, $context, $statusCode, json_encode($content)),
        };
    }

    /**
     * 处理频率限制错误.
     */
    private function handleRateLimitError(string $message, array $context): void
    {
        // 检查是否是频率限制错误
        if (str_contains($message, 'rate limit') || str_contains($message, 'API rate limit')) {
            throw ApiException::rateLimit($message, $context);
        }

        // 其他 403 错误
        throw new ApiException($message, 403, null, $context, 403);
    }
}

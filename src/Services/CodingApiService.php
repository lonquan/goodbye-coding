<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Services;

use GoodbyeCoding\Migration\Contracts\ApiClientInterface;
use GoodbyeCoding\Migration\Exceptions\ApiException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Coding API 客户端服务.
 *
 * 负责与 Coding 平台 API 的交互
 */
class CodingApiService implements ApiClientInterface
{
    private HttpClientInterface $httpClient;
    private string $baseUrl;
    private ?string $accessToken = null;

    public function __construct(?HttpClientInterface $httpClient = null, string $baseUrl = 'https://e.coding.net')
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
     * 获取项目列表.
     */
    public function getProjects(array $query = []): array
    {
        $data = [
            'Action' => 'DescribeUserProjects',
            'PageNumber' => $query['page'] ?? 1,
            'PageSize' => $query['pageSize'] ?? 20,
        ];

        $response = $this->post('/open-api', $data);

        // Coding API 响应格式: {"Response": {...}}
        if (!isset($response['Response'])) {
            throw ApiException::server('Invalid response format from Coding API');
        }

        return $response['Response'] ?? [];
    }

    /**
     * 获取项目仓库列表.
     */
    public function getRepositories(int $projectId, array $query = []): array
    {
        $data = [
            'Action' => 'DescribeProjectDepotInfoList',
            'ProjectId' => $projectId,
            'PageNumber' => $query['page'] ?? 1,
            'PageSize' => $query['pageSize'] ?? 20,
        ];

        $response = $this->post('/open-api', $data);

        // Coding API 响应格式: {"Response": {...}}
        if (!isset($response['Response'])) {
            throw ApiException::server('Invalid response format from Coding API');
        }

        return $response['Response'] ?? [];
    }

    /**
     * 获取仓库详情.
     */
    public function getRepositoryDetails(int $projectId, int $repositoryId): array
    {
        $data = [
            'Action' => 'DescribeDepotInfo',
            'ProjectId' => $projectId,
            'DepotId' => $repositoryId,
        ];

        $response = $this->post('/open-api', $data);

        // 添加调试日志
        error_log("Coding API Response for project {$projectId}, repository {$repositoryId}: " . json_encode($response, JSON_PRETTY_PRINT));

        // Coding API 响应格式: {"Response": {"code": 0, "message": "success", "data": {...}}}
        if (!isset($response['Response'])) {
            error_log("Invalid response format - missing 'Response' key. Full response: " . json_encode($response, JSON_PRETTY_PRINT));

            throw ApiException::server('Invalid response format from Coding API');
        }

        $responseData = $response['Response'];

        // 检查是否有错误
        if (isset($responseData['Error'])) {
            $error = $responseData['Error'];
            $message = $error['Message'] ?? 'Unknown error';
            $code = $error['Code'] ?? 'UnknownError';
            error_log("Coding API error - Code: {$code}, Message: {$message}, Full response: " . json_encode($responseData, JSON_PRETTY_PRINT));

            throw ApiException::server("Coding API error: {$message} (Code: {$code})");
        }

        // 检查响应状态
        if (!isset($responseData['code']) || 0 !== $responseData['code']) {
            $message = $responseData['message'] ?? 'Unknown error';
            $code = $responseData['code'] ?? 'no_code';
            error_log("Coding API error - Code: {$code}, Message: {$message}, Full response: " . json_encode($responseData, JSON_PRETTY_PRINT));

            throw ApiException::server("Coding API error: {$message} (Code: {$code})");
        }

        // 返回仓库详细信息
        return $responseData['data'] ?? [];
    }

    /**
     * 获取团队仓库列表.
     *
     * 使用 DescribeTeamDepotInfoList API 获取团队下的所有仓库
     */
    public function getTeamDepotInfoList(int $pageNumber = 1, int $pageSize = 100): array
    {
        $data = [
            'Action' => 'DescribeTeamDepotInfoList',
            'PageNumber' => $pageNumber,
            'PageSize' => $pageSize,
        ];

        $response = $this->post('/open-api', $data);

        // Coding API 响应格式: {"Response": {...}}
        if (!isset($response['Response'])) {
            throw ApiException::server('Invalid response format from Coding API');
        }

        return $response['Response'] ?? [];
    }

    /**
     * 获取所有团队仓库列表（分页获取）.
     *
     * 自动处理分页，返回所有仓库
     */
    public function getAllTeamDepotInfoList(): array
    {
        $allRepositories = [];
        $pageNumber = 1;
        $pageSize = 100;

        do {
            $response = $this->getTeamDepotInfoList($pageNumber, $pageSize);

            // 根据实际 API 响应格式调整
            $depotData = $response['DepotData'] ?? [];
            $repositories = $depotData['Depots'] ?? [];
            $allRepositories = array_merge($allRepositories, $repositories);

            // 检查分页信息
            $pageInfo = $depotData['Page'] ?? [];
            $totalPages = $pageInfo['TotalPage'] ?? 1;
            $pageNumber++;
        } while ($pageNumber <= $totalPages && !empty($repositories));

        return $allRepositories;
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
            'Accept' => 'application/json',
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

        // 记录调试信息
        if (isset($_ENV['DEBUG']) && 'true' === $_ENV['DEBUG']) {
            error_log(sprintf(
                'Coding API Response: Status=%d, Content=%s',
                $statusCode,
                json_encode($content, JSON_UNESCAPED_UNICODE)
            ));
        }

        // 处理 HTTP 错误状态码
        if ($statusCode >= 400) {
            $this->handleHttpError($statusCode, $content);
        }

        // 处理 Coding API 特定的错误码
        if (isset($content['code']) && 0 !== $content['code']) {
            $this->handleApiError($content);
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
            403 => throw ApiException::rateLimit($message, $context),
            422 => throw ApiException::validation($message, $context),
            default => throw ApiException::server($message, $context, $statusCode, json_encode($content)),
        };
    }

    /**
     * 处理 API 错误.
     */
    private function handleApiError(array $content): void
    {
        $code = $content['code'] ?? 0;
        $message = $content['message'] ?? 'Unknown API error';
        $context = ['api_code' => $code, 'response' => $content];

        match ($code) {
            401 => throw ApiException::unauthorized($message, $context),
            404 => throw ApiException::notFound($message, $context),
            500 => throw ApiException::server($message, $context),
            default => throw new ApiException($message, $code, null, $context),
        };
    }
}

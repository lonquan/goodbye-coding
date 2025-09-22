<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Tests\Contract;

use GoodbyeCoding\Migration\Services\CodingApiService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Coding API 契约测试.
 *
 * 验证 CodingApiService 与 Coding API 的交互符合契约规范
 */
class CodingApiContractTest extends TestCase
{
    private CodingApiService $apiService;
    private MockHttpClient $mockClient;

    protected function setUp(): void
    {
        $this->mockClient = new MockHttpClient();
        $this->apiService = new CodingApiService($this->mockClient, 'https://e.coding.net');
        $this->apiService->setAuthToken('test-token');
    }

    /**
     * 测试获取项目列表 - 成功场景.
     */
    public function testGetProjectsSuccess(): void
    {
        $mockResponse = new MockResponse(
            json_encode([
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'list' => [
                        [
                            'id' => 12345,
                            'name' => 'test-project',
                            'display_name' => 'Test Project',
                            'description' => 'A test project',
                            'created_at' => '2025-01-27T10:00:00Z',
                            'updated_at' => '2025-01-27T10:00:00Z',
                            'status' => 'active',
                            'type' => 'private',
                        ],
                    ],
                    'page' => 1,
                    'pageSize' => 20,
                    'total' => 1,
                    'totalPages' => 1,
                ],
            ]),
            [
                'http_code' => 200,
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
            ]
        );

        $this->mockClient->setResponseFactory(fn () => $mockResponse);

        $result = $this->apiService->getProjects(['page' => 1, 'pageSize' => 20]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('pageSize', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('totalPages', $result);
        $this->assertCount(1, $result['list']);

        // 验证请求
        $requests = $this->mockClient->getRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('https://e.coding.net/api/user/projects?page=1&pageSize=20', $request->getUrl());
        $this->assertArrayHasKey('Authorization', $request->getOptions()['headers']);
        $this->assertEquals('token test-token', $request->getOptions()['headers']['Authorization']);
    }

    /**
     * 测试获取项目列表 - 认证失败.
     */
    public function testGetProjectsUnauthorized(): void
    {
        $mockResponse = new MockResponse(
            json_encode([
                'code' => 401,
                'message' => 'Unauthorized',
            ]),
            [
                'http_code' => 401,
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
            ]
        );

        $this->mockClient->setResponseFactory(fn () => $mockResponse);

        $this->expectException(\GoodbyeCoding\Migration\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Unauthorized');

        $this->apiService->getProjects();
    }

    /**
     * 测试获取项目列表 - API 错误响应.
     */
    public function testGetProjectsApiError(): void
    {
        $mockResponse = new MockResponse(
            json_encode([
                'code' => 500,
                'message' => 'Internal Server Error',
            ]),
            [
                'http_code' => 200,
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
            ]
        );

        $this->mockClient->setResponseFactory(fn () => $mockResponse);

        $this->expectException(\GoodbyeCoding\Migration\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Internal Server Error');

        $this->apiService->getProjects();
    }

    /**
     * 测试获取仓库列表 - 成功场景.
     */
    public function testGetRepositoriesSuccess(): void
    {
        $mockResponse = new MockResponse(
            json_encode([
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'list' => [
                        [
                            'id' => 67890,
                            'project_id' => 12345,
                            'name' => 'test-repo',
                            'display_name' => 'Test Repository',
                            'description' => 'A test repository',
                            'size' => 1048576,
                            'created_at' => '2025-01-27T10:00:00Z',
                            'updated_at' => '2025-01-27T10:00:00Z',
                            'git_url' => 'https://e.coding.net/my-team/test-project/test-repo.git',
                            'ssh_url' => 'git@e.coding.net:my-team/test-project/test-repo.git',
                            'is_public' => false,
                            'default_branch' => 'main',
                        ],
                    ],
                    'page' => 1,
                    'pageSize' => 20,
                    'total' => 1,
                    'totalPages' => 1,
                ],
            ]),
            [
                'http_code' => 200,
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
            ]
        );

        $this->mockClient->setResponseFactory(fn () => $mockResponse);

        $result = $this->apiService->getRepositories(12345, ['page' => 1, 'pageSize' => 20]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);
        $this->assertCount(1, $result['list']);

        // 验证请求
        $requests = $this->mockClient->getRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('https://e.coding.net/api/user/projects/12345/repositories?page=1&pageSize=20', $request->getUrl());
    }

    /**
     * 测试获取仓库详情 - 成功场景.
     */
    public function testGetRepositoryDetailsSuccess(): void
    {
        $mockResponse = new MockResponse(
            json_encode([
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'id' => 67890,
                    'project_id' => 12345,
                    'name' => 'test-repo',
                    'display_name' => 'Test Repository',
                    'description' => 'A test repository',
                    'size' => 1048576,
                    'created_at' => '2025-01-27T10:00:00Z',
                    'updated_at' => '2025-01-27T10:00:00Z',
                    'git_url' => 'https://e.coding.net/my-team/test-project/test-repo.git',
                    'ssh_url' => 'git@e.coding.net:my-team/test-project/test-repo.git',
                    'is_public' => false,
                    'default_branch' => 'main',
                ],
            ]),
            [
                'http_code' => 200,
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
            ]
        );

        $this->mockClient->setResponseFactory(fn () => $mockResponse);

        $result = $this->apiService->getRepositoryDetails(12345, 67890);

        $this->assertIsArray($result);
        $this->assertEquals(67890, $result['id']);
        $this->assertEquals(12345, $result['project_id']);
        $this->assertEquals('test-repo', $result['name']);

        // 验证请求
        $requests = $this->mockClient->getRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('https://e.coding.net/api/user/projects/12345/repositories/67890', $request->getUrl());
    }

    /**
     * 测试获取仓库列表 - 项目不存在.
     */
    public function testGetRepositoriesProjectNotFound(): void
    {
        $mockResponse = new MockResponse(
            json_encode([
                'code' => 404,
                'message' => 'Project not found',
            ]),
            [
                'http_code' => 404,
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
            ]
        );

        $this->mockClient->setResponseFactory(fn () => $mockResponse);

        $this->expectException(\GoodbyeCoding\Migration\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Project not found');

        $this->apiService->getRepositories(99999);
    }

    /**
     * 测试分页参数处理.
     */
    public function testPaginationParameters(): void
    {
        $mockResponse = new MockResponse(
            json_encode([
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'list' => [],
                    'page' => 2,
                    'pageSize' => 10,
                    'total' => 0,
                    'totalPages' => 0,
                ],
            ]),
            [
                'http_code' => 200,
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
            ]
        );

        $this->mockClient->setResponseFactory(fn () => $mockResponse);

        $this->apiService->getProjects([
            'page' => 2,
            'pageSize' => 10,
            'keyword' => 'test',
        ]);

        // 验证请求参数
        $requests = $this->mockClient->getRequests();
        $request = $requests[0];
        $this->assertStringContains('page=2', $request->getUrl());
        $this->assertStringContains('pageSize=10', $request->getUrl());
        $this->assertStringContains('keyword=test', $request->getUrl());
    }

    /**
     * 测试请求头设置.
     */
    public function testRequestHeaders(): void
    {
        $mockResponse = new MockResponse(
            json_encode([
                'code' => 0,
                'message' => 'success',
                'data' => ['list' => [], 'page' => 1, 'pageSize' => 20, 'total' => 0, 'totalPages' => 0],
            ]),
            ['http_code' => 200]
        );

        $this->mockClient->setResponseFactory(fn () => $mockResponse);

        $this->apiService->getProjects();

        $requests = $this->mockClient->getRequests();
        $request = $requests[0];
        $headers = $request->getOptions()['headers'];

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('token test-token', $headers['Authorization']);
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertArrayHasKey('Accept', $headers);
        $this->assertEquals('application/json', $headers['Accept']);
    }
}

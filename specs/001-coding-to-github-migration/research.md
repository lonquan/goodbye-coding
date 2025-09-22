# Research: Coding API 修复

**Date**: 2025-01-27  
**Context**: 修复 Coding API 调用问题，解决 "Syntax error for https://e.coding.net/projects" 错误

## 问题分析

### 当前错误
- 错误信息：`Syntax error for "https://e.coding.net/projects"`
- 发生位置：执行 `./bin/migration.php migrate` 时
- 影响：无法获取 Coding 上的仓库列表

### 根本原因分析
通过日志分析发现：
1. 当前 API 调用使用错误的端点：`/projects`
2. 认证方式可能不正确
3. 请求格式不符合 Coding OpenAPI 规范

## 技术研究

### Coding OpenAPI 规范

**Decision**: 使用正确的 Coding OpenAPI 端点和认证方式

**Rationale**: 
- Coding OpenAPI 使用 RESTful 风格设计
- 所有请求地址都是可预期的
- 使用规范的 HTTP 响应代码标识结果

**API 端点规范**:
- 基础 URL: `https://e.coding.net`
- 获取项目列表: `GET /api/user/projects`
- 获取项目仓库: `GET /api/user/projects/{projectId}/repositories`

**认证方式**:
- 使用 Personal Access Token
- 请求头格式: `Authorization: token YOUR_PERSONAL_ACCESS_TOKEN`
- 或者使用 Basic Auth: `-u username:token`

**请求头要求**:
```http
Authorization: token YOUR_PERSONAL_ACCESS_TOKEN
Content-Type: application/json
Accept: application/json
```

### 响应格式

**Decision**: 处理 Coding API 的标准响应格式

**Rationale**: Coding API 返回规范的 JSON 对象格式

**响应结构**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [...],
    "page": 1,
    "pageSize": 20,
    "total": 100
  }
}
```

**错误处理**:
- `code !== 0` 表示错误
- `message` 字段包含错误信息
- HTTP 状态码用于 HTTP 级别错误

### 参考实现分析

**Decision**: 参考 Go 语言实现中的正确模式

**Rationale**: 现有实现展示了正确的 API 调用方式

**关键模式**:
1. 正确的端点路径
2. 适当的认证头设置
3. 错误响应处理
4. 分页参数处理

## 修复方案

### 1. 修正 API 端点

**当前问题**:
```php
$response = $this->get('/projects', $query);
```

**修复方案**:
```php
$response = $this->get('/api/user/projects', $query);
```

### 2. 修正认证头格式

**当前问题**:
```php
$headers['Authorization'] = 'token ' . $this->accessToken;
```

**修复方案**:
```php
$headers['Authorization'] = 'token ' . $this->accessToken;
```
（格式正确，但需要确保 token 有效）

### 3. 改进错误处理

**当前问题**: 没有正确处理 Coding API 的响应格式

**修复方案**:
```php
if ($response['code'] !== 0) {
    throw ApiException::server($response['message'] ?? 'API request failed');
}
```

### 4. 添加调试信息

**决策**: 添加详细的请求和响应日志

**理由**: 便于调试和问题排查

**实现**:
- 记录请求 URL 和参数
- 记录响应状态码和内容
- 在调试模式下输出详细信息

## 测试策略

### 单元测试
- 测试正确的 API 端点调用
- 测试认证头设置
- 测试响应解析

### 集成测试
- 测试与真实 Coding API 的交互
- 测试错误场景处理
- 测试分页功能

### 契约测试
- 验证 API 请求格式
- 验证响应格式
- 验证错误处理

## 实施优先级

1. **高优先级**: 修正 API 端点路径
2. **高优先级**: 验证认证 token 有效性
3. **中优先级**: 改进错误处理和日志记录
4. **低优先级**: 添加更多调试信息

## 风险评估

**低风险**: 
- 修改范围小，影响有限
- 有明确的参考实现
- 错误信息明确指向问题

**缓解措施**:
- 先写测试确保修复正确
- 使用 dry-run 模式验证
- 保留原有错误处理逻辑

## 后续计划

1. 实施修复方案
2. 编写测试用例
3. 验证修复效果
4. 更新文档
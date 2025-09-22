# Quickstart: Coding API 修复验证

**Date**: 2025-01-27  
**Context**: 验证 Coding API 修复是否成功

## 前置条件

1. **环境要求**:
   - PHP 8.4+
   - Composer 2.x
   - Git 环境
   - 网络访问 Coding 和 GitHub

2. **配置要求**:
   - 有效的 Coding Personal Access Token
   - 有效的 GitHub Personal Access Token
   - 配置文件已正确设置

## 验证步骤

### 步骤 1: 检查当前配置

```bash
# 检查配置文件
cat config/migration.php

# 检查环境变量
cat .env
```

**预期结果**: 
- 配置文件包含正确的 Coding 和 GitHub 配置
- 环境变量文件包含有效的 API Token

### 步骤 2: 运行配置命令

```bash
# 检查配置状态
./bin/migration.php config:check

# 设置 Coding Token（如果未设置）
./bin/migration.php config:set coding.access_token YOUR_CODING_TOKEN

# 设置 GitHub Token（如果未设置）
./bin/migration.php config:set github.access_token YOUR_GITHUB_TOKEN
```

**预期结果**:
- 配置检查通过
- 所有必需的配置项都已设置

### 步骤 3: 测试 Coding API 连接

```bash
# 运行状态命令，测试 API 连接
./bin/migration.php status

# 或者直接测试项目列表获取
./bin/migration.php migrate --dry-run --verbose
```

**预期结果**:
- 成功连接到 Coding API
- 能够获取项目列表
- 没有 "Syntax error" 错误

### 步骤 4: 验证 API 端点修复

**检查修复内容**:

1. **API 端点**:
   - 从 `/projects` 改为 `/api/user/projects`
   - 从 `/projects/{id}/repositories` 改为 `/api/user/projects/{id}/repositories`

2. **认证头**:
   - 确保使用 `Authorization: token YOUR_TOKEN` 格式
   - 确保 Content-Type 和 Accept 头正确设置

3. **响应处理**:
   - 正确处理 Coding API 的响应格式
   - 正确解析 `code` 字段判断成功/失败

### 步骤 5: 运行单元测试

```bash
# 运行 Coding API 相关测试
./vendor/bin/phpunit tests/Contract/CodingApiContractTest.php

# 运行所有测试
./vendor/bin/phpunit
```

**预期结果**:
- 所有测试通过
- 契约测试验证 API 调用格式正确

### 步骤 6: 运行集成测试

```bash
# 运行端到端测试
./bin/migration.php migrate --dry-run --verbose --concurrent-limit=1
```

**预期结果**:
- 成功获取 Coding 项目列表
- 成功获取每个项目的仓库列表
- 显示详细的迁移计划
- 没有 API 调用错误

## 成功标准

### 功能验证
- [ ] 能够成功调用 Coding API 获取项目列表
- [ ] 能够成功获取项目下的仓库列表
- [ ] 没有 "Syntax error" 错误
- [ ] API 响应正确解析

### 错误处理验证
- [ ] 认证失败时显示正确错误信息
- [ ] 网络错误时有重试机制
- [ ] 无效响应时有适当错误处理

### 性能验证
- [ ] API 调用响应时间 < 5 秒
- [ ] 支持分页参数
- [ ] 支持搜索参数

## 故障排除

### 常见问题

1. **仍然出现 "Syntax error"**:
   - 检查 API 端点是否正确
   - 检查认证头格式
   - 检查请求 URL 构建

2. **认证失败**:
   - 验证 Token 是否有效
   - 检查 Token 权限
   - 确认 Token 格式正确

3. **网络连接问题**:
   - 检查网络连接
   - 检查防火墙设置
   - 验证 Coding 服务状态

### 调试命令

```bash
# 启用详细输出
./bin/migration.php migrate --dry-run --verbose -vvv

# 检查日志
tail -f logs/migration.log

# 测试特定项目
./bin/migration.php migrate --project=12345 --dry-run --verbose
```

## 验证报告

### 测试结果记录

**测试时间**: [填写测试时间]  
**测试环境**: [填写环境信息]  
**测试人员**: [填写测试人员]

**功能测试结果**:
- [ ] 项目列表获取: 通过/失败
- [ ] 仓库列表获取: 通过/失败
- [ ] 错误处理: 通过/失败
- [ ] 性能表现: 通过/失败

**问题记录**:
- [记录发现的问题]

**修复建议**:
- [记录修复建议]

## 后续步骤

1. **如果验证通过**:
   - 提交代码更改
   - 更新文档
   - 准备生产环境部署

2. **如果验证失败**:
   - 分析失败原因
   - 修复问题
   - 重新验证

3. **性能优化**:
   - 监控 API 调用性能
   - 优化并发处理
   - 添加缓存机制
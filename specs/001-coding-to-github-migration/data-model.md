# Data Model: Coding API 修复

**Date**: 2025-01-27  
**Context**: 修复 Coding API 调用问题

## 核心实体

### CodingProject
Coding 项目实体，表示 Coding 平台上的项目

**字段**:
- `id` (int): 项目唯一标识符
- `name` (string): 项目名称
- `display_name` (string): 项目显示名称
- `description` (string): 项目描述
- `created_at` (DateTime): 创建时间
- `updated_at` (DateTime): 更新时间
- `status` (string): 项目状态
- `type` (string): 项目类型

**验证规则**:
- `id` 必须为正整数
- `name` 不能为空，最大长度 100 字符
- `display_name` 不能为空，最大长度 200 字符
- `created_at` 和 `updated_at` 必须为有效的 ISO 8601 格式

### CodingRepository
Coding 仓库实体，表示项目下的代码仓库

**字段**:
- `id` (int): 仓库唯一标识符
- `project_id` (int): 所属项目 ID
- `name` (string): 仓库名称
- `display_name` (string): 仓库显示名称
- `description` (string): 仓库描述
- `size` (int): 仓库大小（字节）
- `created_at` (DateTime): 创建时间
- `updated_at` (DateTime): 更新时间
- `git_url` (string): Git 克隆 URL
- `ssh_url` (string): SSH 克隆 URL
- `is_public` (bool): 是否公开
- `default_branch` (string): 默认分支

**验证规则**:
- `id` 必须为正整数
- `project_id` 必须为正整数
- `name` 不能为空，最大长度 100 字符
- `git_url` 和 `ssh_url` 必须为有效的 URL 格式
- `size` 必须为非负整数

### ApiResponse
API 响应包装实体

**字段**:
- `code` (int): 响应代码（0 表示成功）
- `message` (string): 响应消息
- `data` (mixed): 响应数据
- `request_id` (string): 请求 ID（用于追踪）

**验证规则**:
- `code` 必须为整数
- `message` 不能为空
- 成功时 `code` 必须为 0

### PaginationInfo
分页信息实体

**字段**:
- `page` (int): 当前页码
- `pageSize` (int): 每页大小
- `total` (int): 总记录数
- `totalPages` (int): 总页数

**验证规则**:
- `page` 必须为正整数
- `pageSize` 必须为正整数，最大 100
- `total` 必须为非负整数
- `totalPages` 必须为非负整数

## 关系

### CodingProject -> CodingRepository
- 一对多关系
- 一个项目可以有多个仓库
- 通过 `project_id` 外键关联

### ApiResponse -> PaginationInfo
- 可选包含关系
- 当 API 返回分页数据时，`data` 字段包含分页信息

## 状态转换

### CodingProject 状态
- `active`: 活跃项目
- `archived`: 已归档项目
- `suspended`: 已暂停项目

### CodingRepository 状态
- `active`: 活跃仓库
- `archived`: 已归档仓库
- `readonly`: 只读仓库

## 数据验证

### 输入验证
- 所有字符串字段进行长度和格式验证
- 所有数字字段进行范围和类型验证
- 日期字段进行格式验证

### 业务规则
- 项目名称在组织内必须唯一
- 仓库名称在项目内必须唯一
- 创建时间不能晚于更新时间

## 错误处理

### API 错误码映射
- `401`: 认证失败
- `403`: 权限不足
- `404`: 资源不存在
- `422`: 参数验证失败
- `500`: 服务器内部错误

### 异常类型
- `ApiException`: API 调用异常
- `ValidationException`: 数据验证异常
- `AuthenticationException`: 认证异常
- `PermissionException`: 权限异常
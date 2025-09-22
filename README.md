# Coding 到 GitHub 代码仓库迁移工具

一个强大的CLI工具，用于将Coding平台的代码仓库迁移到GitHub平台。

## 功能特性

- 🚀 **批量迁移**: 支持迁移所有项目或指定项目
- ⚡ **并发处理**: 支持并发迁移，提高效率
- 🔧 **灵活配置**: 支持多种配置方式和环境变量
- 📊 **进度跟踪**: 实时显示迁移进度和状态
- 🛡️ **错误处理**: 完善的错误处理和重试机制
- 📝 **详细日志**: 完整的操作日志记录
- 🔍 **状态检查**: 检查工具状态和API连接
- 🎯 **精确控制**: 支持指定项目、仓库迁移
- 🎨 **交互式界面**: 友好的交互式选择界面，支持仓库预览和确认
- 🔍 **智能检查**: 自动检查GitHub仓库存在性，支持覆盖策略配置

## 安装

### 使用 Composer

```bash
composer install
```

### 环境配置

1. 复制环境变量示例文件：
```bash
cp .env.example .env
```

2. 编辑 `.env` 文件，填入你的配置：
```env
# 仅支持以下三个配置项，其他配置将被忽略

# CODING 访问令牌
CODING_ACCESS_TOKEN=your_coding_access_token_here

# GitHub 访问令牌
GITHUB_ACCESS_TOKEN=your_github_access_token_here

# GitHub 组织名称
GITHUB_ORGANIZATION=your_github_organization_here
```

## 使用方法

### 基本命令

```bash
# 查看帮助
php bin/migration.php --help

# 检查工具状态
php bin/migration.php status

# 查看配置
php bin/migration.php config --show

# 验证配置
php bin/migration.php config --validate
```

### 迁移命令

```bash
# 交互式迁移（推荐）
php bin/migration.php migrate

# 使用自定义配置文件
php bin/migration.php migrate --config ./my-config.php
```

### 配置管理

```bash
# 显示当前配置
php bin/migration.php config --show

# 显示配置（隐藏敏感信息）
php bin/migration.php config --show --masked

# 验证配置
php bin/migration.php config --validate

# 设置配置值
php bin/migration.php config --set coding.access_token=your_token
php bin/migration.php config --set migration.concurrent_limit=5

# 指定配置文件
php bin/migration.php config --file ./my-config.yaml
```

### 状态检查

```bash
# 基本状态检查
php bin/migration.php status

# 检查API连接
php bin/migration.php status --check-api

# 检查Git环境
php bin/migration.php status --check-git

# 详细输出
php bin/migration.php status --verbose
```

## 配置说明

### 环境变量

**仅支持以下三个环境变量，其他配置将被忽略：**

| 变量名 | 说明 | 必需 |
|--------|------|------|
| `CODING_ACCESS_TOKEN` | Coding访问令牌 | 是 |
| `GITHUB_ACCESS_TOKEN` | GitHub访问令牌 | 是 |
| `GITHUB_ORGANIZATION` | GitHub组织名称 | 是 |

### 配置文件

支持PHP数组格式的配置文件，默认位置：`./config/migration.php`

**注意：** 敏感信息（访问令牌）应通过环境变量设置，配置文件中应设置为 `null`。

```php
<?php

return [
    'coding' => [
        'access_token' => null, // 从环境变量 CODING_ACCESS_TOKEN 获取
        'base_url' => 'https://e.coding.net',
    ],
    
    'github' => [
        'access_token' => null, // 从环境变量 GITHUB_ACCESS_TOKEN 获取
        'base_url' => 'https://api.github.com',
        'organization' => null, // 从环境变量 GITHUB_ORGANIZATION 获取
        'overwrite_existing' => true,
    ],
    
    'migration' => [
        'concurrent_limit' => 3,
        'temp_directory' => './temp',
        'max_retry_attempts' => 3,
        'retry_delay_seconds' => 5,
        'debug_mode' => false,
        'verbose_output' => false,
        'timeout' => 300,
        'rate_limit' => 60,
    ],
];
```

## 开发

### 运行测试

```bash
# 运行所有测试
composer test

# 运行特定测试
vendor/bin/phpunit tests/Integration/ConcurrentMigrationTest.php

# 生成测试覆盖率报告
composer test-coverage
```

### 代码质量检查

```bash
# 代码风格检查
composer cs-check

# 修复代码风格
composer cs-fix

# 静态分析
composer phpstan

# 运行所有质量检查
composer quality
```

### 项目结构

```
src/
├── Commands/          # Symfony Console 命令
├── Contracts/         # 接口定义
├── Exceptions/        # 异常类
├── Services/          # 核心服务
└── Utils/            # 工具类

tests/
├── Contract/         # 契约测试
├── Integration/      # 集成测试
└── Unit/            # 单元测试

config/              # 配置文件
logs/                # 日志文件
temp/                # 临时文件
```

## 故障排除

### 常见问题

1. **配置验证失败**
   - 检查环境变量是否正确设置
   - 验证访问令牌是否有效
   - 确认GitHub组织名称正确

2. **API连接失败**
   - 检查网络连接
   - 验证访问令牌权限
   - 确认API地址正确

3. **Git操作失败**
   - 检查Git是否正确安装
   - 验证Git用户配置
   - 确认仓库权限

4. **权限问题**
   - 检查文件/目录权限
   - 确认临时目录可写
   - 验证GitHub仓库创建权限

### 调试模式

启用详细输出模式获取更详细的信息：

```bash
php bin/migration.php migrate --verbose
```

## 许可证

MIT License

## 贡献

欢迎提交Issue和Pull Request！

## 更新日志

### v1.1.0
- ✨ **新增交互式迁移流程**: 支持用户友好的仓库选择和预览界面
- ✨ **智能仓库检查**: 自动检查GitHub仓库存在性，支持多种覆盖策略
- ✨ **迁移计划预览**: 显示源仓库到目标仓库的完整映射关系
- ✨ **增强的用户体验**: 默认全选仓库，支持自定义选择
- 🔧 **配置增强**: 新增 `overwrite_existing` 配置选项
- 📚 **文档更新**: 完善的使用说明和示例

### v1.0.0
- 初始版本发布
- 支持基本的仓库迁移功能
- 提供完整的CLI界面
- 支持并发迁移
- 完善的错误处理和日志记录

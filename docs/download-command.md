# 下载命令使用说明

## 命令概述

`download` 命令用于将Coding平台的所有代码仓库下载到本地指定文件夹，按照 `projectName/repo` 的结构进行存放。

## 命令语法

```bash
php bin/migration.php download [选项]
```

## 可用选项

| 选项 | 简写 | 必需 | 默认值 | 说明 |
|------|------|------|--------|------|
| `--config` | - | 否 | `./config/migration.php` | 配置文件路径 |
| `--output-dir` | `-o` | 是 | `./downloads` | 输出目录路径 |
| `--exclude-empty` | - | 否 | `false` | 排除空仓库 |
| `--concurrent` | `-c` | 否 | `3` | 并发下载数量 |

## 使用示例

### 基本用法

```bash
# 下载所有仓库到默认目录 ./downloads
php bin/migration.php download

# 下载所有仓库到指定目录
php bin/migration.php download --output-dir /path/to/downloads

# 使用简写选项
php bin/migration.php download -o /path/to/downloads
```

### 高级用法

```bash
# 排除空仓库
php bin/migration.php download --exclude-empty

# 设置并发下载数量为5
php bin/migration.php download --concurrent 5

# 组合使用多个选项
php bin/migration.php download -o /path/to/downloads --exclude-empty -c 5
```

## 目录结构

下载后的目录结构将按照以下格式组织：

```
output-dir/
├── project1/
│   ├── repo1/
│   │   ├── .git/
│   │   ├── src/
│   │   ├── README.md
│   │   └── ...
│   └── repo2/
│       ├── .git/
│       ├── src/
│       └── ...
├── project2/
│   ├── repo1/
│   └── repo2/
└── ...
```

## 功能特性

### 1. 智能目录管理
- 自动创建项目目录和仓库目录
- 按照 `projectName/repo` 结构组织文件
- 避免目录名冲突

### 2. 空仓库处理
- 可选择排除空仓库（使用 `--exclude-empty` 选项）
- 自动检测并清理空仓库

### 3. 并发下载
- 支持并发下载多个仓库
- 可配置并发数量（使用 `--concurrent` 选项）
- 提高下载效率

### 4. 错误处理
- 详细的错误信息和日志记录
- 下载失败时自动清理临时文件
- 继续处理其他仓库

### 5. 进度显示
- 实时显示下载进度
- 彩色输出和状态图标
- 详细的统计信息

## 输出信息

命令执行过程中会显示以下信息：

1. **仓库发现**：显示找到的仓库数量
2. **下载计划**：显示输出目录、排除设置、并发数量等
3. **仓库列表**：显示源仓库、本地路径、描述、时间等信息
4. **下载进度**：实时显示每个仓库的下载状态
5. **结果统计**：显示总数量、成功数量、跳过数量、错误数量

## 注意事项

1. **权限要求**：确保对输出目录有写入权限
2. **网络连接**：需要能够访问Coding平台
3. **存储空间**：确保有足够的磁盘空间存储所有仓库
4. **SSH配置**：如果使用SSH克隆，需要配置SSH密钥
5. **配置文件**：确保配置文件中的Coding访问令牌有效

## 配置文件

下载命令使用与迁移命令相同的配置文件，主要需要配置：

```php
return [
    'coding' => [
        'access_token' => 'your_coding_access_token',
        'base_url' => 'https://e.coding.net',
    ],
    // ... 其他配置
];
```

## 日志记录

下载过程中的所有操作都会记录到日志文件中，包括：
- 仓库发现和筛选
- 下载开始和完成
- 错误和异常信息
- 统计信息

日志文件位置：`logs/migration-{timestamp}.log`

# 项目目录结构说明

## 概述

本文档详细描述了 Coding 到 GitHub 代码仓库迁移工具的项目目录结构。这是一个纯 CLI 项目，不包含数据模型，专注于命令行工具的核心功能。

## 根目录结构

```
migration/
├── bin/                          # 可执行文件目录
│   └── migration.php             # 主命令行入口
├── src/                          # 源代码目录
│   ├── Services/                 # 业务服务
│   ├── Commands/                 # Symfony Console 命令
│   ├── Contracts/                # 接口定义
│   ├── Exceptions/               # 异常类
│   └── Utils/                    # 工具类
├── tests/                        # 测试目录
│   ├── Unit/                     # 单元测试
│   ├── Integration/              # 集成测试
│   ├── Contract/                 # 契约测试
│   └── Fixtures/                 # 测试数据
├── temp/                         # 临时文件目录（自动创建）
│   ├── repositories/             # 临时克隆的仓库
│   │   ├── project1-repo1/       # 按项目-仓库命名
│   │   ├── project1-repo2/
│   │   └── project2-repo1/
├── logs/                         # 日志文件目录（自动创建）
│   ├── migration.log             # 主日志文件
│   ├── error.log                 # 错误日志文件
│   └── debug.log                 # 调试日志文件
├── config/                       # 配置文件目录
│   ├── services.yaml             # 服务配置
│   └── logging.yaml              # 日志配置
├── .env                          # 环境变量配置（不提交到 Git）
├── .env.example                  # 环境变量配置示例
├── composer.json                 # Composer 依赖配置
├── composer.lock                 # Composer 锁定文件
├── phpunit.xml.dist              # PHPUnit 测试配置
├── phpstan.neon                  # PHPStan 静态分析配置
├── .php-cs-fixer.php             # PHP CS Fixer 配置
├── README.md                     # 项目说明文档
└── .gitignore                    # Git 忽略文件配置
```

## 详细目录说明

### 1. bin/ 目录
存放可执行文件，包含主命令行入口。

**文件说明**:
- `migration.php`: 主命令行入口文件，负责解析命令行参数和调用相应的命令

### 2. src/ 目录
存放所有源代码文件，按功能模块组织。

#### 2.1 Services/ 子目录
存放业务服务类，实现核心业务逻辑。

**文件列表**:
- `CodingApiService.php`: Coding API 客户端服务
- `GitHubApiService.php`: GitHub API 客户端服务
- `GitService.php`: Git 操作服务
- `MigrationService.php`: 迁移核心服务
- `ConfigService.php`: 配置管理服务
- `LogService.php`: 日志记录服务
- `InteractiveSelectionService.php`: 交互式选择服务
- `ProgressDisplayService.php`: 进度显示服务

#### 2.2 Commands/ 子目录
存放 Symfony Console 命令类。

**文件列表**:
- `MigrateCommand.php`: 迁移命令
- `ConfigCommand.php`: 配置命令
- `StatusCommand.php`: 状态查询命令

#### 2.3 Contracts/ 子目录
存放接口定义，定义服务契约。

**文件列表**:
- `ApiClientInterface.php`: API 客户端接口
- `GitServiceInterface.php`: Git 服务接口
- `MigrationServiceInterface.php`: 迁移服务接口

#### 2.4 Exceptions/ 子目录
存放自定义异常类。

**文件列表**:
- `MigrationException.php`: 迁移基础异常
- `ApiException.php`: API 调用异常
- `GitException.php`: Git 操作异常
- `ConfigException.php`: 配置异常

#### 2.5 Utils/ 子目录
存放工具类和辅助函数。

**文件列表**:
- `PathHelper.php`: 路径处理工具
- `StringHelper.php`: 字符串处理工具
- `ArrayHelper.php`: 数组处理工具
- `FileHelper.php`: 文件处理工具

### 3. tests/ 目录
存放所有测试文件，按测试类型组织。

#### 3.1 Unit/ 子目录
存放单元测试文件。

**文件列表**:
- `Services/`: 服务类单元测试
- `Commands/`: 命令类单元测试
- `Utils/`: 工具类单元测试

#### 3.2 Integration/ 子目录
存放集成测试文件。

**文件列表**:
- `ApiIntegrationTest.php`: API 集成测试
- `GitIntegrationTest.php`: Git 集成测试
- `MigrationIntegrationTest.php`: 迁移集成测试

#### 3.3 Contract/ 子目录
存放契约测试文件。

**文件列表**:
- `CodingApiContractTest.php`: Coding API 契约测试
- `GitHubApiContractTest.php`: GitHub API 契约测试

#### 3.4 Fixtures/ 子目录
存放测试数据和模拟文件。

**文件列表**:
- `api-responses/`: API 响应模拟数据
- `git-repositories/`: Git 仓库测试数据
- `configs/`: 配置文件测试数据

### 4. temp/ 目录
存放临时文件，在迁移过程中自动创建。

#### 4.1 repositories/ 子目录
存放临时克隆的仓库，按 `{project_name}-{repository_name}` 格式命名。

**目录结构**:
```
temp/repositories/
├── my-project-main-repo/         # 克隆的仓库目录
│   ├── .git/                     # Git 元数据
│   ├── src/                      # 源代码
│   ├── README.md                 # 项目文档
│   └── ...                       # 其他文件
├── my-project-api-service/
└── test-proj-frontend/
```


### 5. logs/ 目录
存放日志文件，按日志类型分类。

#### 5.1 日志文件说明
- `migration.log`: 主日志文件，记录所有迁移操作
- `error.log`: 错误日志文件，只记录错误信息
- `debug.log`: 调试日志文件，记录详细的调试信息

#### 5.2 日志轮转
日志文件支持按大小和时间轮转：
- 单个日志文件最大 10MB
- 保留最近 7 天的日志文件
- 自动压缩历史日志文件

### 6. config/ 目录
存放配置文件，支持 YAML 格式。

#### 6.1 services.yaml
服务容器配置，定义服务依赖关系。

#### 6.2 logging.yaml
日志配置，定义日志级别和输出格式。

## 目录创建规则

### 1. 自动创建目录
以下目录在首次运行时自动创建：
- `temp/` 及其子目录
- `logs/` 及其子目录
- `config/` 目录（如果不存在）

### 2. 权限设置
- `temp/` 目录：读写权限（755）
- `logs/` 目录：读写权限（755）
- `config/` 目录：读写权限（755）

### 3. 清理规则
- 迁移完成后自动清理 `temp/repositories/` 中的临时仓库
- 日志文件按配置规则轮转和清理

## 环境变量配置

### 1. 路径相关配置
```env
# 临时目录（相对于项目根目录）
TEMP_DIRECTORY=./temp

# 日志文件路径（相对于项目根目录）
LOG_FILE=./logs/migration.log

# 配置文件目录（相对于项目根目录）
CONFIG_DIR=./config
```

### 2. 目录创建配置
```env
# 是否自动创建目录
AUTO_CREATE_DIRECTORIES=true

# 临时目录权限
TEMP_DIR_PERMISSIONS=0755

# 日志目录权限
LOG_DIR_PERMISSIONS=0755
```

## 最佳实践

### 1. 目录管理
- 使用相对路径，便于项目迁移
- 定期清理临时文件，避免磁盘空间不足
- 合理设置日志轮转，避免日志文件过大

### 2. 权限管理
- 确保应用有足够的权限创建和写入目录
- 避免在系统目录中创建临时文件
- 使用项目相对路径，避免权限问题

### 3. 性能优化
- 合理设置并发数量，避免资源竞争
- 及时清理不需要的临时文件

## 故障排除

### 1. 目录创建失败
- 检查父目录权限
- 确保磁盘空间充足
- 验证路径格式正确

### 2. 权限问题
- 检查目录权限设置
- 确保用户有写入权限
- 避免使用系统保护目录

### 3. 路径问题
- 使用绝对路径避免相对路径问题
- 检查路径分隔符（Windows vs Unix）
- 验证路径长度限制

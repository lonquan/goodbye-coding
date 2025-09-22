# Tasks: Coding 到 GitHub 代码仓库迁移工具

**Input**: Design documents from `/specs/001-coding-to-github-migration/`
**Prerequisites**: plan.md (required), research.md, data-model.md, contracts/

## Execution Flow (main)
```
1. Load plan.md from feature directory
   → Extract: PHP 8.4, Symfony Console 7.x, Symfony Process 7.x, Composer 2.x
   → Structure: 纯 CLI Composer Package
2. Load design documents:
   → data-model.md: 数据结构定义（无实体模型）
   → contracts/: CodingApiContractTest.php, GitHubApiContractTest.php
   → research.md: Symfony Console, API 集成, 配置管理
3. Generate tasks by category:
   → Setup: Composer 项目, 依赖管理, 代码质量工具
   → Tests: 契约测试, 集成测试, 单元测试
   → Core: 服务类, 命令类, 配置管理
   → Integration: API 客户端, Git 操作, 交互式界面
   → Polish: 文档, 示例, 性能优化
4. Apply task rules:
   → 不同文件 = 标记 [P] 并行执行
   → 相同文件 = 顺序执行（无 [P]）
   → 测试优先于实现（TDD）
5. 任务编号: T001, T002...
6. 生成依赖关系图
7. 创建并行执行示例
8. 验证任务完整性
9. 返回: SUCCESS（任务准备就绪）
```

## Format: `[ID] [P?] Description`
- **[P]**: 可以并行运行（不同文件，无依赖关系）
- 描述中包含确切的文件路径

## Path Conventions
- **Composer 包**: 在仓库根目录的 `src/`, `tests/`
- 路径基于 plan.md 中的纯 CLI Composer Package 结构

## Phase 3.1: 项目初始化
- [ ] T001 创建 Composer 包结构，包含 src/, tests/, bin/, config/ 目录
- [ ] T002 初始化 PHP 8.4 项目，配置 composer.json 和依赖项
- [ ] T003 [P] 配置 PHPStan Level 8, PHP CS Fixer, PHPUnit 10+

## Phase 3.2: 测试优先（TDD）⚠️ 必须在 3.3 之前完成
**关键：这些测试必须先编写并失败，然后才能进行任何实现**
- [ ] T004 [P] 契约测试 CodingApiContractTest 在 tests/Contract/CodingApiContractTest.php
- [ ] T005 [P] 契约测试 GitHubApiContractTest 在 tests/Contract/GitHubApiContractTest.php
- [ ] T006 [P] 集成测试端到端迁移流程在 tests/Integration/EndToEndMigrationTest.php
- [ ] T007 [P] 集成测试错误处理在 tests/Integration/ErrorHandlingTest.php
- [ ] T008 [P] 集成测试并发迁移在 tests/Integration/ConcurrentMigrationTest.php
- [ ] T009 [P] 集成测试配置验证在 tests/Integration/ConfigValidationTest.php

## Phase 3.3: 核心实现（仅在测试失败后）
- [ ] T010 [P] ConfigService 配置管理在 src/Services/ConfigService.php
- [ ] T011 [P] CodingApiService API 客户端在 src/Services/CodingApiService.php
- [ ] T012 [P] GitHubApiService API 客户端在 src/Services/GitHubApiService.php
- [ ] T013 [P] GitService Git 操作在 src/Services/GitService.php
- [ ] T014 [P] MigrationService 迁移逻辑在 src/Services/MigrationService.php
- [ ] T015 [P] LogService 日志记录在 src/Services/LogService.php
- [ ] T016 [P] InteractiveSelectionService 交互式选择在 src/Services/InteractiveSelectionService.php
- [ ] T017 [P] ProgressDisplayService 进度显示在 src/Services/ProgressDisplayService.php

## Phase 3.4: Symfony Console 命令
- [ ] T018 [P] MigrateCommand 迁移命令在 src/Commands/MigrateCommand.php
- [ ] T019 [P] ConfigCommand 配置命令在 src/Commands/ConfigCommand.php
- [ ] T020 [P] StatusCommand 状态命令在 src/Commands/StatusCommand.php
- [ ] T021 命令参数验证和帮助文档

## Phase 3.5: 异常处理和接口
- [ ] T022 [P] MigrationException 基础异常在 src/Exceptions/MigrationException.php
- [ ] T023 [P] ApiException API 异常在 src/Exceptions/ApiException.php
- [ ] T024 [P] GitException Git 异常在 src/Exceptions/GitException.php
- [ ] T025 [P] ConfigException 配置异常在 src/Exceptions/ConfigException.php
- [ ] T026 [P] ApiClientInterface 接口在 src/Contracts/ApiClientInterface.php
- [ ] T027 [P] GitServiceInterface 接口在 src/Contracts/GitServiceInterface.php
- [ ] T028 [P] MigrationServiceInterface 接口在 src/Contracts/MigrationServiceInterface.php

## Phase 3.6: 工具类和辅助函数
- [ ] T029 [P] PathHelper 路径处理在 src/Utils/PathHelper.php
- [ ] T030 [P] StringHelper 字符串处理在 src/Utils/StringHelper.php
- [ ] T031 [P] ArrayHelper 数组处理在 src/Utils/ArrayHelper.php
- [ ] T032 [P] FileHelper 文件处理在 src/Utils/FileHelper.php

## Phase 3.7: 单元测试
- [ ] T033 [P] ConfigService 单元测试在 tests/Unit/Services/ConfigServiceTest.php
- [ ] T034 [P] CodingApiService 单元测试在 tests/Unit/Services/CodingApiServiceTest.php
- [ ] T035 [P] GitHubApiService 单元测试在 tests/Unit/Services/GitHubApiServiceTest.php
- [ ] T036 [P] GitService 单元测试在 tests/Unit/Services/GitServiceTest.php
- [ ] T037 [P] MigrationService 单元测试在 tests/Unit/Services/MigrationServiceTest.php
- [ ] T038 [P] 工具类单元测试在 tests/Unit/Utils/

## Phase 3.8: 主入口和配置
- [ ] T039 创建主命令行入口 bin/migration.php
- [ ] T040 配置服务容器和依赖注入
- [ ] T041 环境变量加载和验证
- [ ] T042 日志配置和轮转设置

## Phase 3.9: 文档和示例
- [ ] T043 [P] 完善 README.md 文档
- [ ] T044 [P] 创建使用示例和最佳实践
- [ ] T045 [P] API 文档生成
- [ ] T046 [P] 故障排除指南

## Phase 3.10: 性能优化和最终测试
- [ ] T047 性能测试和内存使用优化
- [ ] T048 并发控制优化
- [ ] T049 错误重试机制优化
- [ ] T050 最终集成测试和验证

## 依赖关系
- 测试（T004-T009）必须在实现（T010-T021）之前
- T010 阻塞 T011, T012, T013, T014
- T011, T012 阻塞 T014
- T013 阻塞 T014
- 实现必须在单元测试（T033-T038）之前
- 所有核心功能必须在文档（T043-T046）之前

## 并行执行示例
```
# 启动 T004-T009 一起：
Task: "契约测试 CodingApiContractTest 在 tests/Contract/CodingApiContractTest.php"
Task: "契约测试 GitHubApiContractTest 在 tests/Contract/GitHubApiContractTest.php"
Task: "集成测试端到端迁移流程在 tests/Integration/EndToEndMigrationTest.php"
Task: "集成测试错误处理在 tests/Integration/ErrorHandlingTest.php"
Task: "集成测试并发迁移在 tests/Integration/ConcurrentMigrationTest.php"
Task: "集成测试配置验证在 tests/Integration/ConfigValidationTest.php"

# 启动 T010-T017 一起：
Task: "ConfigService 配置管理在 src/Services/ConfigService.php"
Task: "CodingApiService API 客户端在 src/Services/CodingApiService.php"
Task: "GitHubApiService API 客户端在 src/Services/GitHubApiService.php"
Task: "GitService Git 操作在 src/Services/GitService.php"
Task: "MigrationService 迁移逻辑在 src/Services/MigrationService.php"
Task: "LogService 日志记录在 src/Services/LogService.php"
Task: "InteractiveSelectionService 交互式选择在 src/Services/InteractiveSelectionService.php"
Task: "ProgressDisplayService 进度显示在 src/Services/ProgressDisplayService.php"
```

## 任务执行顺序

### 阶段 1: 项目初始化（T001-T003）
1. 创建项目结构
2. 配置 Composer 和依赖
3. 设置代码质量工具

### 阶段 2: 测试优先（T004-T009）
1. 编写契约测试（必须失败）
2. 编写集成测试（必须失败）
3. 验证测试失败

### 阶段 3: 核心实现（T010-T021）
1. 实现服务类
2. 实现命令类
3. 实现异常和接口

### 阶段 4: 工具和测试（T022-T038）
1. 实现工具类
2. 编写单元测试
3. 验证所有测试通过

### 阶段 5: 集成和文档（T039-T050）
1. 创建主入口
2. 完善文档
3. 性能优化

## 验证清单
- [x] 所有契约都有对应的测试
- [x] 所有服务都有实现任务
- [x] 所有测试都在实现之前
- [x] 并行任务真正独立
- [x] 每个任务指定确切的文件路径
- [x] 没有任务修改与其他 [P] 任务相同的文件

## 技术栈确认
- **语言**: PHP 8.4+
- **框架**: Symfony Console 7.x, Symfony Process 7.x
- **包管理**: Composer 2.x
- **测试**: PHPUnit 10+
- **代码质量**: PHPStan Level 8, PHP CS Fixer
- **项目类型**: 纯 CLI Composer 包

## 注意事项
- [P] 任务 = 不同文件，无依赖关系
- 实现前验证测试失败
- 每个任务后提交
- 避免：模糊任务，同文件冲突
- 遵循 TDD 原则：红-绿-重构循环

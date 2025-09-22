# Migration Project Constitution
<!-- 
Sync Impact Report:
Version change: 0.0.0 → 1.0.0
Modified principles: N/A (initial creation)
Added sections: 技术栈要求, 开发工作流
Removed sections: N/A
Templates requiring updates:
✅ updated /Users/quan/wwwroot/coding/migration/.specify/templates/plan-template.md
✅ updated /Users/quan/wwwroot/coding/migration/.specify/templates/tasks-template.md
Follow-up TODOs: None
-->
<!-- PHP 8.4 + Composer 项目宪法 -->

## Core Principles

### I. Composer-First Architecture
所有功能必须作为独立的 Composer 包开发；包必须自包含、独立可测试、有完整文档；每个包必须有明确的单一职责，禁止仅用于组织目的的包。

### II. PHP 8.4 特性优先
充分利用 PHP 8.4 的新特性（类型系统、属性、枚举等）；代码必须通过 PHP 8.4 严格类型检查；使用现代 PHP 语法和最佳实践。

### III. 测试驱动开发（不可协商）
TDD 强制要求：先写测试 → 用户确认 → 测试失败 → 然后实现；严格遵循红-绿-重构循环；所有代码必须有对应的测试覆盖。

### IV. 集成测试重点
需要集成测试的重点领域：新包契约测试、契约变更、服务间通信、共享数据模式、数据库迁移、API 端点。

### V. 可观测性与版本控制
结构化日志记录必须包含请求 ID 和上下文；使用语义化版本控制（MAJOR.MINOR.PATCH）；所有变更必须向后兼容或提供迁移路径。

### VI. 简单性原则
从简单开始，遵循 YAGNI 原则；复杂性必须有明确理由；优先选择标准库和成熟包而非自定义实现。

## 技术栈要求

**PHP 版本**: 8.4+（使用最新特性）  
**包管理**: Composer 2.x  
**测试框架**: PHPUnit 10+  
**代码质量**: PHPStan Level 8, PHP CS Fixer  
**文档**: PHPDoc 标准，自动生成 API 文档  

## 开发工作流

**代码审查**: 所有 PR 必须验证宪法合规性；复杂性必须被证明合理  
**质量门禁**: 测试覆盖率 >90%，静态分析无错误，代码风格一致  
**部署流程**: 自动化测试 → 代码审查 → 预生产验证 → 生产部署  

## Governance

宪法超越所有其他实践；修正需要文档、批准和迁移计划；所有 PR/审查必须验证合规性；复杂性必须被证明合理；使用 `.specify/memory/constitution.md` 作为运行时开发指导。

**Version**: 1.0.0 | **Ratified**: 2025-01-27 | **Last Amended**: 2025-01-27
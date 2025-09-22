
# Implementation Plan: Coding API 修复

**Branch**: `001-coding-to-github-migration` | **Date**: 2025-01-27 | **Spec**: [link]
**Input**: Feature specification from `/specs/001-coding-to-github-migration/spec.md`

## Execution Flow (/plan command scope)
```
1. Load feature spec from Input path
   → If not found: ERROR "No feature spec at {path}"
2. Fill Technical Context (scan for NEEDS CLARIFICATION)
   → Detect Project Type from context (web=frontend+backend, mobile=app+api)
   → Set Structure Decision based on project type
3. Fill the Constitution Check section based on the content of the constitution document.
4. Evaluate Constitution Check section below
   → If violations exist: Document in Complexity Tracking
   → If no justification possible: ERROR "Simplify approach first"
   → Update Progress Tracking: Initial Constitution Check
5. Execute Phase 0 → research.md
   → If NEEDS CLARIFICATION remain: ERROR "Resolve unknowns"
6. Execute Phase 1 → contracts, data-model.md, quickstart.md, agent-specific template file (e.g., `CLAUDE.md` for Claude Code, `.github/copilot-instructions.md` for GitHub Copilot, `GEMINI.md` for Gemini CLI, `QWEN.md` for Qwen Code or `AGENTS.md` for opencode).
7. Re-evaluate Constitution Check section
   → If new violations: Refactor design, return to Phase 1
   → Update Progress Tracking: Post-Design Constitution Check
8. Plan Phase 2 → Describe task generation approach (DO NOT create tasks.md)
9. STOP - Ready for /tasks command
```

**IMPORTANT**: The /plan command STOPS at step 7. Phases 2-4 are executed by other commands:
- Phase 2: /tasks command creates tasks.md
- Phase 3-4: Implementation execution (manual or via tools)

## Summary
修复 Coding API 调用问题，确保能够正确获取项目列表和仓库信息。当前问题：Syntax error for "https://e.coding.net/projects"，需要修正 API 端点、认证方式和请求格式。

## Technical Context
**Language/Version**: PHP 8.4+  
**Primary Dependencies**: Symfony Console 7.x, Symfony Process 7.x, Symfony HttpClient 7.x, Composer 2.x  
**Storage**: 临时文件系统（Git 仓库克隆）  
**Testing**: PHPUnit 10+, PHPStan Level 8, PHP CS Fixer  
**Target Platform**: Linux/macOS/Windows (需要 Git 环境)  
**Project Type**: single (Composer 包)  
**Performance Goals**: 单个仓库迁移 < 2 分钟，支持并发迁移（默认3个）  
**Constraints**: 内存使用 < 512MB，网络错误自动重试（最多3次）  
**Scale/Scope**: 支持批量迁移多个项目，默认并发数3

## Constitution Check
*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Composer-First**: ✅ 项目已配置为 Composer 包，有完整的 composer.json 和 autoload 配置
**PHP 8.4 特性**: ✅ 使用严格类型声明，现代 PHP 语法，match 表达式等
**TDD 合规**: ✅ 有完整的测试结构，PHPUnit 配置，需要确保先写测试
**集成测试**: ✅ 已识别关键领域：API 契约测试、服务间通信、错误处理
**可观测性**: ✅ 有结构化日志记录，使用 Monolog，包含上下文信息
**简单性**: ✅ 当前问题明确，修复方案直接，无需复杂重构

## Project Structure

### Documentation (this feature)
```
specs/[###-feature]/
├── plan.md              # This file (/plan command output)
├── research.md          # Phase 0 output (/plan command)
├── data-model.md        # Phase 1 output (/plan command)
├── quickstart.md        # Phase 1 output (/plan command)
├── contracts/           # Phase 1 output (/plan command)
└── tasks.md             # Phase 2 output (/tasks command - NOT created by /plan)
```

### Source Code (repository root)
```
# Option 1: Composer Package (DEFAULT for PHP)
src/
├── Models/
├── Services/
├── Commands/
├── Contracts/
└── Exceptions/

tests/
├── Unit/
├── Integration/
├── Contract/
└── Fixtures/

composer.json
phpunit.xml.dist
phpstan.neon
.php-cs-fixer.php

# Option 2: Multi-package monorepo
packages/
├── package-a/
│   ├── src/
│   ├── tests/
│   └── composer.json
├── package-b/
│   ├── src/
│   ├── tests/
│   └── composer.json
└── migration-tool/
    ├── src/
    ├── tests/
    └── composer.json

# Option 3: Web application (when "frontend" + "backend" detected)
backend/
├── src/
│   ├── Models/
│   ├── Services/
│   ├── Controllers/
│   └── Middleware/
├── tests/
└── composer.json

frontend/
└── [JavaScript/TypeScript structure]
```

**Structure Decision**: [DEFAULT to Option 1 for PHP packages unless Technical Context indicates multi-package or web app]

## Phase 0: Outline & Research
1. **Extract unknowns from Technical Context** above:
   - For each NEEDS CLARIFICATION → research task
   - For each dependency → best practices task
   - For each integration → patterns task

2. **Generate and dispatch research agents**:
   ```
   For each unknown in Technical Context:
     Task: "Research {unknown} for {feature context}"
   For each technology choice:
     Task: "Find best practices for {tech} in {domain}"
   ```

3. **Consolidate findings** in `research.md` using format:
   - Decision: [what was chosen]
   - Rationale: [why chosen]
   - Alternatives considered: [what else evaluated]

**Output**: research.md with all NEEDS CLARIFICATION resolved

## Phase 1: Design & Contracts
*Prerequisites: research.md complete*

1. **Extract entities from feature spec** → `data-model.md`:
   - Entity name, fields, relationships
   - Validation rules from requirements
   - State transitions if applicable

2. **Generate API contracts** from functional requirements:
   - For each user action → endpoint
   - Use standard REST/GraphQL patterns
   - Output OpenAPI/GraphQL schema to `/contracts/`

3. **Generate contract tests** from contracts:
   - One test file per endpoint
   - Assert request/response schemas
   - Tests must fail (no implementation yet)

4. **Extract test scenarios** from user stories:
   - Each story → integration test scenario
   - Quickstart test = story validation steps

5. **Update agent file incrementally** (O(1) operation):
   - Run `.specify/scripts/bash/update-agent-context.sh cursor`
     **IMPORTANT**: Execute it exactly as specified above. Do not add or remove any arguments.
   - If exists: Add only NEW tech from current plan
   - Preserve manual additions between markers
   - Update recent changes (keep last 3)
   - Keep under 150 lines for token efficiency
   - Output to repository root

**Output**: data-model.md, /contracts/*, failing tests, quickstart.md, agent-specific file

## Phase 2: Task Planning Approach
*This section describes what the /tasks command will do - DO NOT execute during /plan*

**Task Generation Strategy**:
- Load `.specify/templates/tasks-template.md` as base
- Generate tasks from Phase 1 design docs (contracts, data model, quickstart)
- 修复 Coding API 端点路径：/projects → /api/user/projects
- 修复仓库端点路径：/projects/{id}/repositories → /api/user/projects/{id}/repositories
- 验证认证头格式和响应处理
- 添加契约测试验证 API 调用格式
- 添加集成测试验证端到端功能

**Ordering Strategy**:
- TDD order: 先写契约测试，再修复实现
- 依赖顺序: API 修复 → 测试验证 → 集成测试
- 标记 [P] 用于并行执行（独立文件）

**具体任务类型**:
1. **API 修复任务**: 修正端点路径和请求格式
2. **契约测试任务**: 验证 API 调用符合规范
3. **集成测试任务**: 验证端到端功能
4. **错误处理任务**: 改进错误处理和日志记录
5. **文档更新任务**: 更新相关文档

**Estimated Output**: 15-20 个编号、有序的任务在 tasks.md 中

**IMPORTANT**: This phase is executed by the /tasks command, NOT by /plan

## Phase 3+: Future Implementation
*These phases are beyond the scope of the /plan command*

**Phase 3**: Task execution (/tasks command creates tasks.md)  
**Phase 4**: Implementation (execute tasks.md following constitutional principles)  
**Phase 5**: Validation (run tests, execute quickstart.md, performance validation)

## Complexity Tracking
*Fill ONLY if Constitution Check has violations that must be justified*

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |


## Progress Tracking
*This checklist is updated during execution flow*

**Phase Status**:
- [x] Phase 0: Research complete (/plan command)
- [x] Phase 1: Design complete (/plan command)
- [ ] Phase 2: Task planning complete (/plan command - describe approach only)
- [ ] Phase 3: Tasks generated (/tasks command)
- [ ] Phase 4: Implementation complete
- [ ] Phase 5: Validation passed

**Gate Status**:
- [x] Initial Constitution Check: PASS
- [x] Post-Design Constitution Check: PASS
- [x] All NEEDS CLARIFICATION resolved
- [x] Complexity deviations documented

---
*Based on Constitution v1.0.0 - See `/memory/constitution.md`*

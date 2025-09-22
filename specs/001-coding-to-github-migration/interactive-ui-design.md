# 交互式界面设计文档

## 概述

本文档详细描述了 Coding 到 GitHub 代码仓库迁移工具的交互式用户界面设计，包括界面布局、用户交互流程和实现细节。

## 界面设计原则

### 1. 用户友好
- 清晰的视觉层次
- 直观的操作流程
- 丰富的视觉反馈
- 错误提示明确

### 2. 信息丰富
- 显示仓库的详细信息
- 实时进度反馈
- 清晰的状态指示
- 完整的操作结果

### 3. 操作便捷
- 支持键盘快捷键
- 批量操作支持
- 快速预览功能
- 一键确认执行

## 界面组件设计

### 1. 仓库列表表格

#### 表格结构
```
┌─────┬─────────────┬─────────────────┬─────────────────────────┬──────────┬─────────────────┐
│ 选择 │ 项目名称    │ 仓库名称        │ 描述                    │ 大小     │ 最后更新        │
├─────┼─────────────┼─────────────────┼─────────────────────────┼──────────┼─────────────────┤
│ ✓   │ my-project  │ main-repo       │ 主要代码仓库            │ 2.5 MB   │ 2025-01-27      │
│ ✓   │ my-project  │ api-service     │ API 服务代码            │ 1.2 MB   │ 2025-01-26      │
│ ✓   │ test-proj   │ frontend        │ 前端代码                │ 3.1 MB   │ 2025-01-25      │
│ ✓   │ test-proj   │ backend         │ 后端代码                │ 4.2 MB   │ 2025-01-24      │
│ ✓   │ utils       │ common-lib      │ 通用工具库              │ 0.8 MB   │ 2025-01-23      │
└─────┴─────────────┴─────────────────┴─────────────────────────┴──────────┴─────────────────┘
```

#### 列定义
- **选择**: 复选框状态（✓ 选中，☐ 未选中）
- **项目名称**: Coding 项目名称
- **仓库名称**: Coding 仓库名称
- **描述**: 仓库描述（截断显示，最大 30 字符）
- **大小**: 仓库大小（格式化显示，如 2.5 MB）
- **最后更新**: 最后更新时间（格式化显示，如 2025-01-27）

#### 样式规范
- 表头使用粗体
- 选中行使用绿色背景
- 未选中行使用默认背景
- 当前焦点行使用蓝色边框

### 2. 操作提示区域

#### 提示内容
```
操作选项:
  [空格] 切换选择  [a] 全选/反选  [Enter] 确认选择  [q] 退出
```

#### 样式规范
- 使用灰色文字
- 居中对齐
- 与表格保持适当间距

### 3. 迁移计划预览

#### 预览格式
```
📋 迁移计划预览:
  my-project/main-repo     → ant-cool/my-project-main-repo
  my-project/api-service   → ant-cool/my-project-api-service
  test-proj/frontend       → ant-cool/test-proj-frontend
  test-proj/backend        → ant-cool/test-proj-backend
  utils/common-lib         → ant-cool/utils-common-lib
```

#### 样式规范
- 使用表格图标 📋
- 源仓库名称左对齐
- 箭头符号 → 居中对齐
- 目标仓库名称右对齐
- 使用等宽字体

### 4. 确认对话框

#### 确认提示
```
确认开始迁移? [y/N]: 
```

#### 样式规范
- 使用黄色文字突出显示
- 支持 y/Y/yes 和 n/N/no 输入
- 默认值为 N（安全选择）

### 5. 进度显示

#### 进度条格式
```
🚀 开始迁移...
[████████████████████████████████] 100% (5/5)
```

#### 实时状态
```
正在迁移: my-project/main-repo → ant-cool/my-project-main-repo
状态: 克隆仓库... [████████████████████████████████] 50%
```

#### 样式规范
- 使用进度条字符 █
- 显示百分比和当前/总数
- 实时更新状态信息
- 使用不同颜色表示不同状态

### 6. 结果展示

#### 成功结果
```
✅ 迁移完成!
成功: 5 个仓库
失败: 0 个仓库
跳过: 0 个仓库

详细结果:
  ✓ my-project/main-repo     → ant-cool/my-project-main-repo
  ✓ my-project/api-service   → ant-cool/my-project-api-service
  ✓ test-proj/frontend       → ant-cool/test-proj-frontend
  ✓ test-proj/backend        → ant-cool/test-proj-backend
  ✓ utils/common-lib         → ant-cool/utils-common-lib
```

#### 失败结果
```
❌ 迁移完成（部分失败）!
成功: 3 个仓库
失败: 2 个仓库
跳过: 0 个仓库

详细结果:
  ✓ my-project/main-repo     → ant-cool/my-project-main-repo
  ✓ my-project/api-service   → ant-cool/my-project-api-service
  ✓ test-proj/frontend       → ant-cool/test-proj-frontend
  ✗ test-proj/backend        → 错误: 仓库已存在
  ✗ utils/common-lib         → 错误: 网络超时
```

## 交互流程设计

### 1. 初始化阶段
```
🔍 正在获取 Coding 仓库列表...
📋 发现 5 个仓库，准备迁移到 GitHub 组织: ant-cool
```

### 2. 选择阶段
- 显示仓库列表表格
- 默认全选所有仓库
- 支持键盘操作：
  - `空格`: 切换当前行选择状态
  - `a`: 全选/反选所有仓库
  - `↑/↓`: 上下移动焦点
  - `Enter`: 确认选择
  - `q`: 退出程序

### 3. 预览阶段
- 显示迁移计划预览
- 显示操作统计信息
- 等待用户确认

### 4. 执行阶段
- 显示进度条
- 实时更新状态
- 显示当前操作

### 5. 完成阶段
- 显示最终结果
- 统计成功/失败数量
- 显示详细结果列表

## 技术实现

### 1. 表格组件
使用 Symfony Console 的 Table 组件：
```php
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;

$table = new Table($output);
$table->setHeaders(['选择', '项目名称', '仓库名称', '描述', '大小', '最后更新']);
$table->setRows($repositoryData);
$table->render();
```

### 2. 进度条组件
使用 Symfony Console 的 ProgressBar 组件：
```php
use Symfony\Component\Console\Helper\ProgressBar;

$progressBar = new ProgressBar($output, $totalRepositories);
$progressBar->setFormat('verbose');
$progressBar->start();
```

### 3. 用户输入组件
使用 Symfony Console 的 Question 组件：
```php
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;

$question = new ConfirmationQuestion('确认开始迁移? [y/N]: ', false);
$confirmed = $helper->ask($input, $output, $question);
```

### 4. 颜色输出
使用 Symfony Console 的颜色支持：
```php
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

$output->getFormatter()->setStyle('success', new OutputFormatterStyle('green'));
$output->getFormatter()->setStyle('error', new OutputFormatterStyle('red'));
$output->getFormatter()->setStyle('warning', new OutputFormatterStyle('yellow'));
```

## 响应式设计

### 1. 屏幕适配
- 自动调整表格宽度
- 支持不同终端尺寸
- 长文本自动截断

### 2. 性能优化
- 分页显示大量仓库
- 延迟加载仓库详情
- 异步获取仓库信息

### 3. 错误处理
- 网络错误重试提示
- 权限错误明确说明
- 配置错误引导修复

## 可访问性

### 1. 键盘导航
- 支持 Tab 键切换焦点
- 支持方向键导航
- 支持快捷键操作

### 2. 屏幕阅读器
- 提供文本描述
- 使用语义化标记
- 支持语音输出

### 3. 颜色对比
- 确保足够的颜色对比度
- 支持高对比度模式
- 不依赖颜色传达信息

## 国际化支持

### 1. 多语言支持
- 支持中英文界面
- 可配置语言设置
- 本地化日期时间格式

### 2. 字符编码
- 支持 UTF-8 编码
- 正确处理中文字符
- 兼容不同终端环境

## 测试策略

### 1. 单元测试
- 测试表格渲染
- 测试进度条更新
- 测试用户输入处理

### 2. 集成测试
- 测试完整交互流程
- 测试错误处理
- 测试不同屏幕尺寸

### 3. 用户测试
- 收集用户反馈
- 优化交互体验
- 改进界面设计

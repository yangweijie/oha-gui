# iFlow 上下文文档 - OHA GUI Tool

## 项目概述

OHA GUI Tool 是一个跨平台的图形用户界面应用程序，用于通过 [oha](https://github.com/hatoo/oha) 命令行工具进行 HTTP 负载测试。该应用使用 PHP 和 kingbes/libui 库构建。

### 主要特性

- **用户友好的 GUI** 用于配置 HTTP 负载测试
- **保存和加载** 测试配置，支持持久化存储
- **实时测试执行** 和结果展示
- **跨平台支持** (Windows, macOS, Linux)
- **多种 HTTP 方法** 支持 (GET, POST, PUT, DELETE, PATCH)
- **自定义请求头和请求体** 配置
- **高级测试结果解析** 和格式化展示
- **全面的错误处理** 和用户指导
- **资源清理** 和正确的应用程序生命周期管理

## 技术栈

- **后端语言**: PHP 8.0+
- **GUI 框架**: kingbes/libui
- **依赖管理**: Composer
- **外部工具**: oha (需单独下载并放置在项目 `bin/` 目录)

## 项目结构

```
src/
├── App/           # 主应用程序类
├── Core/          # 核心业务逻辑 (命令构建、执行、解析)
├── GUI/           # 用户界面组件
├── Models/        # 数据模型
└── Utils/         # 工具类

tests/             # 单元测试
vendor/            # Composer 依赖
main.php           # 应用程序入口点
```

## 构建和运行

### 环境要求

- **PHP 8.0+** 需要以下扩展:
  - `json` 扩展 (通常已包含)
  - `pcntl` 扩展 (Unix-like 系统的信号处理，可选)
- **Composer** - PHP 依赖管理器
- **kingbes/libui** 包 (通过 Composer 安装)
- **oha** 二进制文件 - HTTP 负载测试工具

### 安装步骤

1. **克隆或下载仓库**
   ```bash
   git clone <repository-url>
   cd oha-gui-tool
   ```

2. **安装 PHP 依赖**
   ```bash
   composer install
   ```

3. **下载并安装 oha 二进制文件**
   - 从 https://github.com/hatoo/oha/releases 下载适用于你平台的二进制文件
   - 将二进制文件放置在项目 `bin/` 目录中
     - Windows: `bin/oha.exe`
     - macOS/Linux: `bin/oha` (确保有执行权限 `chmod +x bin/oha`)

### 运行应用

#### Windows
```cmd
run.bat
```
或
```cmd
php main.php
```

#### macOS/Linux
```bash
./run.sh
```
或
```bash
php main.php
```

## 开发规范

### 代码结构

项目遵循模块化架构，清晰分离关注点：

- **App**: 主应用程序类和生命周期管理
- **Core**: 核心业务逻辑 (命令构建、执行、解析、验证)
- **GUI**: 用户界面组件 (主窗口、表单、结果显示)
- **Models**: 数据模型 (测试配置、测试结果)
- **Utils**: 工具类 (跨平台兼容性、文件管理、用户指导)

### 编码规范

- 遵循 PSR-4 自动加载标准
- 使用严格的类型声明 (`declare(strict_types=1);`)
- 添加全面的错误处理和用户指导
- 确保跨平台兼容性

### 测试

项目包含全面的测试套件：

- **单元测试** 覆盖所有核心组件
- **集成测试** 验证组件交互
- **跨平台兼容性测试**
- **错误处理验证**
- **资源清理验证**

运行测试:
```bash
php run_tests.php
```

## 配置管理

应用将配置存储在平台特定的目录中：

- **Windows**: `%APPDATA%\OhaGui\`
- **macOS**: `~/Library/Application Support/OhaGui/`
- **Linux**: `~/.config/ohagui/`

## 错误处理和用户指导

应用包含全面的错误处理和用户友好的指导：

- **自动错误检测** 常见问题
- **平台特定的安装说明**
- **详细的故障排除指南**
- **上下文相关的帮助和建议**
- **当 oha 二进制文件不可用时的优雅降级**

## 贡献指南

1. Fork 仓库
2. 创建功能分支
3. 进行修改
4. 如适用，添加测试
5. 运行测试套件: `php test_final_integration.php`
6. 提交 Pull Request

开发指南:

- 遵循 PSR-4 自动加载标准
- 添加全面的错误处理和用户指导
- 确保跨平台兼容性
- 为新功能编写单元测试
- 必要时更新文档
# OHA GUI Tool - 项目上下文说明

## 项目概述

OHA GUI Tool 是一个跨平台的图形用户界面应用程序，用于执行 HTTP 负载测试。它使用 PHP 和 kingbes/libui 库构建，并利用 [oha](https://github.com/hatoo/oha) 命令行工具作为后端执行引擎。

### 主要特性

- **用户友好的图形界面**：用于配置 HTTP 负载测试
- **保存和加载测试配置**：支持持久化存储
- **实时测试执行和结果展示**
- **跨平台支持**：Windows、macOS、Linux
- **支持多种 HTTP 方法**：GET, POST, PUT, DELETE, PATCH
- **自定义请求头和请求体配置**
- **高级测试结果解析和格式化展示**
- **全面的错误处理和用户指导**
- **资源清理和应用生命周期管理**

## 技术架构

### 核心技术栈

- **编程语言**：PHP 8.0+
- **GUI 框架**：kingbes/libui (基于 libui 的 PHP 绑定)
- **后端工具**：oha (命令行 HTTP 负载测试工具)
- **依赖管理**：Composer

### 项目结构

```
src/
├── App/           # 主应用类和生命周期管理
├── Core/          # 核心业务逻辑（命令构建、执行、结果解析）
├── GUI/           # 用户界面组件
├── Models/        # 数据模型
└── Utils/         # 工具类

tests/             # 单元测试
vendor/            # Composer 依赖
main.php           # 应用入口点
```

### 核心组件

- **OhaGuiApp** (`src/App/OhaGuiApp.php`)：主应用类，处理 libui 初始化、应用生命周期和主事件循环
- **MainWindow** (`src/GUI/MainWindow.php`)：主窗口类，创建和管理主应用窗口及所有 UI 组件
- **TestExecutor** (`src/Core/TestExecutor.php`)：负责异步执行 oha 命令，实时捕获输出并管理进程
- **OhaCommandBuilder** (`src/Core/OhaCommandBuilder.php`)：根据测试配置构建 oha 命令
- **ConfigurationManager** (`src/Core/ConfigurationManager.php`)：管理测试配置的保存、加载、列表和删除
- **ResultParser** (`src/Core/ResultParser.php`)：解析 oha 输出结果

## 开发和运行

### 环境要求

- **PHP 8.0+** 及其扩展：
  - `json` 扩展（通常已包含）
  - `pcntl` 扩展（用于 Unix 系统上的信号处理，可选）
  - `ffi` 扩展（用于与 libui 交互）
- **Composer**：PHP 依赖管理器
- **kingbes/libui**：通过 Composer 安装的 GUI 库
- **oha 二进制文件**：放置在项目 `bin/` 目录中

### 安装步骤

1. 克隆或下载此仓库
2. 运行 `composer install` 安装依赖
3. 下载 [oha](https://github.com/hatoo/oha/releases) 二进制文件
4. 将二进制文件放置在项目 `bin/` 目录中：
   - Windows: `bin/oha.exe`
   - macOS/Linux: `bin/oha` (需要执行权限)

### 运行应用

- **Windows**：`run.bat` 或 `php main.php`
- **macOS/Linux**：`./run.sh` 或 `php main.php`

### 运行测试

```bash
php run_tests.php
```

## 配置管理

应用将配置存储在平台特定的目录中：
- **Windows**：`%APPDATA%\OhaGui\`
- **macOS**：`~/Library/Application Support/OhaGui/`
- **Linux**：`~/.config/ohagui/`

## 开发规范

- 遵循 PSR-4 自动加载标准
- 使用严格的类型声明 (`strict_types=1`)
- 添加全面的错误处理和用户指导
- 确保跨平台兼容性
- 为新功能编写单元测试
- 保持代码模块化和关注点分离
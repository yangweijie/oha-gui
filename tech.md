完美的想法！oha确实是个很好的选择——Rust写的跨平台压测工具，支持漂亮的TUI界面。去除Swoole并改用oha比我想象的要简单。

## 核心改造步骤

### 1. 移除Swoole依赖
删除[SwooleBenchmarkHelper.php](code/api/app/common/helper/SwooleBenchmarkHelper.php#L1-L245)中的所有Swoole相关代码，这个文件基本可以完全重写。

### 2. 创建OhaBenchmarkHelper替换类
在同样位置创建新的压测助手类：

```php
<?php
namespace app\common\helper;

class OhaBenchmarkHelper 
{
    public static function instance() {
        return new static();
    }
    
    public function testUrl($host, $port, $path, $method, $body, $headers, $concurrent_count, $duration, $timeout, $scheme) {
        $url = "{$scheme}://{$host}:{$port}{$path}";
        $command = $this->buildOhaCommand($url, $method, $body, $headers, $concurrent_count, $duration, $timeout);
        
        return $this->executeCommand($command);
    }
    
    private function buildOhaCommand($url, $method, $body, $headers, $concurrent_count, $duration, $timeout) {
        $command = ['oha'];
        $command[] = "-c {$concurrent_count}";
        $command[] = "-z {$duration}s";
        $command[] = "-t {$timeout}s";
        $command[] = "-m {$method}";
        $command[] = "--no-tui"; // 禁用TUI，获取纯文本输出
        
        // 添加请求头
        foreach ($headers as $key => $value) {
            $command[] = "-H \"{$key}: {$value}\"";
        }
        
        // 添加请求体
        if (!empty($body)) {
            $command[] = "-d '{$body}'";
        }
        
        $command[] = "\"{$url}\"";
        return implode(' ', $command);
    }
}
```

### 3. 修改BenchmarkJob.php
在[BenchmarkJob.php](code/api/app/job/BenchmarkJob.php#L35-L70)中替换压测逻辑：

```php
// 将这部分
switch ($requests['driver']) {
    case 'co':
        // Swoole协程代码
        break;
    case 'wrk':
        // wrk代码
        break;
}

// 改为
switch ($requests['driver']) {
    case 'oha':
        $out = OhaBenchmarkHelper::instance()->testUrl(
            $requests['host'],
            $requests['port'], 
            $path,
            $requests['type'],
            $param2['value'] ?? '',
            $param3,
            $requests['connect_count'],
            $requests['duration'],
            $requests['timeout'],
            $requests['scheme']
        );
        break;
}
```

### 4. 更新结果解析
oha的输出格式和wrk不同，需要调整正则表达式：

```php
case 'oha':
    // oha输出示例: "Requests/sec: 299.0098"
    preg_match_all('/Success rate:\s*(\d+\.\d+)%|Total:\s*(\d+)\s+requests|Requests\/sec:\s*(\d+\.\d+)/im', $out, $match);
    $update_data = [
        'content' => $out,
        'request_count' => join($match[2]) ?: 0,
        'fail_count' => 0, // 需要从Success rate计算
        'request_rate' => join($match[3]) ?: 0,
    ];
    break;
```

### 5. 去除composer中的swoole依赖
[composer.json](code/api/composer.json#L37)中的`swoole/ide-helper`可以完全删除，因为它只是开发时的IDE提示。

### 6. 前端配置调整
前端需要支持选择oha作为压测引擎，在原来的wrk/co选项基础上添加oha选项。

## WebSocket压测替代方案
由于oha主要针对HTTP，对于WebSocket压测可以考虑：
- 使用专门的WebSocket压测工具如`websocket-bench`
- 或者保留一个简化的PHP socket连接实现

## 优势分析
1. **真正跨平台**：oha在Windows/macOS/Linux都有原生支持
2. **性能优异**：Rust编写，比PHP协程效率更高
3. **维护简单**：无需复杂的进程管理和协程调度
4. **输出友好**：支持JSON输出，便于结果解析

这样改造后，你就有了一个真正跨平台、无Docker依赖的压测工具了。

建议先参考：
[API架构](5-api-architecture)
[环境配置](4-environment-setup)
[测试框架](9-testing-frameworks)
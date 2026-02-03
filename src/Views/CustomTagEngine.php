<?php

declare(strict_types=1);

namespace Core\Views;

use Latte\Engine;
use Latte\Runtime\Template;
 

class CustomTagEngine extends Engine
{
    private CustomTagPreprocessor $preprocessor;
    private string $tempDir = '';
    private CustomLatteExtension $extension;
    private ?string $templateRoot = null;
    private bool $autoPurge = false;
    private string $cacheScope = '';
    // 标记是否已完成一次模板创建以初始化 Latte 环境
    private bool $envInitialized = false;
    /** @var bool 是否输出预处理跟踪 */
    private bool $traceEnabled = false;
    /** @var bool 跟踪是否包含源模板内容 */
    private bool $traceIncludeSource = true;
    /** @var null|string 跟踪输出目录 */
    private ?string $traceDir = null;
    /** @var null|callable(array): void */
    private $traceCallback = null;

    /**
     * 构造函数：安装自定义扩展与预处理器。
     */
    public function __construct()
    {
        parent::__construct();
        $this->preprocessor = new CustomTagPreprocessor();
        $this->extension = new CustomLatteExtension();
        $this->addExtension($this->extension);
        
    }

    /**
     * 启用预处理跟踪输出（DSL -> Latte）。
     * @param bool $includeSource 是否写入原始模板内容
     * @param null|string $dir 自定义跟踪目录，默认使用 tempDir
     */
    public function enablePreprocessTrace(bool $includeSource = true, ?string $dir = null): void
    {
        $this->traceEnabled = true;
        $this->traceIncludeSource = $includeSource;
        $this->traceDir = $dir;
    }

    /** 关闭预处理跟踪输出。 */
    public function disablePreprocessTrace(): void
    {
        $this->traceEnabled = false;
        $this->traceIncludeSource = true;
        $this->traceDir = null;
    }

    /**
     * 设置预处理跟踪回调。
     * @param null|callable(array): void $callback
     */
    public function setPreprocessTraceCallback(?callable $callback): void
    {
        $this->traceCallback = $callback;
    }

    /**
     * 设置临时目录
     */
    /**
     * 设置预处理后 Latte 文件的临时目录。
     *
     * @param string|null $path 目录路径
     * @return static
     */
    public function setTempDirectory(?string $path): static
    {
        $this->tempDir = $path ?? '';
        return parent::setTempDirectory($path);
    }

    /**
     * 设置模板根目录（用于支持 '@' 路径前缀）。
     * 例如 {include '@inc/header.latte'} 将从该目录解析。
     */
    /**
     * 设置模板根目录（用于 '@' 路径前缀）并安装自定义 Loader。
     *
     * @param string|null $path 模板根目录
     * @return static
     */
    public function setTemplateRoot(?string $path): static
    {
        $this->templateRoot = $path ?: null;
        $this->setLoader(new AtFileLoader($this->templateRoot));
        return $this;
    }

    /**
     * 启用/关闭自动清理预处理缓存文件（默认关闭）。
     */
    public function enableAutoPurge(bool $on = true): static
    {
        $this->autoPurge = $on;
        return $this;
    }

    /**
     * 设定缓存作用域（级别/命名空间），参与缓存 Key 计算，实现多级别隔离。
     */
    public function setCacheScope(?string $scope): static
    {
        $this->cacheScope = (string)($scope ?? '');
        return $this;
    }

    /**
     * 创建模板对象（重写以支持自定义标签）
     *
     * @param string $name 模板文件路径
     * @param array $params 参数
     * @param bool $clearCache 是否清除缓存
     * @return Template
     */
    /**
     * 创建模板：按需对非 .latte 模板进行预处理后再交给 Latte。
     *
     * @param string $name 模板名或路径
     * @param array $params 模板参数
     * @param bool $clearCache 是否清理临时缓存
     * @return Template
     */
    public function createTemplate(string $name, array $params = [], bool $clearCache = false): Template
    {
        if ($clearCache || $this->autoPurge) {
            $this->purgeTempFiles();
        }
        $this->ensureLoader();
        if (file_exists($name)) {
            $name = $this->preprocessTemplateFile($name);
        } else {
            $name = $this->preprocessTemplateByLoader($name);
        }
        // Latte 3 在首次调用时需要初始化 environmentHash，否则会触发
        // “Typed property ...$environmentHash must not be accessed before initialization”
        // 因此首次强制传递 clearCache=true，之后遵循参数/默认值。
        $firstCallNeedsInit = !$this->envInitialized;
        $this->envInitialized = true;
        $latteClearCache = $clearCache || $firstCallNeedsInit;

        return parent::createTemplate($name, $params, $latteClearCache);
    }

    /**
     * 仅进行预处理并返回缓存文件路径（不触发 Latte 编译）。
     */
    public function preprocessToCache(string $name, bool $clearCache = false): string
    {
        if ($clearCache || $this->autoPurge) {
            $this->purgeTempFiles();
        }
        $this->ensureLoader();
        if (file_exists($name)) {
            return $this->preprocessTemplateFile($name);
        }
        return $this->preprocessTemplateByLoader($name);
    }

    /**
     * 清理临时目录下已生成的预处理 Latte 文件。
     */
    private function purgeTempFiles(): void
    {
        $dir = rtrim($this->tempDir, '/');
        if ($dir === '' || !is_dir($dir)) return;
        foreach (glob($dir . '/custom_*.latte') ?: [] as $f) {
            @unlink($f);
        }
    }

    /**
     * 预处理模板文件
     *
     * @param string $file 原始文件路径
     * @return string 处理后的文件路径
     */
    /**
     * 预处理基于文件的模板（.latte 直接跳过）。
     *
     * @param string $file 源文件路径
     * @return string 处理后的临时文件绝对路径或原始 .latte 路径
     */
    private function preprocessTemplateFile(string $file): string
    {
        if (strtolower((string)pathinfo($file, PATHINFO_EXTENSION)) === 'latte') {
            return $file;
        }
        $template = file_get_contents($file);
        $fileTime = filemtime($file) ?: time();
        $processedTemplate = $this->preprocessor->preprocess($template);
        $cacheKey = $this->computeCacheKey('f', (string)realpath($file));
        $tempFile = $this->tempFileFor($cacheKey);
        $this->writeIfChanged($tempFile, $processedTemplate, $fileTime);
        // 写入源文件目录 sidecar，供 Loader 解析相对引用时使用
        @file_put_contents($tempFile . '.srcdir', (string)dirname((string)$file));
        $this->tracePreprocess($file, $template, $processedTemplate, $tempFile);
        return $tempFile;
    }

    /**
     * 通过 Loader 读取并预处理（用于主题 FileLoader 等相对路径场景）。
     * 返回预处理后的临时文件路径，失败时返回空字符串。
     */
    /**
     * 通过 Loader 读取并预处理模板（.latte 直接跳过）。
     *
     * @param string $name 模板名
     * @return string 处理后的临时文件绝对路径或 Loader 返回名
     */
    private function preprocessTemplateByLoader(string $name): string
    {
        $loader = $this->getLoader();
        // .latte 直接交给 Loader
        if (str_ends_with(strtolower((string)$name), '.latte')) {
            return $name;
        }
        // 直接按 Loader 基于 baseDir 解析内容，避免错误的“再拼接”
        $source = $loader->getContent($name);
        $processed = $this->preprocessor->preprocess($source);
        // 使用 Loader 的唯一 ID（若存在）稳定化缓存键
        $uid = method_exists($loader, 'getUniqueId') ? (string)$loader->getUniqueId($name) : $name;
        $cacheKey = $this->computeCacheKey('ldr|' . get_class($loader), $uid);
        $tempFile = $this->tempFileFor($cacheKey);
        $this->writeIfChanged($tempFile, $processed, null);
        // 从 loader 的唯一 ID 推断源目录，写入 sidecar
        $srcDir = $this->dirOfPath($uid);
        if ($srcDir !== '') {
            @file_put_contents($tempFile . '.srcdir', $srcDir);
        }
        $this->tracePreprocess($name, $source, $processed, $tempFile);
        return $tempFile;
    }

    /**
     * 写入预处理跟踪文件。
     */
    private function tracePreprocess(string $name, string $source, string $processed, string $tempFile): void
    {
        if (!$this->traceEnabled) {
            return;
        }
        $dir = $this->traceDir ?? $this->tempDir;
        $dir = rtrim($dir, '/');
        if ($dir && !is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $payload = [
            'name' => $name,
            'cache' => $tempFile,
            'updated_at' => date('c'),
        ];
        if ($this->traceIncludeSource) {
            $payload['source'] = $source;
        }
        $payload['processed'] = $processed;
        $traceFile = $dir . '/' . basename($tempFile) . '.trace.json';
        @file_put_contents($traceFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($this->traceCallback) {
            ($this->traceCallback)($payload);
        }
    }

    /** 获取路径所在目录（纯字符串处理）。 */
    private function dirOfPath(string $path): string
    {
        $p = str_replace('\\\\', '/', $path);
        $pos = strrpos($p, '/');
        return $pos === false ? '' : substr($p, 0, $pos);
    }

    /**
     * 确保 Loader 为 AtFileLoader，并继承其唯一 ID 语义。
     */
    private function ensureLoader(): void
    {
        $loader = $this->getLoader();
        if ($loader instanceof AtFileLoader) {
            return;
        }
        $baseDir = '';
        if ($loader && method_exists($loader, 'getUniqueId')) {
            $baseDir = rtrim((string)$loader->getUniqueId(''), DIRECTORY_SEPARATOR);
        }
        $this->setLoader(new AtFileLoader($baseDir ?: $this->templateRoot));
    }

    /**
     * 计算缓存键，包含模板根、预处理版本与作用域。
     */
    private function computeCacheKey(string $prefix, string $identity): string
    {
        $version = \defined(CustomTagPreprocessor::class . '::VERSION')
            ? (string) (CustomTagPreprocessor::VERSION)
            : 'v1';
        return md5($prefix . '|' . $identity . '|' . (string)$this->templateRoot . '|' . $version . '|' . $this->cacheScope);
    }

    /**
     * 根据缓存键返回临时文件完整路径。
     */
    private function tempFileFor(string $cacheKey): string
    {
        $tempDir = rtrim($this->tempDir, '/');
        if ($tempDir && !is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }
        return $tempDir . '/custom_' . $cacheKey . '.latte';
    }

    /**
     * 仅在内容变更或源文件较新时写入，并可对目标 mtime 校正到不早于源 mtime。
     */
    private function writeIfChanged(string $targetFile, string $content, ?int $sourceMtime): void
    {
        $needWrite = !is_file($targetFile);
        if (!$needWrite) {
            $needWrite = (md5_file($targetFile) !== md5($content));
        }
        if (!$needWrite && $sourceMtime !== null) {
            $needWrite = $sourceMtime > (filemtime($targetFile) ?: 0);
        }
        if ($needWrite) {
            file_put_contents($targetFile, $content);
            if ($sourceMtime !== null) {
                @touch($targetFile, max(time(), $sourceMtime));
            }
        }
    }
}

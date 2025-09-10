<?php

declare(strict_types=1);

namespace Core\Views;

use Latte\Loaders\FileLoader;

/**
 * 模板加载器（兼容增强）：
 * - 支持 '@' 前缀（相对模板根目录）
 * - 兼容绝对路径与带协议路径（phar://、file:// 等）
 * - 相对路径优先尝试基于引用文件目录，再回退父类逻辑
 */
class AtFileLoader extends FileLoader
{
    /**
     * 解析模板引用：
     * - '@path' → 去除前缀按相对路径处理
     * - 绝对/URL → 原样返回
     * - 其他 → 尝试相对引用文件目录拼接，不命中回退父类
     */
    public function getReferredName(string $file, string $referringFile): string
    {
        if ($file !== '' && $file[0] === '@') {
            return $this->normalizePath(ltrim(substr($file, 1), '/\\'));
        }
        if ($this->isAbsolute($file) || $this->hasScheme($file)) {
            return $file;
        }

        // 1) 尝试以引用文件所在目录为基准（temp 缓存文件或真实文件）
        $base = $this->dirOf($referringFile);
        if ($base !== '' && !$this->hasScheme($base)) {
            $joined = $this->normalizePath($base . '/' . $file);
            if (is_file($joined)) {
                return $joined;
            }

            // 1.1) 如果引用方是我们生成的预处理缓存文件，读取 sidecar 获取源目录
            $sidecar = $referringFile . '.srcdir';
            if (is_file($sidecar)) {
                $srcDir = trim((string)@file_get_contents($sidecar));
                if ($srcDir !== '') {
                    $joined2 = $this->normalizePath($srcDir . '/' . $file);
                    if (is_file($joined2)) {
                        return $joined2;
                    }
                }
            }
        }

        // 2) 若配置了 baseDir（模板根），则回退为相对 baseDir 的路径
        if ($this->baseDir) {
            return $this->normalizePath($file);
        }

        // 3) 最后回退父类逻辑
        return parent::getReferredName($file, $referringFile);
    }

    /**
     * 读取模板内容：
     * - 绝对/URL 直接读取
     * - 其他回退父类（使用 baseDir 规则）
     */
    public function getContent(string $file): string
    {
        if ($this->isAbsolute($file) || $this->hasScheme($file)) {
            $content = @file_get_contents($file);
            if ($content === false) {
                throw new \RuntimeException("Unable to read template file '" . $file . "'.");
            }
            return $content;
        }
        return parent::getContent($file);
    }

    /**
     * 返回路径所在目录（仅字符串拼接，不访问文件系统）。
     */
    private function dirOf(string $path): string
    {
        if ($path === '') return '';
        $p = str_replace('\\', '/', $path);
        $pos = strrpos($p, '/');
        return $pos === false ? '' : substr($p, 0, $pos);
    }

    /**
     * 判断是否为绝对路径（Unix/Windows）。
     */
    private function isAbsolute(string $path): bool
    {
        if ($path !== '' && ($path[0] === '/' || $path[0] === '\\')) return true;
        return preg_match('~^[A-Za-z]:[\\/]~', $path) === 1;
    }

    /**
     * 判断是否包含协议（scheme://）。
     */
    private function hasScheme(string $path): bool
    {
        return preg_match('~^[a-zA-Z][a-zA-Z0-9+.-]*://~', $path) === 1;
    }

}

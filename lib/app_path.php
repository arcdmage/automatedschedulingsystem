<?php

if (!function_exists('app_base_path')) {
    function app_base_path(): string
    {
        static $basePath = null;

        if ($basePath !== null) {
            return $basePath;
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($scriptName));

        if ($dir === '/' || $dir === '\\' || $dir === '.') {
            $dir = '';
        }

        $basePath = rtrim($dir, '/');

        return $basePath;
    }

    function app_url(string $path = ''): string
    {
        $normalizedPath = ltrim($path, '/');

        if ($normalizedPath === '') {
            return app_base_path();
        }

        return app_base_path() . '/' . $normalizedPath;
    }
}

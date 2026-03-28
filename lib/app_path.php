<?php

if (!function_exists("app_base_path")) {
    function app_base_path(): string
    {
        static $basePath = null;

        if ($basePath !== null) {
            return $basePath;
        }

        $projectRoot = realpath(__DIR__ . "/..");
        $documentRoot = isset($_SERVER["DOCUMENT_ROOT"])
            ? realpath((string) $_SERVER["DOCUMENT_ROOT"])
            : false;

        if (
            $projectRoot !== false &&
            $documentRoot !== false &&
            stripos($projectRoot, $documentRoot) === 0
        ) {
            $relativePath = substr($projectRoot, strlen($documentRoot));
            $relativePath = str_replace("\\", "/", $relativePath);
            $basePath = rtrim($relativePath, "/");

            return $basePath;
        }

        $scriptName = $_SERVER["SCRIPT_NAME"] ?? "";
        $scriptDir = str_replace("\\", "/", dirname($scriptName));

        if ($scriptDir === "/" || $scriptDir === "\\" || $scriptDir === ".") {
            $scriptDir = "";
        }

        // Fallback when DOCUMENT_ROOT is unavailable. This works for root-mounted apps.
        if (str_starts_with($scriptDir, "/tabs")) {
            $scriptDir = "";
        }

        $basePath = rtrim($scriptDir, "/");

        return $basePath;
    }

    function app_url(string $path = ""): string
    {
        $normalizedPath = ltrim($path, "/");

        if ($normalizedPath === "") {
            return app_base_path();
        }

        return app_base_path() . "/" . $normalizedPath;
    }
}

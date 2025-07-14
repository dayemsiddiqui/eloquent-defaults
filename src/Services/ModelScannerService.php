<?php

namespace dayemsiddiqui\EloquentDefaults\Services;

use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionException;

class ModelScannerService
{
    protected ModelDiscoveryService $discoveryService;

    protected array $scanDirectories = [];

    protected array $excludeDirectories = [];

    protected string $cacheKey = 'eloquent_defaults.discovered_models';

    protected int $cacheTtl = 3600; // 1 hour

    protected bool $forceInDevelopment = false;

    public function __construct(ModelDiscoveryService $discoveryService)
    {
        $this->discoveryService = $discoveryService;
        $this->loadConfiguration();
    }

    public function discoverAndRegisterModels(): void
    {
        if (! $this->isAutoDiscoveryEnabled()) {
            return;
        }

        $discoveredModels = $this->getDiscoveredModels();

        foreach ($discoveredModels as $modelData) {
            $this->discoveryService->registerEloquentDefaults(
                $modelData['target_model'],
                $modelData['provider_model'],
                false // Don't register events yet - we'll do that after all discovery
            );
        }

        // Register event listeners for all discovered target models
        $this->registerEventListeners();
    }

    protected function getDiscoveredModels(): array
    {
        // Use cache in production or if forced in development
        if (app()->environment('production') || $this->forceInDevelopment) {
            return Cache::remember($this->cacheKey, $this->cacheTtl, function () {
                return $this->scanForModels();
            });
        }

        // Always scan in development for better DX
        return $this->scanForModels();
    }

    protected function scanForModels(): array
    {
        $discoveredModels = [];

        foreach ($this->scanDirectories as $directory) {
            $discoveredModels = array_merge($discoveredModels, $this->scanDirectory($directory));
        }

        return $discoveredModels;
    }

    protected function scanDirectory(string $directory): array
    {
        $discoveredModels = [];

        if (! File::isDirectory($directory)) {
            return $discoveredModels;
        }

        // Check if this directory should be excluded
        foreach ($this->excludeDirectories as $excludeDir) {
            $fullExcludePath = base_path($excludeDir);
            if (str_starts_with($directory, $fullExcludePath)) {
                return $discoveredModels;
            }
        }

        $phpFiles = File::allFiles($directory);

        foreach ($phpFiles as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $models = $this->extractModelsFromFile($file->getPathname());
            $discoveredModels = array_merge($discoveredModels, $models);
        }

        return $discoveredModels;
    }

    protected function extractModelsFromFile(string $filePath): array
    {
        $discoveredModels = [];

        try {
            $content = File::get($filePath);

            // Quick check if file contains the trait before expensive operations
            if (! str_contains($content, 'HasEloquentDefaults')) {
                return $discoveredModels;
            }

            // Extract namespace and class name
            $namespace = $this->extractNamespace($content);
            $className = $this->extractClassName($content);

            if (! $className) {
                return $discoveredModels;
            }

            $fullClassName = $namespace ? $namespace.'\\'.$className : $className;

            // Check if class uses our trait
            if ($this->classUsesHasEloquentDefaults($fullClassName)) {
                $targetModel = $this->getTargetModelClass($fullClassName);
                if ($targetModel) {
                    $discoveredModels[] = [
                        'provider_model' => $fullClassName,
                        'target_model' => $targetModel,
                        'file_path' => $filePath,
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silently skip files that can't be processed
            // This could happen with syntax errors, missing dependencies, etc.
        }

        return $discoveredModels;
    }

    protected function extractNamespace(string $content): ?string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function extractClassName(string $content): ?string
    {
        if (preg_match('/^class\s+(\w+)/m', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function classUsesHasEloquentDefaults(string $className): bool
    {
        try {
            if (! class_exists($className)) {
                // Try to include the file if class doesn't exist
                return false;
            }

            $reflection = new ReflectionClass($className);
            $traits = $reflection->getTraitNames();

            return in_array(HasEloquentDefaults::class, $traits);
        } catch (ReflectionException $e) {
            return false;
        }
    }

    protected function getTargetModelClass(string $providerClassName): ?string
    {
        try {
            if (! class_exists($providerClassName)) {
                return null;
            }

            $reflection = new ReflectionClass($providerClassName);

            if (! $reflection->hasMethod('eloquentDefaults')) {
                return null;
            }

            $method = $reflection->getMethod('eloquentDefaults');
            $parameters = $method->getParameters();

            if (empty($parameters)) {
                return null;
            }

            $parameter = $parameters[0];
            $type = $parameter->getType();

            if (! $type instanceof \ReflectionNamedType) {
                return null;
            }

            return $type->getName();
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function registerEventListeners(): void
    {
        $registrations = $this->discoveryService->getAllEloquentDefaultsRegistrations();

        foreach (array_keys($registrations) as $targetModelClass) {
            $this->discoveryService->registerEloquentDefaults(
                $targetModelClass,
                '', // Empty provider since we're just registering events
                true // Register events only
            );
        }
    }

    protected function loadConfiguration(): void
    {
        $basePath = base_path();

        // Load scan directories from config
        $configDirectories = config('eloquent-defaults.auto_discovery.scan_directories', ['app/Models', 'app']);
        $additionalDirectories = config('eloquent-defaults.auto_discovery.additional_directories', []);

        $allDirectories = array_merge($configDirectories, $additionalDirectories);
        $this->scanDirectories = array_map(fn ($dir) => $basePath.'/'.ltrim($dir, '/'), $allDirectories);

        // Load exclude directories
        $excludeDirectories = config('eloquent-defaults.auto_discovery.exclude_directories', []);
        $this->excludeDirectories = array_map(fn ($dir) => ltrim($dir, '/'), $excludeDirectories);

        // Load cache settings
        $this->cacheKey = config('eloquent-defaults.cache.key', 'eloquent_defaults.discovered_models');
        $this->cacheTtl = config('eloquent-defaults.cache.ttl', 3600);
        $this->forceInDevelopment = config('eloquent-defaults.cache.force_in_development', false);
    }

    public function isAutoDiscoveryEnabled(): bool
    {
        return config('eloquent-defaults.auto_discovery.enabled', true);
    }

    public function setScanDirectories(array $directories): void
    {
        $this->scanDirectories = $directories;
    }

    public function addScanDirectory(string $directory): void
    {
        $this->scanDirectories[] = $directory;
    }

    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
    }

    public function setCacheKey(string $key): void
    {
        $this->cacheKey = $key;
    }

    public function setCacheTtl(int $ttl): void
    {
        $this->cacheTtl = $ttl;
    }
}

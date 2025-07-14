<?php

namespace dayemsiddiqui\EloquentDefaults\Services;

use Illuminate\Support\Facades\DB;

class ModelDiscoveryService
{
    protected array $eloquentDefaultsRegistry = [];

    protected array $creationStack = [];

    public function registerEloquentDefaults(string $targetModelClass, string $providerModelClass, bool $registerEvents = true): void
    {
        if (! isset($this->eloquentDefaultsRegistry[$targetModelClass])) {
            $this->eloquentDefaultsRegistry[$targetModelClass] = [];
        }

        // Prevent duplicate registrations
        if (! in_array($providerModelClass, $this->eloquentDefaultsRegistry[$targetModelClass])) {
            $this->eloquentDefaultsRegistry[$targetModelClass][] = $providerModelClass;

            // TODO: Implement circular dependency detection
            // For now, we rely on the runtime creation stack protection
        }

        if ($registerEvents) {
            $this->registerEventListeners($targetModelClass);
        }
    }

    public function getEloquentDefaultsProviders(string $targetModelClass): array
    {
        return $this->eloquentDefaultsRegistry[$targetModelClass] ?? [];
    }

    public function getAllEloquentDefaultsRegistrations(): array
    {
        return $this->eloquentDefaultsRegistry;
    }

    protected array $registeredEvents = [];

    protected function registerEventListeners(string $rootModelClass): void
    {
        if (in_array($rootModelClass, $this->registeredEvents)) {
            return;
        }

        $rootModelClass::created(function ($model) {
            $this->handleModelCreated($model);
        });

        $this->registeredEvents[] = $rootModelClass;
    }

    protected function handleModelCreated($model): void
    {
        $modelClass = get_class($model);
        $modelKey = $modelClass.':'.$model->getKey();

        // Check for circular dependencies
        if (in_array($modelKey, $this->creationStack)) {
            \Illuminate\Support\Facades\Log::warning(
                "Circular dependency detected: {$modelKey} is already in creation stack",
                ['stack' => $this->creationStack]
            );

            return;
        }

        // Add to creation stack
        $this->creationStack[] = $modelKey;

        try {
            // Handle HasEloquentDefaults trait
            $this->handleEloquentDefaults($model);

        } finally {
            // Always remove from creation stack when done
            $this->creationStack = array_filter(
                $this->creationStack,
                fn ($item) => $item !== $modelKey
            );
        }
    }

    protected function handleEloquentDefaults($model): void
    {
        $modelClass = get_class($model);
        $providerModels = $this->getEloquentDefaultsProviders($modelClass);

        if (empty($providerModels)) {
            return;
        }

        DB::transaction(function () use ($providerModels, $model, $modelClass) {
            $allDefaultModels = [];

            // Collect all default models from all providers
            foreach ($providerModels as $providerModelClass) {
                try {
                    $defaultModels = $providerModelClass::createEloquentDefaults($model);
                    $allDefaultModels = array_merge($allDefaultModels, $defaultModels);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error(
                        "Failed to create eloquent defaults from {$providerModelClass} for {$modelClass}",
                        [
                            'provider' => $providerModelClass,
                            'target' => $modelClass,
                            'error' => $e->getMessage(),
                            'creation_stack' => $this->creationStack,
                        ]
                    );
                    throw $e;
                }
            }

            // Save all models
            foreach ($allDefaultModels as $defaultModel) {
                $defaultModel->save();
            }
        });
    }

    public function clearCache(): void
    {
        // Clear the registries for testing purposes
        $this->eloquentDefaultsRegistry = [];
        $this->registeredEvents = [];
        $this->creationStack = [];
    }
}

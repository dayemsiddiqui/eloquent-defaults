<?php

namespace dayemsiddiqui\EloquentDefaults;

use dayemsiddiqui\EloquentDefaults\Services\ModelDiscoveryService;

class EloquentDefaults
{
    protected ModelDiscoveryService $discoveryService;

    public function __construct(ModelDiscoveryService $discoveryService)
    {
        $this->discoveryService = $discoveryService;
    }

    public function getEloquentDefaultsProviders(string $targetModelClass): array
    {
        return $this->discoveryService->getEloquentDefaultsProviders($targetModelClass);
    }

    public function getAllEloquentDefaultsRegistrations(): array
    {
        return $this->discoveryService->getAllEloquentDefaultsRegistrations();
    }

    public function clearCache(): void
    {
        $this->discoveryService->clearCache();
    }

    public function debugRegistrations(): array
    {
        $eloquentDefaultsRegistrations = $this->getAllEloquentDefaultsRegistrations();
        $debug = [];

        // Debug HasEloquentDefaults registrations
        foreach ($eloquentDefaultsRegistrations as $targetModel => $providerModels) {
            $debug[$targetModel] = [
                'target_model' => $targetModel,
                'provider_models' => $providerModels,
                'provider_count' => count($providerModels),
                'type' => 'HasEloquentDefaults',
            ];
        }

        return $debug;
    }
}

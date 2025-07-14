<?php

namespace dayemsiddiqui\EloquentDefaults\Traits;

use dayemsiddiqui\EloquentDefaults\Exceptions\InvalidConfigurationException;
use dayemsiddiqui\EloquentDefaults\Services\ModelDiscoveryService;
use Illuminate\Database\Eloquent\Model;

/**
 * @template T of Model
 */
trait HasEloquentDefaults
{
    public static function bootHasEloquentDefaults(): void
    {
        static::validateEloquentDefaultsConfiguration();
        static::registerWithEloquentDefaultsDiscoveryService();
    }

    protected static function validateEloquentDefaultsConfiguration(): void
    {
        if (! method_exists(static::class, 'eloquentDefaults')) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Model [%s] uses HasEloquentDefaults trait but does not define eloquentDefaults() method.',
                    static::class
                )
            );
        }

        // Extract the generic type from the trait usage
        $rootModelClass = static::getEloquentDefaultsTargetModel();
        if (! class_exists($rootModelClass)) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Target model [%s] specified in [%s] does not exist.',
                    $rootModelClass,
                    static::class
                )
            );
        }
    }

    protected static function registerWithEloquentDefaultsDiscoveryService(): void
    {
        $discoveryService = app(ModelDiscoveryService::class);
        $targetModelClass = static::getEloquentDefaultsTargetModel();
        $discoveryService->registerEloquentDefaults($targetModelClass, static::class);
    }

    /**
     * Extract the target model class from the trait's generic type usage.
     * This uses reflection to determine the target model.
     */
    protected static function getEloquentDefaultsTargetModel(): string
    {
        // For now, we'll use a simple approach where the target model is determined
        // by looking at the method signature of eloquentDefaults()
        $reflection = new \ReflectionMethod(static::class, 'eloquentDefaults');
        $parameters = $reflection->getParameters();

        if (empty($parameters)) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Method eloquentDefaults() in [%s] must accept a parameter with the target model type.',
                    static::class
                )
            );
        }

        $parameter = $parameters[0];
        $type = $parameter->getType();

        if (! $type instanceof \ReflectionNamedType) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Parameter in eloquentDefaults() method of [%s] must have a typed parameter.',
                    static::class
                )
            );
        }

        return $type->getName();
    }

    /**
     * Create default model instances for the given target model.
     *
     * @param  T  $targetModel
     * @return array<Model>
     */
    public static function createEloquentDefaults($targetModel): array
    {
        $defaultModels = static::eloquentDefaults($targetModel);

        if (! is_array($defaultModels)) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Method eloquentDefaults() in [%s] must return an array of model instances.',
                    static::class
                )
            );
        }

        // Validate that all returned items are model instances
        foreach ($defaultModels as $index => $model) {
            if (! $model instanceof Model) {
                throw new InvalidConfigurationException(
                    sprintf(
                        'Item at index %d returned by eloquentDefaults() in [%s] must be a Model instance, %s given.',
                        $index,
                        static::class,
                        is_object($model) ? get_class($model) : gettype($model)
                    )
                );
            }
        }

        return $defaultModels;
    }

    /**
     * This method should be implemented by the using class.
     * It should return an array of Model instances created with ::make().
     *
     * @param  T  $targetModel
     * @return array<Model>
     */
    protected static function eloquentDefaults($targetModel): array
    {
        throw new InvalidConfigurationException(
            sprintf(
                'Model [%s] uses HasEloquentDefaults trait but does not override the eloquentDefaults() method.',
                static::class
            )
        );
    }
}

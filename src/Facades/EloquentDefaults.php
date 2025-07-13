<?php

namespace dayemsiddiqui\EloquentDefaults\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \dayemsiddiqui\EloquentDefaults\EloquentDefaults
 */
class EloquentDefaults extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \dayemsiddiqui\EloquentDefaults\EloquentDefaults::class;
    }
}

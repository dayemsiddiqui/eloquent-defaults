<?php

it('has HasEloquentDefaults trait available', function () {
    expect(trait_exists(\dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults::class))->toBeTrue();
});

it('has EloquentDefaults facade available', function () {
    expect(class_exists(\dayemsiddiqui\EloquentDefaults\EloquentDefaults::class))->toBeTrue();
});

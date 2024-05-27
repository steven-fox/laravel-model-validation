<?php

namespace StevenFox\LaravelModelValidation\Listeners;

use StevenFox\LaravelModelValidation\Contracts\ValidatesWhenSaving;

class ValidateModel
{
    public function handle(mixed $model): void
    {
        if (! $model instanceof ValidatesWhenSaving) {
            return;
        }

        if ($model::shouldNotValidateWhenSaving()) {
            return;
        }

        $model->validate();
    }
}

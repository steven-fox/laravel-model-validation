<?php

namespace StevenFox\LaravelModelValidation\Listeners;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ValidateModel
{
    public function handle(Model $model): void
    {
        if (! method_exists($model, 'validate')) {
            throw new InvalidArgumentException(
                "The model must have a 'validate()' method."
            );
        }

        if (! method_exists($model, 'shouldNotValidateWhenSaving')) {
            throw new InvalidArgumentException(
                "The model must have a static 'shouldNotValidateWhenSaving()' method."
            );
        }

        if ($model::shouldNotValidateWhenSaving()) {
            return;
        }

        $model->validate();
    }
}

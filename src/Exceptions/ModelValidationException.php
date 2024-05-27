<?php

namespace StevenFox\LaravelModelValidation\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ModelValidationException extends ValidationException
{
    public function __construct(
        public Model $model,
        $validator,
        $response = null,
        $errorBag = 'default'
    ) {
        parent::__construct($validator, $response, $errorBag);
    }
}

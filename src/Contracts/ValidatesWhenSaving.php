<?php

namespace StevenFox\LaravelModelValidation\Contracts;

use Illuminate\Validation\Validator;

interface ValidatesWhenSaving
{
    public static function shouldNotValidateWhenSaving(): bool;

    public function validate(): Validator;
}

<?php

namespace StevenFox\LaravelModelValidation\Tests\Fixtures;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Database\Eloquent\Model;
use StevenFox\LaravelModelValidation\ValidatesAttributes;

class ValidatingModel extends Model
{
    use ValidatesAttributes;

    protected $guarded = [];

    protected function commonValidationRules(): array
    {
        return [
            'required_string' => ['required', 'string'],
            'stringable' => ['string'],
            'unique_column' => $this->uniqueRule(),
            'datetime' => ['date'],
            'json' => ['json'],
            'array_object' => ['array'],
            'collection' => ['array'],
            'encrypted_object' => ['encrypted'],
        ];
    }

    protected function casts(): array
    {
        return [
            'required_string' => 'string',
            'stringable' => AsStringable::class,
            'datetime' => 'datetime',
            'json' => 'array',
            'array_object' => AsArrayObject::class,
            'collection' => AsCollection::class,
            'encrypted_object' => AsEncryptedArrayObject::class,
        ];
    }
}

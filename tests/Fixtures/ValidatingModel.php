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

    protected $casts = [
        'required_string' => 'string',
        'stringable' => AsStringable::class,
        'datetime' => 'datetime',
        'json' => 'array',
        'array_object' => AsArrayObject::class,
        'collection' => AsCollection::class,
        'encrypted_object' => AsEncryptedArrayObject::class,
    ];

    protected function baseValidationRules(): array
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

    protected function prepareAttributesForValidation(array $rawAttributes): array
    {
        $rawAttributes['array_object'] = $this->array_object?->toArray();
        $rawAttributes['collection'] = $this->collection?->toArray();

        return $rawAttributes;
    }

    public function customValidationMessages(): array
    {
        return [
            'required_string.string' => 'This is a custom message for the required_string.string rule',
        ];
    }

    public function customValidationAttributeNames(): array
    {
        return [
            'datetime' => 'custom datetime attribute name',
        ];
    }
}

<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Validator;
use StevenFox\LaravelModelValidation\Exceptions\ModelValidationException;
use StevenFox\LaravelModelValidation\Listeners\ValidateModel;
use StevenFox\LaravelModelValidation\Tests\Fixtures\ValidatesWhenSavingModel;
use StevenFox\LaravelModelValidation\Tests\Fixtures\ValidatingModel;

beforeEach(function () {
    // Reset the static variable on the trait prior to each test
    // to avoid state corruption.
    ValidatingModel::enableValidationWhenSaving();
});

it('registers a "creating" model event listeners when shouldValidateWhenSaving() is true', function () {
    Event::forget('*');
    Event::fake();

    $m = new ValidatesWhenSavingModel();
    $modelClassName = $m::class;

    expect($m::shouldValidateWhenSaving())->toBeTrue();

    Event::assertListening("eloquent.creating: {$modelClassName}", ValidateModel::class);
});

it('registers an "updating" model event listeners when shouldValidateWhenSaving() is true', function () {
    Event::forget('*');
    Event::fake();

    $m = new ValidatesWhenSavingModel();
    $modelClassName = $m::class;

    expect($m::shouldValidateWhenSaving())->toBeTrue();

    Event::assertListening("eloquent.updating: {$modelClassName}", ValidateModel::class);
});

it('will not register the model event listeners when shouldValidateOnSaving() is false', function () {
    Event::forget('*');
    Event::fake();

    ValidatingModel::disableValidationWhenSaving();

    $m = new ValidatingModel();
    $modelClassName = $m::class;

    expect($m::shouldValidateWhenSaving())->toBeFalse()
        ->and(Event::hasListeners("eloquent.updating: {$modelClassName}"))
        ->toBeFalse()
        ->and(Event::hasListeners("eloquent.creating: {$modelClassName}"))
        ->toBeFalse();
});

it('the model event listeners will be registered even if validation is disabled during boot', function () {
    Event::forget('*');
    Event::fake();

    ValidatingModel::disableValidationWhenSaving();

    $m = new ValidatesWhenSavingModel();
    $modelClassName = $m::class;

    ValidatingModel::enableValidationWhenSaving();

    expect($m::shouldValidateWhenSaving())->toBeTrue()
        ->and(Event::hasListeners("eloquent.updating: {$modelClassName}"))
        ->toBeTrue()
        ->and(Event::hasListeners("eloquent.creating: {$modelClassName}"))
        ->toBeTrue();
});

it('shouldNotValidateWhenSaving() will return the opposite of shouldValidateWhenSaving()', function () {
    expect(ValidatesWhenSavingModel::shouldNotValidateWhenSaving())
        ->toBeFalse()
        ->toBe(! ValidatesWhenSavingModel::shouldValidateWhenSaving());

    ValidatesWhenSavingModel::disableValidationWhenSaving();

    expect(ValidatesWhenSavingModel::shouldNotValidateWhenSaving())
        ->toBeTrue()
        ->toBe(! ValidatesWhenSavingModel::shouldValidateWhenSaving());
});

it('provides a whileValidatingDisabled() function to run a callback while validation is disabled', function () {
    $this->markTestSkipped();
});

it('can validate a model and throw the ModelValidationException upon failure', function () {
    $this->expectException(ModelValidationException::class);
    $this->expectExceptionMessage('The required string field is required.');

    $m = new ValidatingModel();

    $m->validate();
});

it('will validate a model during the save process when applicable', function () {
    $this->expectException(ModelValidationException::class);
    $this->expectExceptionMessage('The required string field is required.');

    $m = new ValidatesWhenSavingModel();

    $m->save();
    // Exception should be thrown
});

it('will NOT validate a model during the save process when inapplicable', function () {
    $this->expectException(QueryException::class);

    $m = new ValidatingModel();

    $m->save();
    // Exception should be thrown
});

it('has a passesValidation method', function () {
    $m = new ValidatingModel();

    expect($m->passesValidation())->toBeFalse();

    $m->fill([
        'required_string' => 'foo',
        'stringable' => str('foo'),
        'datetime' => Date::create(2000, 1, 1),
        'json' => ['foo' => 'bar'],
        'array_object' => ['foo' => 'bar'],
        'collection' => collect(['foo' => 'bar']),
    ]);

    $m->validate();

    expect($m->passesValidation())->toBeTrue();
});

it('will use database-prepared attribute values for validation by default', function () {
    $m = new ValidatingModel([
        'stringable' => str('foo'),
        'datetime' => Date::create(2000, 1, 1),
        'json' => ['foo' => 'bar'],
    ]);

    expect($m->validationData())->toBe([
        'stringable' => 'foo',
        'datetime' => '2000-01-01 00:00:00',
        'json' => '{"foo":"bar"}',
        'array_object' => null,
        'collection' => null,
    ]);
});

it('can prepare the validation data with the prepareAttributesForValidation method', function () {
    $m = new ValidatingModel([
        'array_object' => ['foo' => 'bar'],
        'collection' => ['foo' => 'bar'],
    ]);

    /** @see ValidatingModel::prepareAttributesForValidation() */
    expect($m->validationData())->toBe([
        'array_object' => ['foo' => 'bar'],
        'collection' => ['foo' => 'bar'],
    ]);
});

it('can use superseding validation rules', function () {
    $m = new ValidatingModel([
        'required_string' => null,
    ]);

    $m->setSupersedingValidationRules([
        'required_string' => 'nullable|string',
    ]);

    expect($m->getSupersedingValidationRules())
        ->toBe(['required_string' => 'nullable|string'])
        ->and($m->makeValidator()->getRules())
        ->toBe(['required_string' => ['nullable', 'string']])
        ->and($m->validate()->fails())
        ->toBeFalse();
});

it('can clear the superseding validation rules', function () {
    $m = new ValidatingModel();

    $m->setSupersedingValidationRules([
        'required_string' => 'nullable|string',
    ]);

    expect($m->getSupersedingValidationRules())->not()->toBeEmpty()
        ->and($m->makeValidator()->getRules())->toBe(['required_string' => ['nullable', 'string']]);

    $m->clearSupersedingValidationRules();

    expect($m->getSupersedingValidationRules())->toBeEmpty()
        ->and($m->makeValidator()->getRules())->not()->toBe(['required_string' => ['nullable', 'string']]);
});

it('can use mixin validation rules', function () {
    $m = new ValidatingModel([
        'required_string' => null,
    ]);

    $m->addMixinValidationRules([
        'required_string' => 'nullable|string',
        'array_object' => 'nullable|array',
        'collection' => 'nullable|array',
    ]);

    expect($m->getMixinValidationRules())
        ->toBe([
            'required_string' => 'nullable|string',
            'array_object' => 'nullable|array',
            'collection' => 'nullable|array',
        ])
        ->and($m->makeValidator()->getRules())
        ->toBe([
            'required_string' => ['nullable', 'string'],
            'stringable' => ['string'],
            'unique_column' => ['unique:validating_models,NULL,NULL,id'],
            'datetime' => ['date'],
            'json' => ['json'],
            'array_object' => ['nullable', 'array'],
            'collection' => ['nullable', 'array'],
            'encrypted_object' => ['encrypted'],
        ])
        ->and($m->validate()->fails())
        ->toBeFalse();
});

it('can clear the mixin validation rules', function () {
    $m = new ValidatingModel();

    $m->addMixinValidationRules([
        'required_string' => 'nullable|string',
    ]);

    expect($m->getMixinValidationRules())->not()->toBeEmpty()
        ->and($m->makeValidator()->getRules())->toBe([
            'required_string' => ['nullable', 'string'],
            'stringable' => ['string'],
            'unique_column' => ['unique:validating_models,NULL,NULL,id'],
            'datetime' => ['date'],
            'json' => ['json'],
            'array_object' => ['array'],
            'collection' => ['array'],
            'encrypted_object' => ['encrypted'],
        ]);

    $m->clearMixinValidationRules();

    expect($m->getMixinValidationRules())->toBeEmpty()
        ->and($m->makeValidator()->getRules())->toBe([
            'required_string' => ['required', 'string'],
            'stringable' => ['string'],
            'unique_column' => ['unique:validating_models,NULL,NULL,id'],
            'datetime' => ['date'],
            'json' => ['json'],
            'array_object' => ['array'],
            'collection' => ['array'],
            'encrypted_object' => ['encrypted'],
        ]);
});

it('uses independent rules for updating vs creating', function () {
    $m = new ValidatingModel();

    expect($m->exists)->toBeFalse()
        ->and($m->makeValidator()->getRules()['unique_column'])
        ->toBe(['unique:validating_models,NULL,NULL,id']);

    $m->id = 127;
    $m->exists = true;

    expect($m->exists)->toBeTrue()
        ->and($m->makeValidator()->getRules()['unique_column'])
        ->toBe(['unique:validating_models,NULL,"127",id']); // The model key is now a part of the rule for ignoring
});

it('can use custom validation messages', function () {
    /** @see ValidatingModel::customValidationMessages() */
    $m = new ValidatingModel([
        'required_string' => 123,
    ]);

    $m->passesValidation();

    expect($m->validator()->errors()->first('required_string'))
        ->toBe('This is a custom message for the required_string.string rule');
});

it('can use custom validation attribute names', function () {
    /** @see ValidatingModel::customValidationMessages() */
    $m = new ValidatingModel([
        'datetime' => Date::create(2000, 1, 1),
    ]);

    // Only test the datetime attribute...
    $m->setSupersedingValidationRules([
        'datetime' => ['date', 'after:2000-01-01'],
    ]);

    $m->passesValidation();

    expect($m->validator()->errors()->first('datetime'))
        ->toBe('The custom datetime attribute name field must be a date after 2000-01-01.');
});

it('provides public access to the validation configuration', function () {
    $m = new ValidatingModel([
        'required_string' => 'foo',
        'stringable' => 'bar',
    ]);

    $rules = $m->validationRules();

    expect($rules['required_string'])
        ->toBe(['required', 'string'])
        ->and($rules['stringable'])
        ->toBe(['string'])
        ->and($m->validationData())
        ->toMatchArray([
            'required_string' => 'foo',
            'stringable' => 'bar',
        ])
        ->and($m->customValidationMessages())
        ->toBe(['required_string.string' => 'This is a custom message for the required_string.string rule'])
        ->and($m->customValidationAttributeNames())
        ->toBe(['datetime' => 'custom datetime attribute name']);
});

it('provides a static validating method to register an event hook', function () {
    ValidatingModel::validating(function (ValidatingModel $model, Validator $validator) {
        expect($model->id)->toBe(123)
            ->and($validator->failed())->toBeEmpty();
    });

    $m = new ValidatingModel([
        'id' => 123,
    ]);

    // The following should trigger the event.
    $m->passesValidation();
});

it('provides a static validated method to register an event hook', function () {
    ValidatingModel::validated(function (ValidatingModel $model, Validator $validator) {
        expect($model->id)->toBe(123)
            ->and($validator->failed())->not()->toBeEmpty();
    });

    $m = new ValidatingModel([
        'id' => 123,
    ]);

    // The following should trigger the event.
    $m->passesValidation();
});

it('registers observable events', function () {
    $m = new ValidatingModel();

    expect($m->getObservableEvents())
        ->toContain('validating', 'validated');
});

it('throws a ModelValidationException', function () {
    $m = new ValidatingModel();

    try {
        $m->validate();
    } catch (ModelValidationException $e) {
        expect($e->model)->toBe($m);
    }
});

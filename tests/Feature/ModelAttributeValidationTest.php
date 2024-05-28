<?php

use Illuminate\Support\Facades\Event;
use StevenFox\LaravelModelValidation\Exceptions\ModelValidationException;
use StevenFox\LaravelModelValidation\Listeners\ValidateModel;
use StevenFox\LaravelModelValidation\Tests\Fixtures\ValidatesWhenSavingModel;
use StevenFox\LaravelModelValidation\Tests\Fixtures\ValidatingModel;

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

    ValidatingModel::reactivateValidationWhenSaving();

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

it('will use database-prepared attribute values for validation by default', function () {
    $m = new ValidatingModel([
        'stringable' => str('foo'),
        'datetime' => \Illuminate\Support\Facades\Date::create(2000, 1, 1),
        'json' => ['foo' => 'bar'],
        'array_object' => ['foo' => 'bar'],
        'collection' => collect(['foo' => 'bar']),
    ]);

    expect($m->validationData())->toBe([
        'stringable' => 'foo',
        'datetime' => '2000-01-01 00:00:00',
        'json' => '{"foo":"bar"}',
        'array_object' => '{"foo":"bar"}',
        'collection' => '{"foo":"bar"}',
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

<?php

use Illuminate\Support\Facades\Event;
use StevenFox\LaravelModelValidation\Listeners\ValidateModel;
use StevenFox\LaravelModelValidation\Tests\Fixtures\ValidatesWhenSavingModel;
use StevenFox\LaravelModelValidation\Tests\Fixtures\ValidatingModel;

it('does stuff', function () {
    $m = new ValidatingModel();
    $mm = new ValidatesWhenSavingModel();

    $mm->save();
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

    $m = new ValidatingModel();
    $modelClassName = $m::class;

    expect($m::shouldValidateWhenSaving())->toBeTrue();

    Event::assertListening("eloquent.updating: {$modelClassName}", Closure::class);
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
it('will re-register the model event listeners when reactivateValidationWhenSaving() is invoked', function () {
    Event::forget('*');
    Event::fake();

    ValidatingModel::disableValidationWhenSaving();

    $m = new ValidatingModel();
    $modelClassName = $m::class;

    ValidatingModel::reactivateValidationWhenSaving();

    expect($m::shouldValidateWhenSaving())->toBeTrue()
        ->and(Event::hasListeners("eloquent.updating: {$modelClassName}"))
        ->toBeTrue()
        ->and(Event::hasListeners("eloquent.creating: {$modelClassName}"))
        ->toBeTrue();
});

it('shouldNotValidateWhenSaving() will return the opposite of shouldValidateWhenSaving()', function () {
    expect(ValidatingModel::shouldNotValidateWhenSaving())
        ->toBeFalse()
        ->toBe(! ValidatingModel::shouldValidateWhenSaving());

    ValidatingModel::disableValidationWhenSaving();

    expect(ValidatingModel::shouldNotValidateWhenSaving())
        ->toBeTrue()
        ->toBe(! ValidatingModel::shouldValidateWhenSaving());
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
        'json' => ['foo' => 'bar'],
    ]);

    expect($m->validationData())->toBe([
        'json' => \Illuminate\Database\Eloquent\Casts\Json::encode(['foo' => 'bar']),
    ]);
});

it('will stop the validation process and return false if a "validating" event listener returns false', function () {
    Event::listen(
        'eloquent.validating: '.ValidatingModel::class,
        function (ValidatingModel $model, \Illuminate\Validation\Validator $validator) {
            return false;
        });

    $m = new ValidatingModel();

    expect($m->validate())->toBeFalse();
});

it('will stop the validation process and return false if a "validated" event listener returns false', function () {
    Event::listen(
        'eloquent.validated: '.ValidatingModel::class,
        function (ValidatingModel $model, \Illuminate\Validation\Validator $validator) {
            return false;
        });

    $m = new ValidatingModel();

    expect($m->validate())->toBeFalse();
});

it('can use temporary validation rules', function () {
    $m = new ValidatingModel([
        'required_string' => null,
    ]);

    $m->setTemporaryValidationRules([
        'required_string' => 'nullable|string',
    ]);

    expect($m->getTemporaryValidationRules())
        ->toBe(['required_string' => 'nullable|string'])
        ->and($m->makeValidator()->getRules())
        ->toBe(['required_string' => ['nullable', 'string']])
        ->and($m->validate()->fails())
        ->toBeFalse();
});

it('can clear the temporary validation rules', function () {
    $m = new ValidatingModel();

    $m->setTemporaryValidationRules([
        'required_string' => 'nullable|string',
    ]);

    expect($m->getTemporaryValidationRules())->not()->toBeEmpty();

    $m->clearTemporaryValidationRules();

    expect($m->getTemporaryValidationRules())->toBeEmpty();
});

it('uses independent rules for updating vs creating', function () {
    $m = new ValidatingModel();

    expect($m->exists)->toBeFalse()
        ->and($m->makeValidator()->getRules()['unique'])
        ->toBe(['unique:short_urls,url_key,NULL,id']);

    $m->id = 127;
    $m->exists = true;

    expect($m->exists)->toBeTrue()
        ->and($m->makeValidator()->getRules()['unique'])
        ->toBe(['unique:short_urls,url_key,"127",id']); // The model key is now a part of the rule for ignoring
});

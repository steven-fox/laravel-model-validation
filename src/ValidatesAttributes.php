<?php

namespace StevenFox\LaravelModelValidation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use StevenFox\LaravelModelValidation\Exceptions\ModelValidationException;
use StevenFox\LaravelModelValidation\Listeners\ValidateModel;

/**
 * @mixin Model
 */
trait ValidatesAttributes
{
    /**
     * Indicates if all attribute validation is enabled.
     */
    protected static bool $validateWhenSaving = true;

    /**
     * Indicates if the validation listeners have been registered.
     */
    private static bool $validationListenersRegistered = false;

    /**
     * Validation rules that can be temporarily set to override the
     * rules defined from the models' methods.
     */
    protected array $temporaryValidationRules = [];

    public static function bootValidatesAttributes(): void
    {
        self::registerEventListeners();
    }

    protected function initializeValidatesAttributes(): void
    {
        $this->addObservableEvents([
            'validating',
            'validated',
        ]);
    }

    private static function registerEventListeners(): void
    {
        if (static::$validationListenersRegistered) {
            return;
        }

        foreach (static::listeners() as $event => $listener) {
            static::{$event}($listener);
        }

        static::$validationListenersRegistered = true;
    }

    /**
     * @return array<array-key, class-string|\Illuminate\Events\QueuedClosure|\Closure|array>
     */
    protected static function listeners(): array
    {
        // We specifically use the 'creating' and 'updating' events
        // over the more general 'saving' event so that we don't
        // redundantly validate a model that is "saved" without
        // any changed attributes (which would NOT fire an
        // 'updating' event, saving us from redundancy).
        return [
            'creating' => ValidateModel::class,
            'updating' => ValidateModel::class,
        ];
    }

    public static function disableValidationWhenSaving(): void
    {
        static::$validateWhenSaving = false;
    }

    public static function reactivateValidationWhenSaving(): void
    {
        static::$validateWhenSaving = true;

        static::registerEventListeners();
    }

    public static function shouldValidateWhenSaving(): bool
    {
        return static::$validateWhenSaving;
    }

    public static function shouldNotValidateWhenSaving(): bool
    {
        return ! static::shouldValidateWhenSaving();
    }

    public static function whileValidationDisabled(callable $callback): mixed
    {
        if (static::shouldNotValidateWhenSaving()) {
            return $callback();
        }

        static::disableValidationWhenSaving();

        try {
            return $callback();
        } finally {
            static::reactivateValidationWhenSaving();
        }
    }

    public static function validating(callable|string|array $callback): void
    {
        static::registerModelEvent('validating', $callback);
    }

    public static function validated(callable|string|array $callback): void
    {
        static::registerModelEvent('validated', $callback);
    }

    /**
     * Validate the attributes on the model.
     *
     * @return Validator|false Returns the Validator instance upon success.
     *                         Throws a ModelValidationException if validation fails.
     */
    public function validate(): Validator|false
    {
        $validator = $this->makeValidator();

        $this->fireModelValidationEvent('validating', $validator);

        $fails = $validator->fails();

        $this->fireModelValidationEvent('validated', $validator);

        if ($fails) {
            $this->throwValidationException($validator);
        }

        return $validator;
    }

    /**
     * @throws ModelValidationException
     */
    protected function throwValidationException(Validator $validator): never
    {
        $exceptionClass = $this->validationExceptionClass();

        throw new $exceptionClass($this, $validator);
    }

    /**
     * @return class-string<ModelValidationException>
     */
    protected function validationExceptionClass(): string
    {
        return ModelValidationException::class;
    }

    public function makeValidator(): Validator
    {
        $this->beforeMakingValidator();

        $validator = \Illuminate\Support\Facades\Validator::make(
            $this->validationData(),
            $this->validationRules(),
            $this->customValidationMessages(),
            $this->customValidationAttributeNames(),
        );

        $this->afterMakingValidator($validator);

        return $validator;
    }

    protected function beforeMakingValidator(): void
    {
        //
    }

    protected function afterMakingValidator(Validator $validator): void
    {
        //
    }

    public function validationData(): array
    {
        return $this->prepareAttributesForValidation(
            $this->rawAttributesForValidation()
        );
    }

    protected function rawAttributesForValidation(): array
    {
        return $this->getAttributes();
    }

    protected function prepareAttributesForValidation(array $rawAttributes): array
    {
        return $rawAttributes;
    }

    public function validationRules(): array
    {
        // If the developer has set temporary validation rules for the model,
        // we will use those. Otherwise, we will retrieve the rules from the
        // method(s) defined on the model.
        if ($tempRules = $this->getTemporaryValidationRules()) {
            return $tempRules;
        }

        if ($this->exists) {
            return $this->validationRulesForUpdating();
        }

        return $this->validationRulesForCreating();
    }

    public function getTemporaryValidationRules(): array
    {
        return $this->temporaryValidationRules;
    }

    public function setTemporaryValidationRules(array $rules): static
    {
        $this->temporaryValidationRules = $rules;

        return $this;
    }

    public function clearTemporaryValidationRules(): static
    {
        $this->temporaryValidationRules = [];

        return $this;
    }

    protected function validationRulesForCreating(): array
    {
        return [
            ...$this->commonValidationRules(),
            ...$this->validationRulesUniqueToCreating(),
        ];
    }

    protected function validationRulesForUpdating(): array
    {
        return [
            ...$this->commonValidationRules(),
            ...$this->validationRulesUniqueToUpdating(),
        ];
    }

    protected function validationRulesUniqueToUpdating(): array
    {
        return [];
    }

    protected function validationRulesUniqueToCreating(): array
    {
        return [];
    }

    protected function commonValidationRules(): array
    {
        return [];
    }

    public function customValidationMessages(): array
    {
        return [];
    }

    public function customValidationAttributeNames(): array
    {
        return [];
    }

    /**
     * Determine if the model passes validation.
     */
    public function passesValidation(): bool
    {
        try {
            $this->validate();

            return true;
        } catch (ValidationException $e) {
            return false;
        }
    }

    /**
     * Determine if the model fails validation.
     */
    public function failsValidation(): bool
    {
        return ! $this->passesValidation();
    }

    /**
     * Fire a model event that passes the validator instance into the payload.
     *
     * @see Model::fireModelEvent()
     */
    private function fireModelValidationEvent(string $event, Validator $validator, $halt = false): mixed
    {
        if (! isset(static::$dispatcher)) {
            return true;
        }

        $method = $halt ? 'until' : 'dispatch';

        $result = $this->filterModelEventResults(
            $this->fireCustomModelValidationEvent($event, $validator, $method)
        );

        if ($result === false) {
            return false;
        }

        return ! empty($result)
            ? $result
            : static::$dispatcher->{$method}(
                "eloquent.{$event}: ".static::class, [$this, $validator]
            );
    }

    /**
     * Fire a custom model event that passes the validator instance into the payload.
     *
     * @see Model::fireCustomModelEvent()
     */
    private function fireCustomModelValidationEvent($event, Validator $validator, $method): mixed
    {
        if (! isset($this->dispatchesEvents[$event])) {
            return null;
        }

        $result = static::$dispatcher->$method(new $this->dispatchesEvents[$event]($this, $validator));

        if (! is_null($result)) {
            return $result;
        }

        return null;
    }
}

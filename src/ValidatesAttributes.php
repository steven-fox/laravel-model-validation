<?php

namespace StevenFox\LaravelModelValidation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
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
     * Indicates if attribute validation is enabled for all participating models.
     */
    protected static bool $validateWhenSaving = true;

    /**
     * Validation rules that can be set to override all other rules.
     */
    protected array $supersedingValidationRules;

    /**
     * Validation rules that will be merged with the existing
     * rules defined on the model.
     */
    protected array $mixinValidationRules = [];

    /**
     * The validator instance that was last used to perform validation.
     */
    protected ?Validator $validator = null;

    /**
     * Indicates if the validation listeners have been registered.
     */
    private static bool $validationListenersRegistered = false;

    public static function bootValidatesAttributes(): void
    {
        self::registerValidatingEventListeners();
    }

    protected function initializeValidatesAttributes(): void
    {
        $this->addObservableEvents([
            'validating',
            'validated',
        ]);
    }

    private static function registerValidatingEventListeners(): void
    {
        if (static::$validationListenersRegistered) {
            return;
        }

        foreach (static::validatingListeners() as $event => $listener) {
            static::{$event}($listener);
        }

        static::$validationListenersRegistered = true;
    }

    /**
     * @return array<array-key, class-string|\Illuminate\Events\QueuedClosure|\Closure|array>
     */
    protected static function validatingListeners(): array
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

        static::registerValidatingEventListeners();
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

    /**
     * @param class-string|\Illuminate\Events\QueuedClosure|\Closure($this, Validator): void|array $callback
     */
    public static function validating(mixed $callback): void
    {
        static::registerModelEvent('validating', $callback);
    }

    /**
     * @param class-string|\Illuminate\Events\QueuedClosure|\Closure($this, Validator): void|array $callback
     */
    public static function validated(mixed $callback): void
    {
        static::registerModelEvent('validated', $callback);
    }

    /**
     * Validate the attributes on the model.
     *
     * @return Validator Returns the Validator instance upon success.
     *                   Throws a ModelValidationException if validation fails.
     */
    public function validate(): Validator
    {
        $this->validator = $this->makeValidator();

        $this->fireModelValidationEvent('validating', $this->validator);

        $fails = $this->validator->fails();

        $this->fireModelValidationEvent('validated', $this->validator);

        if ($fails) {
            $this->throwValidationException($this->validator);
        }

        return $this->validator;
    }

    /**
     * Get the validator instance that was last used to perform validation.
     */
    public function validator(): ?Validator
    {
        return $this->validator;
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
        // If the developer has set superseding validation rules for the model,
        // we will use those exclusively.
        if (($tempRules = $this->getSupersedingValidationRules()) !== null) {
            return $tempRules;
        }

        // Otherwise, we will retrieve the normal rules defined on the model.
        $rules = $this->exists
            ? $this->validationRulesForUpdating()
            : $this->validationRulesForCreating();

        return array_merge($rules, $this->getMixinValidationRules());
    }

    public function getSupersedingValidationRules(): ?array
    {
        return $this->supersedingValidationRules ?? null;
    }

    public function setSupersedingValidationRules(array $rules): static
    {
        $this->supersedingValidationRules = $rules;

        return $this;
    }

    public function clearSupersedingValidationRules(): static
    {
        unset($this->supersedingValidationRules);

        return $this;
    }

    public function getMixinValidationRules(): array
    {
        return $this->mixinValidationRules;
    }

    public function setMixinValidationRules(array $rules): static
    {
        $this->mixinValidationRules = $rules;

        return $this;
    }

    public function addMixinValidationRules(array $rules): static
    {
        $this->mixinValidationRules = array_merge($this->mixinValidationRules, $rules);

        return $this;
    }

    public function clearMixinValidationRules(): static
    {
        $this->mixinValidationRules = [];

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

    protected function uniqueRule(): Unique
    {
        $rule = Rule::unique($this->getTable());

        return $this->exists
            ? $rule->ignoreModel($this)
            : $rule;
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

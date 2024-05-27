<?php

namespace StevenFox\LaravelModelValidation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * @var Model $this
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
    protected static bool $validationListenersRegistered = false;

    protected array $temporaryValidationRules = [];

    public static function bootValidatesAttributes(): void
    {
        if (static::shouldValidateWhenSaving()) {
            static::registerEventListeners();
        }
    }

    public static function registerEventListeners(): void
    {
        // We specifically use the 'creating' and 'updating' events
        // over the more general 'saving' event so that we don't
        // redundantly validate a model that is "saved" without
        // any changed attributes (which would NOT fire an
        // 'updating' event, saving us from redundancy).
        static::creating(function (self $model) {
            if (static::shouldValidateWhenSaving()) {
                $model->validate();
            }
        });

        static::updating(function (self $model) {
            if (static::shouldValidateWhenSaving()) {
                $model->validate();
            }
        });

        static::$validationListenersRegistered = true;
    }

    public static function disableValidationWhenSaving(): void
    {
        static::$validateWhenSaving = false;
    }

    public static function reactivateValidationWhenSaving(): void
    {
        static::$validateWhenSaving = true;

        if (! static::$validationListenersRegistered) {
            static::registerEventListeners();
        }
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
     *                         Returns an explicit false if validation fails and you have a validation event listener that returns false upon invocation;
     *                         otherwise, throws a ModelValidationException upon failure.
     */
    public function validate(): Validator|false
    {
        $validator = $this->makeValidator();

        if ($this->fireModelValidationEvent('validating', $validator) === false) {
            return false;
        }

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
        throw new ModelValidationException($this, $validator);
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
     * Determine if the model passes validation, catching any
     * thrown validation exceptions.
     *
     * @return array{bool, Validator|false} A tuple of the validation result and the Validator instance.
     */
    public function passesValidation(): array
    {
        try {
            $validator = $this->validate();

            if ($validator === false) {
                return [false, $validator];
            }

            return [true, $validator];
        } catch (ValidationException $e) {
            return [false, $e->validator];
        }
    }

    /**
     * Determine if the model fails validation, catching any
     * thrown validation exceptions.
     *
     * @return array{bool, Validator} A tuple of the validation result and the Validator instance.
     */
    public function failsValidation(): array
    {
        [$passes, $validator] = $this->passesValidation();

        return [! $passes, $validator];
    }

    /**
     * Fire a model event that passes the validator instance into the payload.
     *
     * @see Model::fireModelEvent()
     */
    protected function fireModelValidationEvent(string $event, Validator $validator, $halt = true): mixed
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

        return ! empty($result) ? $result : static::$dispatcher->{$method}(
            "eloquent.{$event}: ".static::class, [$this, $validator]
        );
    }

    /**
     * Fire a custom model event that passes the validator instance into the payload.
     *
     * @see Model::fireCustomModelEvent()
     */
    protected function fireCustomModelValidationEvent($event, Validator $validator, $method): mixed
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

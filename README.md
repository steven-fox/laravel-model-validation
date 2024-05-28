# Salvation for your model validation.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/steven-fox/laravel-model-validation.svg?style=flat-square)](https://packagist.org/packages/steven-fox/laravel-model-validation)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/steven-fox/laravel-model-validation/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/steven-fox/laravel-model-validation/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/steven-fox/laravel-model-validation/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/steven-fox/laravel-model-validation/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/steven-fox/laravel-model-validation.svg?style=flat-square)](https://packagist.org/packages/steven-fox/laravel-model-validation)

This package makes it easy to add validation helpers to your Eloquent models. Some similar packages already exist, but this package aims to achieve flawless functionality in a customizable, Laravel-esque way.

The purpose of this package is to:
1. Reduce code duplication by providing a single location where the baseline validation configuration is defined for your models.
2. Make it easy to retrieve that configuration to supplement specific validation scenarios (like FormRequests, admin forms/actions, console input, etc.).
3. Provide data integrity for applications by ensuring models adhere to a particular set of rules that is independent of UI.

## Key Features

- Adds several validation methods to your models like `validate()`, `passesValidation()`, and `failsValidation()`.
- Validation can be configured to occur automatically when saving the model (opt-in via an interface and can be turned off globally when needed).
- Validation rules can be configured as a single definition or broken out into independent rules for creating vs. updating.
- Validation rules can be superseded or mixed with custom rules at runtime.
- The data used for validation can be customized and transformed prior to validating.
- Models can define custom validation messages and attribute names.
- The validation configuration (data, rules, messages, names) are accessible via public methods, so incorporating them into validation processes with requests, controllers, Nova, Filament, etc. is easy.
- Model event hooks for `validating` and `validated` are provided, easy to work with, and can be used in your existing model observers.
- Custom validation listeners can be defined for specific model events.
- Helpers are provided to work with `Unique` rules that need to ignore the current model record when updating.
- A custom ValidationException is thrown that includes the model that was validated as a property to assist with debugging, logging, and error messages.

## Installation

You can install the package via composer:

```bash
composer require steven-fox/laravel-model-validation
```

## Usage

### The `ValidatesAttributes` Trait
Add validation functionality to a Model by:
1. Adding the `StevenFox\LaravelModelValidation\ValidatesAttributes` trait to the model.
2. Defining the rules on the model via one or more of the available methods: `baseValidationRules()`, `validationRulesUniqueToCreating()`, `validationRulesUniqueToUpdating()`, `validationRulesForCreating()`, `validationRulesForUpdating()`.

```php
use Illuminate\Database\Eloquent\Model;
use StevenFox\LaravelModelValidation\ValidatesAttributes;

class ValidatingModel extends Model
{
    use ValidatesAttributes;
    
    protected function baseValidationRules(): array
    {
        return [
            // rules go here as ['attribute' => ['rule1', 'rule2', ...]
            // like a normal validation setup
        ];
    }
    
    // Other methods are available for more control over rules... see below.
}

$model = new ValidatingModel($request->json());

$model->validate(); // A ModelValidationException is thrown if validation fails.
$model->save();

// Other helpful methods...
$passes = $model->passesValidation(); // An exception, will *not* be thrown if validation fails.
$fails = $model->failsValidation(); // No exception thrown here, either.
$validator = $model->validator();
```

### The `ValidatesWhenSaving` Interface
You can make a model automatically perform validation when saving by adding the `\StevenFox\LaravelModelValiation\Contracts\ValidatesWhenSaving` interface.
This is an **opt-in** feature. Without implementing this interface on your individual models, you can still perform validation on command; it simply won't be performed during the `save()` process automatically.

```php
use Illuminate\Database\Eloquent\Model;
use StevenFox\LaravelModelValidation\Contracts\ValidatesWhenSaving;
use StevenFox\LaravelModelValidation\ValidatesAttributes;

class ValidatingModel extends Model implements ValidatesWhenSaving
{
    use ValidatesAttributes;
    
    // This model will now validate upon saving.
}
```

#### Validation Listeners
By default, this package will register an event listener for the `creating` and `updating` model events that performs the validation prior to saving the model. You can customize this behavior by overloading the static `validatingListeners()` method on your models. Here is the default implementation that you can adjust to your needs.

```php
protected static function validatingListeners(): array
{
    return [
        'creating' => ValidateModel::class,
        'updating' => ValidateModel::class,
    ];
}
```

> Note: We specifically use the `creating` and `updating` events over the more general `saving` event so that we don't redundantly validate a model that is "saved" without any changed attributes (which does NOT fire an `updating` event, saving us from redundancy).

> Note: Keep in mind that the automatic validation process is implemented with Laravel's model event system. Thus, if you perform a `saveQuietly()` or do something else that disables/halts the model's event chain, you will disable the automatic validation as a consequence.

### More Control Over Validation Rules
You can use the following methods to gain finer control over the validation rules used in particular situations.

```php
use Illuminate\Database\Eloquent\Model;
use StevenFox\LaravelModelValidation\ValidatesAttributes;

class ValidatingModel extends Model
{
    use ValidatesAttributes;
    
    /**
     * Define rules that are common to both creating and updating a model record.
     */
    protected function baseValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // more rules...
        ];
    }
    
    /**
     * Define the rules that are unique to creating a record.
     * These rules will be *combined* with the common validation rules.
     * 
     */
    protected function validationRulesUniqueToCreating(): array
    {
        return ['a_unique_column' => Rule::unique('table')];
    }
    
    /**
     * Define the rules that are unique to updating a record.
     * These rules will be combined with the common validation rules.
     * 
     */
    protected function validationRulesUniqueToUpdating(): array
    {
        return ['a_unique_column' => Rule::unique('table')->ignoreModel($this)];
    }
    
    /**
     * Define the rules that are used when creating a record.
     * If you overload this method on your model, the 'baseValidationRules'
     * will not be used by default. 
     */
    protected function validationRulesForCreating(): array
    {
        // ...
    }
    
    /**
     * Define the rules that are used when updating a record.
     * If you overload this method on your model, the 'baseValidationRules'
     * will not be used by default. 
     */
    protected function validationRulesForUpdating(): array
    {
        // ...
    }
}
```

#### Unique Columns
Specifying an attribute as `unique` is a common validation need. Therefore, this package provides a shortcut that you can use in the `baseValidationRules()` method for your unique columns. The helper function will simply define a `Unique` rule for the attribute, and when the model record already exists in the database, the rule will automatically invoke the `ignoreModel($this)` method.

```php
/**
 * Define rules that are common to both creating and updating a model record.
 */
protected function baseValidationRules(): array
{
    return [
        'email' => [
            'required', 
            'email', 
            'max:255',
            $this->uniqueRule(), // adds a unique rule that handles ignoring the current record on update
        ],
        // more rules...
    ];
}
```

### Runtime Customization for Rules

#### Superseding Rules
You can use the `setSupersedingValidationRules()` method to set temporary rules that will **replace** all other rules defined on the model.
```php
$model = new ValidatingModel();

$customRules = [
    // ...
];

$model->setSupersedingValidationRules($customRules);

$model->validate(); // The validator will **only** use the $customRules for validation.

$model->clearSupersedingValidationRules();
$model->validate(); // The validator will now go back to using the normal rules defined on the model.
```

>Note: You can temporarily disable a specific model instance's validation by setting the `supersedingValidationRules` to an empty array. The validation process will still run, but with no rules to validate against, the model will automatically pass.
> 
```php
$model = new ValidatingModel();

$model->setSupersedingValidationRules([]);

$model->validate(); // Validation will run, but with no rules defined, no actual validation will occur.

$model->clearSupersedingValidationRules();
$model->validate(); // Validation will occur normally.
```
#### Mixin Rules
You can use the `addMixinValidationRules()` and `setMixinValidationRules()` methods to define rules that will be **merged** with the other rules defined on the model. The rules you mixin for a particular attribute will replace any existing rules for that attribute.

For example, suppose your model specifies that a dateTime column must simply be a `date` by default, but for a particular situation, you want to ensure that the attribute's value is a date _after a particular moment_. You can do this by mixing in this custom ruleset for this attribute at runtime.

```php
$model = new ValidatingModel();

// Normally, the ValidatingModel specifies that the 'date_attribute'
// is simply a 'date'.

// However, here we will specify that it must be a date after tomorrow.
$mixinRules = [
    'date_attribute' => ['date', 'after:tomorrow']
];

$model->addMixinValidationRules($mixinRules);

$model->validate(); // The validator will use a *combination* of the mixin rules and the standard rules defined within the model.

$model->clearMixinValidationRules();
$model->validate(); // The validator will now go back to using the normal rules defined on the model.
```

### Validation Data
By default, this package will use the model's `getAttributes()` method as the data to pass to the validator instance. Normally, the array returned from the `getAttributes()` method represents the raw values that will be stored within the database. This means attributes with casting will be mutated into the format used for storage, making the validation logic as seamless as possible. For example, most date attributes on models are cast to `Carbon` instances, but when validating dates, the validator needs to receive the string representation of the date, not a `Carbon` instance.

If you need to customize the attributes used as data for validation, you can do so in two ways:
1. Overload the `rawAttributesForValidation()` method and return what you need.
2. Overload the `prepareAttributesForValidation($attributes)` method to transform the default attribute values into a validation-ready state.

### Globally Disabling Validation When Saving
It is possible to disable the automatic validation during the save process for models that implement the `ValidatesOnSave` interface. This can be helpful when setting up a particular test, for example.

#### Option 1
Call the static `disableValidationWhenSaving()` on a validating model class. This will disable validation until you explicitly activate it again. This is similar to the `Model::unguard()` concept, and like unguarding, you would likely do the disabling of validation in the `boot` method of a `ServiceProvider`.
```php
// Perhaps in a ServiceProvider...

public function boot(): void
{
    ValidatingModel::disableValidationWhenSaving();
    
    // All models that validate their attributes and implement 
    // the ValidatesWhenSaving interface will no longer perform
    // that automatic validation during the saving process.
}
```

#### Option 2
Call the static `whileValidationDisables()` method, passing in a callback that executes the logic you would like to perform while automatic validation is globally disabled. This is similar to the `Model::unguarded($callback)` concept.
```php
ValidatingModel::whileValidationDisabled(function () {
    // do something while automatic validation is globally disabled...
});
```

### Validation Model Events
This package adds `validating` and `validated` model events. It also registers these as "observable events", which means you can listen for them within your model observer classes, like you would for `saving`, `deleting`, etc.

When implementing a listener for this event, the model record emitting the event and the related validator instance will be supplied to the callback.

```php
\Illuminate\Support\Facades\Event::listen(
    'eloquent.validating*',
    function (Model $model, Validator $validator) {
        // Do something when any model is "validating".
        // $model will be an instance of Model and ValidatesAttributes.
    }
);
```

Similar to the other observable model events, this package provides static `validating($callback)` and `validated($callback)` methods that you can use to register listeners for these events.
```php
ValidatingModel::validating(function (ValidatingModel $model, Validator $validator) {
    // ...
})

ValidatingModel::validated(function (ValidatingModel $model, Validator $validator) {
    // ...
})
```

### The Validator Instance
You can access the Validator instance that was last used to perform the `validate()` process with the `validator()` method.
```php
$model = new ValidatingModel($request->json());

$validator = $model->validate();

// Or...

$model->validate();
$validator = $model->validator();
```

> Note: A new validator instance is instantiated and stored on the model instance each time the `validate()` method is invoked.

```php
$model = new ValidatingModel(...);

$validator1 = $model->validate();

$validator2 = $model->validate();

// $validator1 is *not* a reference to the same object as $validator2.
```

#### Customizing the Validator
You can customize the validator instance with the `beforeMakingValidator()` and `afterMakingValidator($validator)` methods on a model.

> Note: The `afterMakingValidator()` method can be a great place to specify `after` hooks for your validation process.

You can pass custom validation messages and custom attribute names to the validator via the `customValidationMessages()` and `customValidationAttributeNames()` methods respectively.

```php
class ValidatingModel extends Model
{
    use ValidatesAttributes;
    
    public function customValidationMessages(): array
    {
        return ['email.required' => 'We need to know your email address.']
    }
    
    public function customValidationAttributeNames(): array
    {
        return ['ip_v4' => 'ip address'];
    }
}
```

### Validation Exception
When the `validate()` method is invoked and validation fails, a `ModelValidationException` is thrown by default. This exception extends Laravel's `ValidationException`, but stores a reference to the model record that failed validation to make debugging or error messages easier to handle.

You can provide your own validation exception by overloading the `validationExceptionClass()` or `throwValidationException($validator)` methods.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Steven Fox](https://github.com/steven-fox)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

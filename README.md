# Laravel Model Resource Generator

`model-resource-generator` is a custom Laravel command that generates API resource classes for all available models in your Laravel application. It includes the existing columns and relations of each model in the generated resource classes.

It respects your model's hidden attributes and does not load any relations automatically. It adjusts the API attributes based on whether relations are loaded or not.

## Installation

You can install the package via composer:

```shell
composer require naranarethiya/model-resource-generator
```

## Usage
To generate API resources for models located in the app/Models directory:

```shell
php artisan generate:api-resources
```

To specify a different directory or a single model file:

```shell
php artisan generate:api-resources --model-path=app/CustomModels
```

### Options
`--model-path` (optional): Specify the directory path to search for models or a single model file path. If not provided, it defaults to **app/Models**

## Features
- Automatically generates API resource classes for each model found in the specified directory.
- Includes existing columns and relations in the generated resource classes.
- Exclude all hidden attributes
- Provides an option to overwrite existing resources or skip them.
- Outputs a summary of the generated, skipped, and overwritten resources.

## Contributing

Contributions are welcome! Please submit a pull request or open an issue to suggest changes or report problems.

## License
This package is open-source software licensed under the MIT license.

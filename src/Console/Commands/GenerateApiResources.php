<?php

namespace Naranarethiya\ModelResourceGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

class GenerateApiResources extends Command
{
    protected $signature = '
        generate:api-resources
        {--model-path= : Specify the directory path to search for models or a single model file path. Default is app/Models.}
    ';

    /**
     * Default Directory path to search for models
     */
    private string $defaultModelPath;

    /**
     * Overwite the already genrated resources
     */
    private bool $overwrite = true;

    /**
     * Directory path to search for models or a single model file path
     */
    private string $modelPath;

    private int $newCount = 0;

    private int $overwritCount = 0;

    private int $skipCount = 0;

    private int $totalCount = 0;

    /**
     * @var array<int, string>
     */
    private array $errors = [];

    protected $description = 'Generate API Resources for each model with existing columns and relations';

    public function __construct()
    {
        parent::__construct();

        $this->defaultModelPath = app_path('Models');
        $this->modelPath = $this->defaultModelPath;
    }

    public function handle(): void
    {
        $this->overwrite = (bool) $this->ask('Overwrite existing resources?, Type 1 to overwrite or 0 to skip', '1');
        $this->checkResourceDir();
        $models = $this->getModelList();

        foreach ($models as $model) {
            $this->generateResource($model);
        }

        $this->printSummary();
    }

    private function checkResourceDir(): void
    {
        // create Resources dir if not present
        $resourceDir = app_path('Http/Resources');
        if (! File::exists($resourceDir)) {
            File::makeDirectory($resourceDir, 0755, true);
        }
    }

    private function printSummary(): void
    {
        // show errors
        foreach ($this->errors as $error) {
            $this->error($error);
        }

        $this->info('................');
        $this->table(
            ['Type', 'Count'],
            [
                ['New Resources', $this->newCount],
                ['Skipped Resources', $this->skipCount],
                ['Overwritten Resources', $this->overwritCount],
                ['Total Resources', $this->totalCount],
            ]
        );
    }

    /**
     * @return array<int, string|SplFileInfo>
     */
    private function getModelList(): array
    {
        $optionModelPath = $this->option('model-path');
        if (! empty($optionModelPath)) {
            /** @phpstan-ignore-next-line */
            $this->modelPath = $optionModelPath;
        }

        if (File::isDirectory($this->modelPath)) {
            $models = File::files($this->modelPath);
            $this->totalCount = count($models);
        } else {
            $models = [$this->modelPath];
            $this->totalCount = 1;
        }

        return $models;
    }

    protected function generateResource(string|SplFileInfo $modelPath): void
    {
        $modelDetail = $this->getClassNameFromFile(File::get($modelPath));
        $modelClass = $modelDetail['namespace'].'\\'.$modelDetail['class'];
        $resourceClass = $modelDetail['class'].'Resource';
        $resourcePath = app_path("Http/Resources/{$resourceClass}.php");

        $this->newCount++;
        if (File::exists($resourcePath)) {
            $this->newCount--;

            if (! $this->overwrite) {
                $this->skipCount++;
                $this->warn('Existing  resource for model: '.$modelClass);

                return;
            }

            $this->overwritCount++;
        }

        if (class_exists($modelClass)) {
            $modelInstance = new $modelClass;
            if (! $modelInstance instanceof Model) {
                $this->warn($modelClass.' is not a valid model class.');

                return;
            }

            $columns = Schema::getColumnListing($modelInstance->getTable());
            $hiddenColumns = $modelInstance->getHidden();
            $visibleColumns = array_diff($columns, $hiddenColumns);
            $relations = $this->getModelRelations($modelInstance);

            $resourceContent = $this->generateResourceContent($resourceClass, $visibleColumns, $relations);
            File::put($resourcePath, $resourceContent);

            $this->info("Generated resource for model: {$modelClass}");
        }
    }

    /**
     * Summary of getModelRelations
     *
     * @return array<string, Relation>
     */
    protected function getModelRelations(object $modelInstance): array
    {
        $relations = [];

        foreach ((new \ReflectionClass($modelInstance))->getMethods() as $method) {
            if ($method->class == get_class($modelInstance) && empty($method->getParameters()) && $method->isPublic()) {

                try {
                    $return = $method->invoke($modelInstance);
                    if ($return instanceof Relation) {
                        $relations[$method->name] = $return;
                    }
                } catch (\Throwable $e) {
                    $this->errors[] = "Error while executing method {$method->class}::{$method->name}".PHP_EOL."{$e->getMessage()} in {$e->getFile()} at ".$e->getLine();
                }
            }
        }

        return $relations;
    }

    /**
     * @param  array<string, string>  $columns
     * @param  array<string, Relation>  $relations
     */
    protected function generateResourceContent(string $resourceClass, array $columns, array $relations): string
    {
        $resourceTemplate = File::get(__DIR__.'/../../../stubs/api-resource.stub');

        $columnsArray = collect($columns)
            ->map(fn ($column) => "'$column' => \$this->$column,")
            ->implode("\n            ");

        // Prepare the relations array string
        $relationsArray = collect($relations)
            ->map(function (Relation $relation, string $method) {
                $attribute = Str::snake($method);

                $collectionRelations = [
                    HasMany::class,
                    HasManyThrough::class,
                    BelongsToMany::class,
                    MorphMany::class,
                    MorphToMany::class,
                ];

                $model = class_basename($relation->getRelated()::class);
                if (in_array($relation::class, $collectionRelations)) {
                    return "'$attribute' => {$model}Resource::collection(\$this->whenLoaded('$method')),";
                }

                return "'$attribute' => \$this->whenLoaded('$method'),";
            })
            ->implode("\n            ");

        // Replace placeholders in the template
        return str_replace(
            ['{{resourceClass}}', '{{columns}}', '{{relations}}'],
            [$resourceClass, $columnsArray, $relationsArray],
            $resourceTemplate
        );
    }

    /**
     * @return array<string, string>
     */
    public function getClassNameFromFile(string $fileContent): array
    {
        $tokens = token_get_all($fileContent);

        $namespace = '';
        $namespaceFound = false;
        $className = '';
        $classFound = false;

        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespaceFound = true;
                $namespace = '';
            } elseif ($namespaceFound && $tokens[$i][0] === T_NAME_QUALIFIED) {
                $namespace .= $tokens[$i][1];
                $namespaceFound = false;
            } elseif ($tokens[$i][0] === T_CLASS) {
                $classFound = true;
                $className = '';
            } elseif ($classFound && $tokens[$i][0] === T_STRING) {
                $className = $tokens[$i][1];
                break;
            }
        }

        return [
            'namespace' => $namespace,
            'class' => $className,
        ];
    }
}

<?php
declare(strict_types=1);

namespace Core\Docs;

use Core\App;
use Core\Docs\Attribute\Api;
use Core\Docs\Attribute\Docs;
use Core\Docs\Attribute\Header;
use Core\Docs\Attribute\Params;
use Core\Docs\Attribute\Payload;
use Core\Docs\Attribute\Query;
use Core\Docs\Attribute\Result;
use Core\Docs\Attribute\ResultData;
use Core\Docs\Attribute\ResultMeta;
use Core\Docs\Attribute\ResultMessage;
use Core\Docs\Attribute\ResultStatus;
use Core\Docs\Enum\FieldEnum;
use Core\Docs\Enum\PayloadTypeEnum;
use Core\Docs\Enum\ResultMimeEnum;
use Core\Docs\Enum\ResultTypeEnum;
use Core\Route\Attribute\Route;
use Core\Route\Attribute\RouteGroup;
use Nette\Utils\FileSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DocsCommand extends Command
{
    private array $openApiDoc;
    private const PARAMETER_CONFIGS = [
        ['class' => Query::class, 'in' => 'query'],
        ['class' => Params::class, 'in' => 'path'],
        ['class' => Header::class, 'in' => 'header']
    ];

    public function __construct()
    {
        parent::__construct();
        $this->initializeOpenApiDoc();
    }

    protected function configure(): void
    {
        $this->setName("docs:build")
            ->setDescription('Build OpenAPI documentation from annotations')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'API host', 'localhost')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'API port', '8080')
            ->addOption('ver', null, InputOption::VALUE_OPTIONAL, 'API version', null);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputFile = data_path("docs/openapi.json");
        $this->setupServers($input);
        $this->setupVersion($input, $outputFile);

        $output->writeln('Starting OpenAPI documentation generation...');

        $attributes = App::attributes();
        $output->writeln('Found ' . count($attributes) . ' classes with annotations');

        $this->parseAnnotations($attributes);

        $pathCount = count($this->openApiDoc['paths']);
        $output->writeln("Generated {$pathCount} API paths");

        $this->saveDocument($outputFile);
        $output->writeln("OpenAPI documentation saved to: {$outputFile}");

        return Command::SUCCESS;
    }

    private function initializeOpenApiDoc(): void
    {
        $this->openApiDoc = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => __('docs.title', 'common'),
                'version' => '1.0.0',
                'description' => __('docs.description', 'common')
            ],
            'servers' => [],
            'paths' => [],
            'components' => [
                'schemas' => new \stdClass(),
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    ]
                ]
            ],
            'tags' => []
        ];
    }

    private function setupServers(InputInterface $input): void
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $this->openApiDoc['servers'] = [
            ['url' => "http://{$host}:{$port}", 'description' => __('docs.server_description', 'common')]
        ];
    }

    private function setupVersion(InputInterface $input, string $outputFile): void
    {
        $version = $input->getOption('ver');

        if ($version) {
            $this->openApiDoc['info']['version'] = $version;
        } else {
            $this->openApiDoc['info']['version'] = $this->getNextVersion($outputFile);
        }
    }

    private function getNextVersion(string $outputFile): string
    {
        if (!file_exists($outputFile)) {
            return '1.0.0';
        }

        try {
            $content = file_get_contents($outputFile);
            $data = json_decode($content, true);

            if (!isset($data['info']['version'])) {
                return '1.0.0';
            }

            $currentVersion = $data['info']['version'];
            $versionParts = explode('.', $currentVersion);

            if (count($versionParts) >= 3) {
                $versionParts[2] = (int)$versionParts[2] + 1;
                return implode('.', $versionParts);
            }

            return '1.0.0';
        } catch (\Exception $e) {
            return '1.0.0';
        }
    }

    private function saveDocument(string $outputFile): void
    {
        $jsonContent = json_encode($this->openApiDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        FileSystem::write($outputFile, $jsonContent);
    }

    private function parseAnnotations(array $attributes): void
    {
        [$groups, $routes] = $this->collectGroupsAndRoutes($attributes);
        $this->processApiAnnotations($attributes, $groups, $routes);
    }

    private function collectGroupsAndRoutes(array $attributes): array
    {
        $groups = [];
        $routes = [];

        foreach ($attributes as $item) {
            foreach ($item['annotations'] as $annotation) {
                $this->processAnnotation($annotation, $item, $groups, $routes);
            }
        }

        return [$groups, $routes];
    }

    private function processAnnotation(array $annotation, array $item, array &$groups, array &$routes): void
    {
        $name = $annotation['name'];
        $hasMethod = isset($annotation['method']);

        match (true) {
            $name === Docs::class && !$hasMethod => $this->setDocsGroup($groups, $item['class'], $annotation['params']),
            $name === Route::class && $hasMethod => $routes[$annotation['class']] = $annotation['params'],
            $name === RouteGroup::class && !$hasMethod => $this->setRouteGroup($groups, $item['class'], $annotation['params']),
            default => null
        };
    }

    private function setDocsGroup(array &$groups, string $className, array $params): void
    {
        if (!isset($groups[$className])) {
            $groups[$className] = [];
        }
        $groups[$className] = array_merge($groups[$className], $params);
    }

    private function setRouteGroup(array &$groups, string $className, array $params): void
    {
        if (!isset($groups[$className])) {
            $groups[$className] = [];
        }
        $groups[$className]['routeGroup'] = $params;
    }

    private function processApiAnnotations(array $attributes, array $groups, array $routes): void
    {
        foreach ($attributes as $item) {
            $className = $item['class'];

            if (!isset($groups[$className])) {
                continue;
            }

            foreach ($item['annotations'] as $annotation) {
                if ($annotation['name'] === Api::class && isset($annotation['method'])) {
                    $this->generateApiDoc($annotation, $item, $groups, $routes);
                }
            }
        }
    }

    private function generateApiDoc(array $apiAnnotation, array $item, array $groups, array $routes): void
    {
        $routeKey = $apiAnnotation['class'];
        if (!isset($routes[$routeKey])) return;

        $className = explode(':', $routeKey)[0];
        $apiParams = $apiAnnotation['params'];
        $routeParams = $routes[$routeKey];
        $groupInfo = $groups[$className] ?? null;
        $groupName = $groupInfo['name'] ?? 'Default';

        $path = $this->buildPath($routeParams, $groupInfo);
        $this->addTag($groupName, $groupInfo);

        foreach ($this->getMethods($routeParams) as $method) {
            $this->generateOperation($path, $method, $apiParams, $item, $groupName, $routeKey);
        }
    }

        private function buildPath(array $routeParams, ?array $groupInfo): string
    {
                $path = $routeParams['route'] ?? $routeParams[1] ?? '';

        $appPrefix = $this->getAppPrefix($groupInfo);

        if ($groupRoute = $groupInfo['routeGroup']['route'] ?? null) {
            $path = rtrim($groupRoute, '/') . '/' . ltrim($path, '/');
        }

        if ($appPrefix) {
            $path = rtrim($appPrefix, '/') . '/' . ltrim($path, '/');
        }

        $path = preg_replace('/\{([^:}]+):[^}]+\}/', '{$1}', $path);

        return '/' . ltrim($path, '/');
    }

    private function getAppPrefix(?array $groupInfo): ?string
    {
        if (!$groupInfo || !isset($groupInfo['routeGroup']['app'])) {
            return null;
        }

        $appName = $groupInfo['routeGroup']['app'];

        try {
            $routeApp = App::route()->get($appName);
            return $routeApp->pattern;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getMethods(array $routeParams): array
    {
        $methods = $routeParams['methods'] ?? $routeParams[0] ?? ['GET'];
        return is_array($methods) ? $methods : [$methods];
    }

    private function addTag(string $groupName, ?array $groupInfo): void
    {
        if (!in_array($groupName, array_column($this->openApiDoc['tags'], 'name'))) {
            $this->openApiDoc['tags'][] = [
                'name' => $groupName,
                'description' => $groupInfo['desc']
            ];
        }
    }

    private function generateOperation(string $path, string $method, array $apiParams, array $item, string $groupName, string $routeKey): void
    {
        $operation = [
            'tags' => [$groupName],
            'summary' => $apiParams['name'] ?? '',
            'description' => $apiParams['desc'] ?? '',
            'operationId' => $this->generateOperationId($path, $method, $routeKey),
            'parameters' => $this->buildParameters($item, $routeKey),
            'responses' => $this->buildResponses($item, $apiParams, $routeKey)
        ];

        if ($requestBody = $this->buildRequestBody($item, $apiParams, $routeKey)) {
            $operation['requestBody'] = $requestBody;
        }

        $this->openApiDoc['paths'][$path][strtolower($method)] = $operation;
    }

    private function buildParameters(array $item, string $routeKey): array
    {
        $parameters = [];

        foreach (self::PARAMETER_CONFIGS as $config) {
            $parameters = array_merge($parameters, $this->buildParametersByType($item, $routeKey, $config));
        }

        return $parameters;
    }

    private function buildParametersByType(array $item, string $routeKey, array $config): array
    {
        $parameters = [];

        foreach ($item['annotations'] as $annotation) {
            if ($annotation['name'] === $config['class'] && $annotation['class'] === $routeKey) {
                $params = $annotation['params'];
                $parameters[] = [
                    'name' => $params['field'],
                    'in' => $config['in'],
                    'summary' => $params['name'],
                    'description' => $params['desc'] ?: $params['name'],
                    'required' => $params['required'] ?? ($config['in'] === 'path'),
                    'schema' => ['type' => $params['type']->value],
                    'example' => $params['example'] ?? null
                ];
            }
        }

        return $parameters;
    }

    private function buildRequestBody(array $item, array $apiParams, string $routeKey): ?array
    {
        $payloadAnnotations = $this->filterAnnotations($item, Payload::class, $routeKey);
        if (empty($payloadAnnotations)) return null;

        $schema = $this->buildSchema($payloadAnnotations);
        if (isset($apiParams['payloadExample'])) {
            $schema['example'] = $apiParams['payloadExample'];
        }

        $payloadType = $apiParams['payloadType'] ?? PayloadTypeEnum::JSON;
        return [
            'required' => true,
            'content' => [$payloadType->mime() => ['schema' => $schema]]
        ];
    }

    private function buildResponses(array $item, array $apiParams, string $routeKey): array
    {
        $resultType = $apiParams['resultType'] ?? ResultTypeEnum::MESSAGE;
        $resultMime = $apiParams['resultMime'] ?? ResultMimeEnum::JSON;
        $contentType = is_string($resultMime) ? $resultMime : $resultMime->value;

        $responses = [
            '200' => [
                'description' => __('docs.success', 'common'),
                'content' => [
                    $contentType => [
                        'schema' => $this->buildResponseSchema($item, $routeKey, $resultType, $apiParams)
                    ]
                ]
            ],
            '500' => $this->buildErrorResponse($resultType, $contentType)
        ];

        $this->addCustomStatusResponses($responses, $item, $routeKey, $contentType, $apiParams);
        return $responses;
    }

    private function buildErrorResponse(ResultTypeEnum $resultType, string $contentType): array
    {
        $description = __('docs.internal_server_error', 'common');

        $schema = $resultType === ResultTypeEnum::MESSAGE
            ? [
                'type' => 'object',
                'properties' => [
                    'code' => [
                        'type' => 'integer',
                        'description' => __('docs.http_status_code', 'common'),
                        'example' => 500
                    ],
                    'message' => [
                        'type' => 'string',
                        'description' => __('docs.error_reason', 'common'),
                        'example' => __('docs.error_message', 'common')
                    ]
                ],
                'required' => ['code', 'message']
            ]
            : [
                'type' => 'object',
                'properties' => [
                    'error' => [
                        'type' => 'string',
                        'example' => __('docs.internal_server_error', 'common')
                    ]
                ]
            ];

        return [
            'description' => $description,
            'content' => [$contentType => ['schema' => $schema]]
        ];
    }

    private function buildResponseSchema(array $item, string $routeKey, ResultTypeEnum $resultType, array $apiParams = []): array
    {
        return $resultType === ResultTypeEnum::MESSAGE
            ? $this->buildMessageResponseSchema($item, $routeKey, $apiParams)
            : $this->buildDefaultResponseSchema($item, $routeKey, $apiParams);
    }

    private function buildDefaultResponseSchema(array $item, string $routeKey, array $apiParams = []): array
    {
        $resultAnnotations = $this->filterAnnotations($item, Result::class, $routeKey);
        $schema = empty($resultAnnotations) ? ['type' => 'object'] : $this->buildSchema($resultAnnotations);

        if (isset($apiParams['resultExample'])) {
            $schema['example'] = $apiParams['resultExample'];
        }

        return $schema;
    }

    private function buildMessageResponseSchema(array $item, string $routeKey, array $apiParams = []): array
    {
        $schema = $this->getMessageBaseSchema();

        $this->enhanceMessageSchema($schema, $item, $routeKey);
        $this->enhanceSchemaFields($schema, $item, $routeKey);

        if (isset($apiParams['resultExample'])) {
            $schema['example'] = $apiParams['resultExample'];
        }

        return $schema;
    }

    private function getMessageBaseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'code' => [
                    'type' => 'integer',
                    'description' => __('docs.http_status_code', 'common'),
                    'example' => 200
                ],
                'message' => [
                    'type' => 'string',
                    'description' => __('docs.response_message', 'common'),
                    'example' => 'ok'
                ],
                'data' => [
                    'type' => 'object',
                    'description' => __('docs.response_data', 'common')
                ],
                'meta' => [
                    'type' => 'object',
                    'description' => __('docs.metadata', 'common')
                ]
            ],
            'required' => ['code', 'message']
        ];
    }

    private function enhanceMessageSchema(array &$schema, array $item, string $routeKey): void
    {
        $messageAnnotations = $this->filterAnnotations($item, ResultMessage::class, $routeKey);
        if (!empty($messageAnnotations)) {
            $params = reset($messageAnnotations)['params'];
            $schema['properties']['message']['description'] = $params['desc'] ?: $params['name'];
            if (isset($params['example'])) {
                $schema['properties']['message']['example'] = $params['example'];
            }
        }
    }

    private function enhanceSchemaFields(array &$schema, array $item, string $routeKey): void
    {
        $this->enhanceDataSchema($schema, $item, $routeKey);
        $this->enhanceMetaSchema($schema, $item, $routeKey);
    }

    private function enhanceDataSchema(array &$schema, array $item, string $routeKey): void
    {
        $dataAnnotations = $this->filterAnnotations($item, ResultData::class, $routeKey);
        if (!empty($dataAnnotations)) {
            $this->buildDataSchemaWithRoot($schema, $dataAnnotations);
        } else {
            $resultAnnotations = $this->filterAnnotations($item, Result::class, $routeKey);
            if (!empty($resultAnnotations)) {
                $schema['properties']['data'] = $this->buildSchema($resultAnnotations);
            }
        }
    }

    private function buildDataSchemaWithRoot(array &$schema, array $dataAnnotations): void
    {
        $normalAnnotations = [];

        foreach ($dataAnnotations as $annotation) {
            $params = $annotation['params'];
            if ($params['root'] ?? false) {
                if ($params['field'] === 'data') {
                    $schema['properties']['data'] = $this->buildFieldSchema($params);
                } else {
                    $schema['properties'][$params['field']] = $this->buildFieldSchema($params);
                }
            } else {
                $normalAnnotations[] = $annotation;
            }
        }

        if (!empty($normalAnnotations)) {
            if (!isset($schema['properties']['data'])) {
                $schema['properties']['data'] = $this->buildSchema($normalAnnotations);
            } else {
                $normalSchema = $this->buildSchema($normalAnnotations);
                if (isset($normalSchema['properties'])) {
                    $schema['properties']['data']['properties'] = array_merge(
                        $schema['properties']['data']['properties'] ?? [],
                        $normalSchema['properties']
                    );

                    if (isset($normalSchema['required'])) {
                        $schema['properties']['data']['required'] = array_unique(
                            array_merge($schema['properties']['data']['required'] ?? [], $normalSchema['required'])
                        );
                    }
                }
            }
        }
    }

    private function enhanceMetaSchema(array &$schema, array $item, string $routeKey): void
    {
        $metaAnnotations = $this->filterAnnotations($item, ResultMeta::class, $routeKey);
        if (!empty($metaAnnotations)) {
            $schema['properties']['meta'] = $this->buildSchema($metaAnnotations);
        }
    }

    private function addCustomStatusResponses(array &$responses, array $item, string $routeKey, string $contentType, array $apiParams = []): void
    {
        $statusAnnotations = $this->filterAnnotations($item, ResultStatus::class, $routeKey);
        $resultType = $apiParams['resultType'] ?? ResultTypeEnum::MESSAGE;

        foreach ($statusAnnotations as $statusAnnotation) {
            $params = $statusAnnotation['params'];

            $schema = $resultType === ResultTypeEnum::MESSAGE
                ? $this->buildCustomStatusMessageSchema($item, $routeKey, $params)
                : [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'integer', 'example' => $params['code']],
                        'message' => ['type' => 'string', 'example' => $params['example'] ?: $params['name']]
                    ]
                ];

            $responses[(string)$params['code']] = [
                'description' => $params['desc'] ?: $params['name'],
                'content' => [$contentType => ['schema' => $schema]]
            ];
        }
    }

    private function buildCustomStatusMessageSchema(array $item, string $routeKey, array $statusParams): array
    {
        $schema = $this->getMessageBaseSchema();
        $schema['properties']['code']['example'] = $statusParams['code'];

        if (isset($statusParams['example'])) {
            if (is_array($statusParams['example']) &&
                isset($statusParams['example']['code'], $statusParams['example']['message'])) {
                $schema['example'] = $statusParams['example'];
            } else {
                $schema['properties']['message']['example'] = $statusParams['example'];
            }
        } else {
            $schema['properties']['message']['example'] = $statusParams['name'];
        }

        $this->enhanceSchemaFields($schema, $item, $routeKey);
        return $schema;
    }

    private function buildSchema(array $annotations): array
    {
        $schema = ['type' => 'object', 'properties' => []];
        $required = [];

        foreach ($annotations as $annotation) {
            $params = $annotation['params'];
            $schema['properties'][$params['field']] = $this->buildFieldSchema($params);
            if ($params['required'] ?? false) {
                $required[] = $params['field'];
            }
        }

        if (!empty($required)) {
            $schema['required'] = $required;
        }
        return $schema;
    }

    private function buildFieldSchema(array $params): array
    {
        $schema = [
            'type' => $params['type']->value,
            'title' => $params['name'],
            'description' => $params['desc'] ?: $params['name']
        ];

        if (isset($params['example'])) {
            $schema['example'] = $params['example'];
        }

        return !empty($params['children']) ? $this->addChildrenToSchema($schema, $params) : $schema;
    }

    private function addChildrenToSchema(array $schema, array $params): array
    {
        $childrenProperties = $this->buildChildrenProperties($params['children']);
        $requiredFields = $this->getRequiredChildrenFields($params['children']);

        if ($params['type'] === FieldEnum::ARRAY) {
            $itemSchema = ['type' => 'object', 'properties' => $childrenProperties];
            if (!empty($requiredFields)) {
                $itemSchema['required'] = $requiredFields;
            }
            $schema['items'] = $itemSchema;
        } elseif ($params['type'] === FieldEnum::OBJECT) {
            $schema['properties'] = $childrenProperties;
            if (!empty($requiredFields)) {
                $schema['required'] = $requiredFields;
            }
        } elseif (!empty($childrenProperties)) {
            $schema['type'] = 'object';
            $schema['properties'] = $childrenProperties;
            if (!empty($requiredFields)) {
                $schema['required'] = $requiredFields;
            }
        }

        return $schema;
    }

    private function buildChildrenProperties(array $children): array
    {
        $properties = [];
        foreach ($children as $child) {
            $childData = $this->extractChildData($child);
            if ($childData && !empty($childData['field'])) {
                $properties[$childData['field']] = $this->buildFieldSchema($childData);
            }
        }
        return $properties;
    }

    private function getRequiredChildrenFields(array $children): array
    {
        $required = [];
        foreach ($children as $child) {
            $childData = $this->extractChildData($child);
            if ($childData && !empty($childData['field']) && ($childData['required'] ?? false)) {
                $required[] = $childData['field'];
            }
        }
        return $required;
    }

    private function extractChildData($child): ?array
    {
        if (is_object($child) && method_exists($child, 'getField')) {
            return $child->getField();
        }

        if (is_array($child)) {
            return $child;
        }

        if (is_object($child)) {
            return [
                'field' => $child->field ?? '',
                'type' => $child->type ?? FieldEnum::STRING,
                'name' => $child->name ?? '',
                'required' => $child->required ?? false,
                'desc' => $child->desc ?? '',
                'example' => $child->example ?? null,
                'children' => $child->children ?? [],
                'root' => $child->root ?? false,
            ];
        }

        return null;
    }

    private function filterAnnotations(array $item, string $className, string $routeKey): array
    {
        return array_filter($item['annotations'],
            fn($a) => $a['name'] === $className && $a['class'] === $routeKey
        );
    }

    private function generateOperationId(string $path, string $method, string $routeKey): string
    {
        [$class, $methodName] = explode(':', $routeKey);
        $className = basename(str_replace('\\', '/', $class));
        return strtolower($method) . $className . ucfirst($methodName);
    }
}
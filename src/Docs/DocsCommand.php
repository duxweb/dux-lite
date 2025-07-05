<?php
declare(strict_types=1);

namespace Core\Docs;

use Core\App;
use Core\Docs\Attribute\Api;
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
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'API port', '8080');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputFile = data_path("docs/openapi.json");
        $this->setupServers($input);

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
                'title' => 'API Documentation',
                'version' => '1.0.0',
                'description' => 'Generated API documentation from annotations'
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
            ['url' => "http://{$host}:{$port}", 'description' => 'Development server']
        ];
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
            $name === Api::class && !$hasMethod => $this->setApiGroup($groups, $item['class'], $annotation['params']),
            $name === Route::class && $hasMethod => $routes[$annotation['class']] = $annotation['params'],
            $name === RouteGroup::class && !$hasMethod => $this->setRouteGroup($groups, $item['class'], $annotation['params']),
            default => null
        };
    }

    private function setApiGroup(array &$groups, string $className, array $params): void
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

        $path = $this->buildPath($routeParams, $groupInfo);
        $methods = $this->getMethods($routeParams);
        $groupName = $groupInfo['name'] ?? 'Default';

        $this->addTag($groupName, $groupInfo);

        foreach ($methods as $method) {
            $this->generateOperation($path, $method, $apiParams, $item, $groupName, $routeKey);
        }
    }

    private function buildPath(array $routeParams, ?array $groupInfo): string
    {
        $path = $routeParams['route'] ?? $routeParams[1] ?? '';

        if ($groupInfo && isset($groupInfo['routeGroup'])) {
            $routeGroupParams = $groupInfo['routeGroup'];
            $groupRoute = $routeGroupParams['route'] ?? '';

            if ($groupRoute) {
                $groupRoute = rtrim($groupRoute, '/');
                $path = ltrim($path, '/');
                $path = $groupRoute . '/' . $path;
            }
        }

        $path = preg_replace('/\{([^:}]+):[^}]+\}/', '{$1}', $path);

        return '/' . ltrim($path, '/');
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
                'description' => $groupInfo['name'] ?? $groupName
            ];
        }
    }

    private function generateOperation(string $path, string $method, array $apiParams, array $item, string $groupName, string $routeKey): void
    {
        $operation = [
            'tags' => [$groupName],
            'summary' => $apiParams['name'] ?? '',
            'operationId' => $this->generateOperationId($path, $method, $routeKey),
            'parameters' => $this->buildParameters($item, $routeKey),
            'responses' => $this->buildResponses($item, $apiParams, $routeKey)
        ];

        if ($apiParams['auth'] ?? true) {
            $operation['security'] = [['bearerAuth' => []]];
        }

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

        $payloadType = $apiParams['payloadType'] ?? PayloadTypeEnum::JSON;
        return [
            'required' => true,
            'content' => [
                $payloadType->mime() => ['schema' => $this->buildSchema($payloadAnnotations)]
            ]
        ];
    }

    private function buildResponses(array $item, array $apiParams, string $routeKey): array
    {
        $resultType = $apiParams['resultType'] ?? ResultTypeEnum::MESSAGE;
        $resultMime = $apiParams['resultMime'] ?? ResultMimeEnum::JSON;
        $contentType = is_string($resultMime) ? $resultMime : $resultMime->value;

        $responses = [
            '200' => [
                'description' => 'Success',
                'content' => [
                    $contentType => [
                        'schema' => $this->buildResponseSchema($item, $routeKey, $resultType)
                    ]
                ]
            ],
            '400' => ['description' => 'Bad Request'],
            '500' => ['description' => 'Internal Server Error']
        ];

        $this->addCustomStatusResponses($responses, $item, $routeKey, $contentType);
        return $responses;
    }

    private function buildResponseSchema(array $item, string $routeKey, ResultTypeEnum $resultType): array
    {
        return $resultType === ResultTypeEnum::MESSAGE
            ? $this->buildMessageResponseSchema($item, $routeKey)
            : $this->buildDefaultResponseSchema($item, $routeKey);
    }

    private function buildDefaultResponseSchema(array $item, string $routeKey): array
    {
        $resultAnnotations = $this->filterAnnotations($item, Result::class, $routeKey);
        return empty($resultAnnotations) ? ['type' => 'object'] : $this->buildSchema($resultAnnotations);
    }

    private function buildMessageResponseSchema(array $item, string $routeKey): array
    {
        $schema = $this->getMessageBaseSchema();

        $this->enhanceMessageSchema($schema, $item, $routeKey);
        $this->enhanceDataSchema($schema, $item, $routeKey);
        $this->enhanceMetaSchema($schema, $item, $routeKey);

        return $schema;
    }

    private function getMessageBaseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'code' => [
                    'type' => 'integer',
                    'description' => 'HTTP状态码',
                    'example' => 200
                ],
                'message' => [
                    'type' => 'string',
                    'description' => '响应消息',
                    'example' => 'ok'
                ],
                'data' => [
                    'type' => 'object',
                    'description' => '响应数据'
                ],
                'meta' => [
                    'type' => 'object',
                    'description' => '元数据'
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
            if (isset($params['example']) && $params['example'] !== null) {
                $schema['properties']['message']['example'] = $params['example'];
            }
        }
    }

    private function enhanceDataSchema(array &$schema, array $item, string $routeKey): void
    {
        $dataAnnotations = $this->filterAnnotations($item, ResultData::class, $routeKey);
        if (!empty($dataAnnotations)) {
            $schema['properties']['data'] = $this->buildSchema($dataAnnotations);
        } else {
            $resultAnnotations = $this->filterAnnotations($item, Result::class, $routeKey);
            if (!empty($resultAnnotations)) {
                $schema['properties']['data'] = $this->buildSchema($resultAnnotations);
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

    private function addCustomStatusResponses(array &$responses, array $item, string $routeKey, string $contentType): void
    {
        $statusAnnotations = $this->filterAnnotations($item, ResultStatus::class, $routeKey);

        foreach ($statusAnnotations as $statusAnnotation) {
            $params = $statusAnnotation['params'];
            $responses[(string)$params['code']] = [
                'description' => $params['desc'] ?: $params['name'],
                'content' => [
                    $contentType => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'code' => ['type' => 'integer', 'example' => $params['code']],
                                'message' => ['type' => 'string', 'example' => $params['example'] ?: $params['name']]
                            ]
                        ]
                    ]
                ]
            ];
        }
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
            'description' => $params['desc'] ?: $params['name']
        ];

        if (isset($params['example']) && $params['example'] !== null) {
            $schema['example'] = $params['example'];
        }

        if (!empty($params['children'])) {
            $schema = $this->addChildrenToSchema($schema, $params);
        }

        return $schema;
    }

    private function addChildrenToSchema(array $schema, array $params): array
    {
        $childrenProperties = $this->buildChildrenProperties($params['children']);

        if ($params['type'] === FieldEnum::ARRAY) {
            $schema['items'] = ['type' => 'object', 'properties' => $childrenProperties];
        } elseif ($params['type'] === FieldEnum::OBJECT) {
            $schema['properties'] = $childrenProperties;
        }

        return $schema;
    }

    private function buildChildrenProperties(array $children): array
    {
        $properties = [];

        foreach ($children as $child) {
            $childData = is_object($child) && method_exists($child, 'getField')
                ? $child->getField()
                : $child;
            $properties[$childData['field']] = $this->buildFieldSchema($childData);
        }

        return $properties;
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
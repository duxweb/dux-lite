<?php

declare(strict_types=1);

namespace Core\Queue;

use Core\App;
use Core\Queue\Adapter\AmqpAdapter;
use Core\Queue\Adapter\QueueAdapterInterface;
use Core\Queue\Adapter\RedisAdapter;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;

class Queue
{
    private array $workersConfigCache = [];

    /**
     * @var array<string, TransportInterface>
     */
    private array $sendTransports = [];

    /**
     * 队列服务入口（统一通过 App::queue() 获取单例）。
     */
    public function __construct() {}

    /**
     * 添加任务到队列（不会立即执行）。
     * @param string $name worker 名（workers.<name>），为空则使用 default
     * @param string $priority 优先级（high/medium/low），为空则默认 medium
     */
    public function add(
        string $class,
        string $method = '',
        array $params = [],
        string $name = '',
        string $priority = ''
    ): QueueMessage {
        return new QueueMessage($this, $class, $method, $params, $name, $priority);
    }

    /**
     * 投递消息到指定 worker + priority 的物理队列。
     */
    public function dispatch(string $worker, string $priority, QueueJobMessage $message, int $delayMs = 0): void
    {
        [$worker, $priority] = $this->resolveWorkerAndPriorityForSend($worker, $priority);
        $transport = $this->getSendTransport($worker, $priority);

        $envelope = new Envelope($message);
        if ($delayMs > 0) {
            $envelope = $envelope->with(new DelayStamp($delayMs));
        }

        $transport->send($envelope);

        App::event()->dispatch(new QueueEvent($worker, $priority, $message, $delayMs), QueueEvent::ENQUEUE);
    }

    /**
     * 启动单个 worker 进程消费指定队列（常驻）。
     */
    public function process(string $priority = '', string $worker = ''): void
    {
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', '0');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        [$worker, $priority] = $this->resolveWorkerAndPriorityForConsume($worker, $priority);

        // 允许直接运行 `queue:consume <work> <priority>`，确保 handler 能拿到上下文用于事件/统计。
        if ((string)(getenv('DUX_QUEUE_WORK') ?: '') === '') {
            putenv('DUX_QUEUE_WORK=' . $worker);
        }
        if ((string)(getenv('DUX_QUEUE_PRIORITY') ?: '') === '') {
            putenv('DUX_QUEUE_PRIORITY=' . $priority);
        }
        if ((string)(getenv('DUX_QUEUE_RUN_ID') ?: '') === '') {
            putenv('DUX_QUEUE_RUN_ID=manual-' . date('YmdHis') . '-' . getmypid());
        }

        $adapter = $this->createAdapterForWork($worker);
        $transport = $adapter->createConsumeTransport($this->physicalQueueName($worker, $priority), $this->resolveConsumerName());

        $handler = new QueueJobMessageHandler();
        $locator = new HandlersLocator([
            QueueJobMessage::class => [new HandlerDescriptor([$handler, '__invoke'])],
        ]);
        $bus = new MessageBus([new HandleMessageMiddleware($locator)]);
        $worker = new Worker(['default' => $transport], $bus);

        $this->registerSignalHandlers($worker);
        $worker->run(['sleep' => 1000000]);
    }

    /**
     * 获取队列统计。
     * - 不传 $work：返回所有 worker 的统计列表
     * - 传 $work：返回该 worker 的统计列表（1 条）
     *
     * 每条数据包含：
     * - name: worker 名
     * - num: 总并发（最大数量）
     * - weight_high/weight_medium/weight_low: 权重
     * - pending: 待执行数量（各优先级 pending + delayed 汇总）
     * - running: 执行中数量（各优先级 reserved 汇总）
     * - executed: 已执行数量（启动后）
     * - failed: 执行失败数量（启动后）
     */
    public function stats(string $work = ''): array
    {
        $workers = $this->getWorkersConfigCached();
        if ($work !== '') {
            $work = $this->resolveWorkerName($work);
            $workers = [$work => $workers[$work]];
        }

        $rows = [];
        foreach ($workers as $name => $cfg) {
            $adapter = $this->createAdapterForWork($name);
            $weights = is_array($cfg['weights'] ?? null) ? (array)$cfg['weights'] : $this->extractPriorityWeights((array)$cfg);

            $pending = 0;
            $running = 0;

            foreach (['high', 'medium', 'low'] as $priority) {
                $stats = $adapter->stats([$this->physicalQueueName($name, $priority)]);
                $row = $stats[0] ?? null;
                if (is_array($row)) {
                    $pending += (int)($row['pending'] ?? 0) + (int)($row['delayed'] ?? 0);
                    $running += (int)($row['reserved'] ?? 0);
                }
            }

            $metrics = QueueMetrics::get((string)$name);

            $rows[] = [
                'name' => (string)$name,
                'num' => (int)($cfg['num'] ?? 0),
                'weight_high' => (int)($weights['high'] ?? 0),
                'weight_medium' => (int)($weights['medium'] ?? 0),
                'weight_low' => (int)($weights['low'] ?? 0),
                'pending' => $pending,
                'running' => $running,
                'executed' => (int)($metrics[QueueMetrics::KEY_EXECUTED] ?? 0),
                'failed' => (int)($metrics[QueueMetrics::KEY_FAILED] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * 读取并规范化 workers 配置（num 为总并发，high/medium/low 为权重）。
     * @return array<string, array{type:string,driver:string,num:int,weights:array{high:int,medium:int,low:int},queues:array<string,int>}>
     */
    public function getWorkersConfig(): array
    {
        $workers = (array)App::config('queue')->get('workers', []);
        $normalized = [];
        foreach ($workers as $work => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            $type = (string)($cfg['type'] ?? '');
            $driver = (string)($cfg['driver'] ?? '');
            if ($type === '' || $driver === '') {
                throw new RuntimeException('Queue worker config missing type/driver: ' . (string)$work);
            }
            $num = max(0, (int)($cfg['num'] ?? 0));
            $weights = $this->extractPriorityWeights($cfg);
            $queues = $this->resolveQueueTargets($weights, $num);
            $normalized[(string)$work] = [
                'type' => $type,
                'driver' => $driver,
                'num' => $num,
                'weights' => [
                    'high' => (int)($weights['high'] ?? 0),
                    'medium' => (int)($weights['medium'] ?? 0),
                    'low' => (int)($weights['low'] ?? 0),
                ],
                'queues' => $queues,
            ];
        }
        return $normalized;
    }

    /**
     * 获取/缓存发送 transport。
     */
    private function getSendTransport(string $worker, string $priority): TransportInterface
    {
        $adapter = $this->createAdapterForWork($worker);
        $physical = $this->physicalQueueName($worker, $priority);
        $key = $worker . '|' . $adapter->queueKey($physical);

        if (!isset($this->sendTransports[$key])) {
            $this->sendTransports[$key] = $adapter->createSendTransport($physical);
        }
        return $this->sendTransports[$key];
    }

    /**
     * 生成 consumer 名（用于 Redis streams group 消费者区分）。
     */
    private function resolveConsumerName(): string
    {
        $host = gethostname() ?: 'host';
        return $host . '-' . getmypid();
    }

    /**
     * 注册 SIGINT/SIGTERM，用于优雅停止 worker。
     */
    private function registerSignalHandlers(Worker $worker): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }
        pcntl_async_signals(true);
        pcntl_signal(\SIGINT, static function () use ($worker) {
            $worker->stop();
        });
        pcntl_signal(\SIGTERM, static function () use ($worker) {
            $worker->stop();
        });
    }

    /**
     * workers 配置缓存。
     */
    private function getWorkersConfigCached(): array
    {
        if (!$this->workersConfigCache) {
            $this->workersConfigCache = $this->getWorkersConfig();
        }
        return $this->workersConfigCache;
    }

    /**
     * 解析 worker 名：为空取 default，且必须存在于 workers 配置中。
     */
    private function resolveWorkerName(string $worker): string
    {
        $worker = trim($worker);
        $default = (string)App::config('queue')->get('default', '');
        $workers = $this->getWorkersConfigCached();

        if ($worker === '') {
            if ($default === '') {
                throw new RuntimeException('Queue default worker is not configured');
            }
            $worker = $default;
        }

        if (!isset($workers[$worker])) {
            throw new RuntimeException('Queue worker not configured: ' . $worker);
        }

        return $worker;
    }

    /**
     * 解析投递参数：补全 worker / priority。
     */
    private function resolveWorkerAndPriorityForSend(string $worker, string $priority): array
    {
        $worker = $this->resolveWorkerName($worker);
        $priority = trim($priority);
        if ($priority === '') {
            $priority = 'medium';
        }
        if (!in_array($priority, ['high', 'medium', 'low'], true)) {
            throw new RuntimeException('Queue priority not supported: ' . $priority);
        }
        return [$worker, $priority];
    }

    /**
     * 解析消费参数：补全 worker / priority。
     */
    private function resolveWorkerAndPriorityForConsume(string $worker, string $priority): array
    {
        $worker = $this->resolveWorkerName($worker);
        $priority = trim($priority);
        if ($priority === '') {
            $priority = 'medium';
        }
        if (!in_array($priority, ['high', 'medium', 'low'], true)) {
            throw new RuntimeException('Queue priority not supported: ' . $priority);
        }
        return [$worker, $priority];
    }

    /**
     * 物理队列名：<worker>:<priority>
     */
    private function physicalQueueName(string $work, string $queue): string
    {
        return $work . ':' . $queue;
    }

    /**
     * 根据 worker 配置创建后端适配器。
     */
    private function createAdapterForWork(string $work): QueueAdapterInterface
    {
        $workers = $this->getWorkersConfigCached();
        $cfg = $workers[$work] ?? null;
        if (!is_array($cfg)) {
            throw new RuntimeException('Queue worker not configured: ' . $work);
        }

        $type = (string)($cfg['type'] ?? '');
        $driver = (string)($cfg['driver'] ?? '');
        if ($type === '' || $driver === '') {
            throw new RuntimeException('Queue worker config missing type/driver: ' . $work);
        }

        return match ($type) {
            'redis' => new RedisAdapter($driver, (array)App::config('database')->get('redis.drivers.' . $driver), $work),
            'amqp' => new AmqpAdapter($driver, (array)App::config('database')->get('amqp.drivers.' . $driver)),
            default => throw new RuntimeException('Queue type not supported: ' . $type),
        };
    }

    /**
     * 读取 high/medium/low 权重。
     */
    private function extractPriorityWeights(array $cfg): array
    {
        $source = isset($cfg['weights']) && is_array($cfg['weights']) ? (array)$cfg['weights'] : $cfg;
        $queues = [];
        foreach (['high', 'medium', 'low'] as $key) {
            $value = $source[$key] ?? 0;
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $queues[$key] = max(0, (int)$value);
            } else {
                $queues[$key] = 0;
            }
        }
        return $queues;
    }

    /**
     * @param array<string,int> $weights
     * @return array<string,int> queueName => concurrency
     */
    private function resolveQueueTargets(array $weights, int $num): array
    {
        if ($num <= 0) {
            return [];
        }

        // 没配置权重（或全为 0）则全部走 medium
        $totalWeight = array_sum($weights);
        if (!$weights || $totalWeight <= 0) {
            return ['medium' => $num];
        }

        // 将权重归一化到 num 并发（最大余数法）
        $targets = [];
        $remainders = [];
        $allocated = 0;
        foreach ($weights as $queue => $weight) {
            if ($weight <= 0) {
                $targets[$queue] = 0;
                continue;
            }
            $raw = ($num * $weight) / $totalWeight;
            $base = (int)floor($raw);
            $targets[$queue] = $base;
            $allocated += $base;
            $remainders[] = ['queue' => $queue, 'rem' => $raw - $base];
        }

        $remaining = $num - $allocated;
        usort($remainders, static fn ($a, $b) => $b['rem'] <=> $a['rem']);
        for ($i = 0; $i < $remaining; $i++) {
            if (!isset($remainders[$i])) {
                break;
            }
            $q = $remainders[$i]['queue'];
            $targets[$q] = ($targets[$q] ?? 0) + 1;
        }

        // drop zero targets
        $targets = array_filter($targets, static fn ($v) => (int)$v > 0);
        return $targets ?: ['medium' => $num];
    }
}

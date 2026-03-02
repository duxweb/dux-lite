<?php

declare(strict_types=1);

use Core\Queue\PriorityFallbackReceiver;
use Core\Queue\WeightedPriorityReceiver;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

final class TestArrayReceiver implements ReceiverInterface
{
    /**
     * @var list<Envelope>
     */
    private array $queue;

    /**
     * @var list<Envelope>
     */
    public array $acked = [];

    /**
     * @var list<Envelope>
     */
    public array $rejected = [];

    /**
     * @param list<Envelope> $queue
     */
    public function __construct(array $queue = [])
    {
        $this->queue = $queue;
    }

    public function get(): iterable
    {
        $item = array_shift($this->queue);
        if (!$item) {
            return [];
        }
        return [$item];
    }

    public function ack(Envelope $envelope): void
    {
        $this->acked[] = $envelope;
    }

    public function reject(Envelope $envelope): void
    {
        $this->rejected[] = $envelope;
    }
}

function receiverStorageCount(object $receiver): int
{
    $ref = new ReflectionProperty($receiver, 'envelopeReceivers');
    /** @var SplObjectStorage<object, ReceiverInterface> $storage */
    $storage = $ref->getValue($receiver);
    return $storage->count();
}

it('weighted receiver releases envelope references after ack loop', function (): void {
    $jobs = [];
    for ($i = 0; $i < 200; $i++) {
        $jobs[] = new Envelope((object)['id' => $i]);
    }

    $high = new TestArrayReceiver($jobs);
    $receiver = new WeightedPriorityReceiver(
        [
            'high' => $high,
            'medium' => new TestArrayReceiver(),
            'low' => new TestArrayReceiver(),
        ],
        ['high' => 1, 'medium' => 0, 'low' => 0]
    );

    for ($i = 0; $i < 200; $i++) {
        $items = iterator_to_array($receiver->get());
        expect($items)->toHaveCount(1);
        $receiver->ack($items[0]);
    }

    expect($high->acked)->toHaveCount(200);
    expect(receiverStorageCount($receiver))->toBe(0);
});

it('weighted receiver releases envelope references after reject', function (): void {
    $job = new Envelope((object)['id' => 1]);
    $low = new TestArrayReceiver([$job]);
    $receiver = new WeightedPriorityReceiver(
        [
            'high' => new TestArrayReceiver(),
            'medium' => new TestArrayReceiver(),
            'low' => $low,
        ],
        ['high' => 0, 'medium' => 0, 'low' => 1]
    );

    $items = iterator_to_array($receiver->get());
    expect($items)->toHaveCount(1);
    $receiver->reject($items[0]);

    expect($low->rejected)->toHaveCount(1);
    expect(receiverStorageCount($receiver))->toBe(0);
});

it('fallback receiver releases envelope references after ack loop', function (): void {
    $jobs = [];
    for ($i = 0; $i < 120; $i++) {
        $jobs[] = new Envelope((object)['id' => $i]);
    }

    $low = new TestArrayReceiver($jobs);
    $receiver = new PriorityFallbackReceiver(
        'high',
        [
            'high' => new TestArrayReceiver(),
            'medium' => new TestArrayReceiver(),
            'low' => $low,
        ],
        ['high' => 3, 'medium' => 2, 'low' => 1]
    );

    for ($i = 0; $i < 120; $i++) {
        $items = iterator_to_array($receiver->get());
        expect($items)->toHaveCount(1);
        $receiver->ack($items[0]);
    }

    expect($low->acked)->toHaveCount(120);
    expect(receiverStorageCount($receiver))->toBe(0);
});

it('fallback receiver tracks and clears primary rejects', function (): void {
    $high = new TestArrayReceiver([new Envelope((object)['id' => 10])]);
    $receiver = new PriorityFallbackReceiver(
        'high',
        [
            'high' => $high,
            'medium' => new TestArrayReceiver(),
            'low' => new TestArrayReceiver(),
        ],
        ['high' => 3, 'medium' => 2, 'low' => 1]
    );

    $items = iterator_to_array($receiver->get());
    expect($items)->toHaveCount(1);
    $receiver->reject($items[0]);

    expect($high->rejected)->toHaveCount(1);
    expect(receiverStorageCount($receiver))->toBe(0);
});


<?php

declare(strict_types=1);

namespace Core\Queue;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class WeightedPriorityReceiver implements ReceiverInterface
{
    /**
     * @var array<string, ReceiverInterface>
     */
    private array $receivers;

    /**
     * @var string[]
     */
    private array $schedule;

    private int $cursor = 0;

    /**
     * @var \SplObjectStorage<object, ReceiverInterface>
     */
    private \SplObjectStorage $envelopeReceivers;

    /**
     * @param array<string, ReceiverInterface> $receivers
     * @param array<string, int> $weights
     */
    public function __construct(array $receivers, array $weights)
    {
        $this->receivers = $receivers;
        $this->schedule = $this->buildSchedule($weights);
        $this->envelopeReceivers = new \SplObjectStorage();
    }

    public function get(): iterable
    {
        $count = count($this->schedule);
        if ($count === 0) {
            return [];
        }

        for ($i = 0; $i < $count; $i++) {
            $key = $this->schedule[$this->cursor];
            $this->cursor = ($this->cursor + 1) % $count;
            $receiver = $this->receivers[$key] ?? null;
            if (!$receiver) {
                continue;
            }

            $envelopes = $receiver->get();
            foreach ($envelopes as $envelope) {
                $this->envelopeReceivers[$envelope] = $receiver;
                return [$envelope];
            }
        }

        return [];
    }

    public function ack(Envelope $envelope): void
    {
        if (isset($this->envelopeReceivers[$envelope])) {
            /** @var ReceiverInterface $receiver */
            $receiver = $this->envelopeReceivers[$envelope];
            $receiver->ack($envelope);
            unset($this->envelopeReceivers[$envelope]);
            return;
        }
    }

    public function reject(Envelope $envelope): void
    {
        if (isset($this->envelopeReceivers[$envelope])) {
            /** @var ReceiverInterface $receiver */
            $receiver = $this->envelopeReceivers[$envelope];
            $receiver->reject($envelope);
            unset($this->envelopeReceivers[$envelope]);
            return;
        }
    }

    /**
     * @param array<string, int> $weights
     * @return string[]
     */
    private function buildSchedule(array $weights): array
    {
        $schedule = [];
        foreach (['high', 'medium', 'low'] as $priority) {
            $weight = (int)($weights[$priority] ?? 0);
            if ($weight <= 0) {
                continue;
            }
            if (!isset($this->receivers[$priority])) {
                continue;
            }
            for ($i = 0; $i < $weight; $i++) {
                $schedule[] = $priority;
            }
        }

        if ($schedule) {
            foreach (['high', 'medium', 'low'] as $priority) {
                if (isset($this->receivers[$priority]) && (int)($weights[$priority] ?? 0) <= 0) {
                    $schedule[] = $priority;
                }
            }
            return $schedule;
        }

        $keys = array_keys($this->receivers);
        return $keys ?: [];
    }
}

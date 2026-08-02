<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use RuntimeException;
use Throwable;

class DeclareRabbitMqQueues extends Command
{
    protected $signature = 'rabbitmq:declare-queues {--dry-run : Valida e lista as filas sem conectar ao broker}';

    protected $description = 'Declara idempotentemente as filas duráveis usadas pelos workers';

    public function handle(): int
    {
        $host = (array) config('queue.connections.rabbitmq.hosts.0');
        $queues = array_values(array_unique(array_filter(array_map(
            static fn (string $queue): string => trim($queue),
            explode(',', (string) config('queue.connections.rabbitmq.queues', 'high,default,reports')),
        ))));

        if ($queues === []) {
            throw new RuntimeException('RABBITMQ_QUEUES deve possuir ao menos uma fila.');
        }

        foreach ($queues as $queue) {
            if (preg_match('/^[a-zA-Z0-9._-]+$/', $queue) !== 1) {
                throw new RuntimeException("Nome de fila RabbitMQ inválido: {$queue}");
            }
        }

        if ($this->option('dry-run')) {
            foreach ($queues as $queue) {
                $this->line("[dry-run] Fila RabbitMQ válida: {$queue}");
            }

            return self::SUCCESS;
        }

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $connection = null;
            $channel = null;

            try {
                $connection = new AMQPLazyConnection(
                    (string) ($host['host'] ?? '127.0.0.1'),
                    (int) ($host['port'] ?? 5672),
                    (string) ($host['user'] ?? 'guest'),
                    (string) ($host['password'] ?? 'guest'),
                    (string) ($host['vhost'] ?? '/'),
                );
                $channel = $connection->channel();

                foreach ($queues as $queue) {
                    $channel->queue_declare($queue, false, true, false, false);
                    $this->components->info("Fila RabbitMQ pronta: {$queue}");
                }

                return self::SUCCESS;
            } catch (Throwable $exception) {
                if ($attempt === 5) {
                    throw $exception;
                }

                $delay = 2 ** ($attempt - 1);
                $this->components->warn("RabbitMQ indisponível; nova tentativa em {$delay}s ({$attempt}/5).");
                sleep($delay);
            } finally {
                $channel?->close();
                $connection?->close();
            }
        }

        return self::FAILURE;
    }
}

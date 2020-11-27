<?php

declare(strict_types=1);

namespace App;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class AmqpHelper
{
    private AMQPStreamConnection $connect;
    private AMQPChannel $channel;
    private string $callback = '';

    public function __construct()
    {
        $this->connect = AmqpConnection::connect();
        $this->channel = $this->connect->channel();
    }

    public function initChannel(string $queue): void
    {
        $this->channel->queue_declare($queue, false, false, false, false);
    }

    public function prefetch(): void
    {
        $this->channel->basic_qos(0, 1, false);
    }

    public function publish(
        array $lead,
        string $routingKey,
        string $exchange = '',
        ?string $correlation_id = null
    ): void {
        $req = new AMQPMessage(
            (string)json_encode($lead),
            [
                'reply_to' => $this->callback,
                'correlation_id' => $correlation_id ?? microtime(),
            ]
        );
        $this->channel->basic_publish($req, $exchange, $routingKey);
    }

    public function callBackInit(): void
    {
        /** @var array $queue */
        $queue = $this->channel->queue_declare('');
        list($this->callback) = $queue;
    }

    public function consume(callable $callback): void
    {
        $this->channel->basic_consume(
            (string)$this->callback,
            '',
            false,
            true,
            false,
            false,
            $callback
        );
    }

    public function basicConsume(string $queue, string $consumerTag, callable $callback): void
    {
        $this->channel->basic_consume(
            $queue,
            $consumerTag,
            false,
            true,
            false,
            false,
            $callback
        );
    }

    public function basicWait(float $timeout): void
    {
        while ($this->channel->is_consuming()) {
            $this->channel->wait(null, false, $timeout);
        }
    }

    public function wait(?string $result, float $timeout): void
    {
        if (!$result) {
            $this->channel->wait(null, false, $timeout);
        }
    }

    public function close(): void
    {
        $this->channel->close();
        $this->connect->close();
    }
}

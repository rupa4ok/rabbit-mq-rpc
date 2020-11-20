<?php

declare(strict_types=1);

namespace App;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class AmqpConnection
{
    public static function connect(): AMQPStreamConnection
    {
        return new AMQPStreamConnection('rpc-rabbitmq', 5672, 'rabbit', 'rabbit');
    }

    public static function registerShutdown(AMQPStreamConnection $connection, AMQPChannel $channel): void
    {
        register_shutdown_function(function (AMQPChannel $channel, AMQPStreamConnection $connection) {
            $channel->close();
            $connection->close();
        }, $channel, $connection);
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\AmqpConnection;
use App\AmqpHelper;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Consumer extends Command
{
    public function __construct()
    {
        parent::__construct('consume');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = new AmqpHelper(AmqpConnection::connect());
        $connection->prefetch();
        $connection->initChannel('example');

        $callback = function (AMQPMessage $req) use ($connection): void {
            $request = json_decode($req->getBody(), true);
            $reply_to = $req->get('reply_to');
            $correlation_id = $req->get('correlation_id');

            $rpcResponse = [
                'request' => $request['number'],
                'response' => $request['number'] + rand(1, 10)
            ];

            // Performing a lengthy process such as: sleep(3)
            sleep(3);

            $connection->publish($rpcResponse, $reply_to, '', $correlation_id);
        };

        $consumerTag = 'example_' . getmypid();
        $connection->basicConsume('example', $consumerTag, $callback);
        $connection->basicWait(100);
        $connection->close();

        return self::SUCCESS;
    }
}
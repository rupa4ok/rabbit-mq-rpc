<?php

declare(strict_types=1);

namespace App\Command;

use App\AmqpConnection;
use App\AmqpHelper;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Publisher extends Command
{
    public function __construct()
    {
        parent::__construct('publish');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = new AmqpHelper(AmqpConnection::connect());
        $connection->initChannel('example');
        $connection->callBackInit();

        $callback = function (AMQPMessage $res) use (&$result, &$body): void {
            /** @psalm-var array */
            $body = json_decode($res->getBody(), true);
            $result = $body['response'] ?? 'error';
        };

        $example = ['number' => $number = rand(1, 10)];
        $connection->consume($callback);
        $connection->publish($example, 'example');

        $timeout = 60;

        try {
            $connection->wait((string)$result, $timeout);
        } catch (\RuntimeException $e) {
            //@TODO implement logger interface
        }

        $connection->close();

        $response = $body['response'];
        $output->writeln("<comment>Publisher request: $number</comment>");
        $output->writeln("<comment>Consumer response: $response</comment>");

        return self::SUCCESS;
    }
}
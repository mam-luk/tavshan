<?php

namespace Mamluk\Tavshan;

use Monolog\Logger;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class Queue
{
    private string $host, $username, $password;
    private int $port;
    public AbstractConnection $connection;
    private AMQPConnectionConfig $config;
    public AMQPChannel $channel;

    private string $queue;

    private int|null $channelId = null;

    private Logger $log;

    public function __construct(array $config, Logger $log, string $queue, int|null $channelId = null)
    {
        list($this->host, $this->port, $this->username, $this->password) =  [
            $config['hostname'],
            $config['port'],
            $config['username'],
            $config['password']
        ];

        $this->config = new AMQPConnectionConfig();
        $this->config->setHost($this->host);
        $this->config->setPort($this->port);
        $this->config->setUser($this->username);
        $this->config->setPassword($this->password);
        $this->config->setIsSecure(true);
        $this->config->setSslVerify(true);
        $this->config->setVhost('/');
        $this->log = $log;
        $this->log->info('Queue library instantiated', ['queue' => $queue, 'host' => $this->host, 'port' => $this->port, 'username' => $this->username]);
        $this->queue = $queue;
        $this->channelId = $channelId;
        $this->connect();
        $this->openChannel();
        $this->declareQueue();
    }

    public function connect(): void
    {
        $this->connection = AMQPConnectionFactory::create($this->config);
    }

    public function disconnect(): void
    {
        $this->connection->close();
    }

    public function openChannel(): void
    {
        $this->channel = $this->connection->channel($this->channelId);
        $this->channel->confirm_select();

    }

    public function closeChannel(): void
    {
        $this->channel->close();
    }

    public function declareQueue(bool $passive = false, bool $durable = true, bool $exclusive = false, bool $autoDelete = false): void
    {
        $this->channel->queue_declare($this->queue, $passive, $durable, $exclusive, $autoDelete);
    }

    public function publish(string $data, string $exchange = ''): void
    {
        $message = new AMQPMessage($data, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

        $this->channel->set_ack_handler(function (AMQPMessage $message) use ($exchange) {
            // Do nothing, message ack'd
            $this->log->debug('Message ACK\'d',['message' => $message->getBody()]);
            $this->channel->basic_publish($message, $exchange, $this->queue);
        });

        $this->channel->set_nack_handler(function (AMQPMessage $message) use ($exchange) {
            // message nack'd, requeue after a sleep of 1 second
            $this->log->error('Message NACK\'d, requeueing after 1 second',['message' => $message->getBody()]);
            sleep(1);
            $this->channel->basic_publish($message, $exchange, $this->queue);
        });

        $this->channel->basic_publish($message, $exchange, $this->queue);
    }

    public function subscribe($callback = null, string $consumerTag = '', bool $noLocal = false, bool $noAck = false, bool $exclusive = false, bool $noWait = false): void
    {
        $this->channel->basic_consume($this->queue, $consumerTag, $noLocal, $noAck, $exclusive, $noWait, $callback);
        try {
            $this->channel->consume();
        } catch (\Throwable $exception) {
            echo $exception->getMessage();
        }

        $this->channel->close();
        $this->connection->close();

    }
}
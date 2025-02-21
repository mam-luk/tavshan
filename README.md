# Tavshan by Mamluk

Tavshan is a simplified Rabbit MQ library written on top of the PHP AMQP Library. It doesn't utilise all the bells and 
whistles within Rabbit MQ yet, but will be enhanced  as required (or with your contributions).

## What does Tavshan mean?

It's actually *tavşan* (pronounced as tavshan), and means rabbit in Turkish.

## How to use it?

Include it by adding `mamluk/tavshan` to your `composer.json` file.

```php
use Mamluk\Tavshan\Queue
use Monolog\Logger;
use Psr\Log\LogLevel;

$logger = new Monolog\Logger('myQueue');
$logger->pushHandler(new StreamHandler('php://stdout', LogLevel::DEBUG));

$q = new Queue($config, $logger, 'queue_name', 1); // See __construct() src/Queue for what the config array requires

// Produce a message
$q->publish('My first message');

// Consume messages
$callback = function ($msg) {
  echo ' [x] Received ', $msg->body, "\n";
};
$this->q->subscribe($callback, 'queue_name');

```

### License

MIT

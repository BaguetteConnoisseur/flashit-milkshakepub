<?php

require __DIR__ . '/vendor/autoload.php';

use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\Factory;
use React\Socket\Server as ReactSocketServer;
use React\Http\Server as ReactHttpServer;
use React\Http\Message\Response as ReactHttpResponse;
use Psr\Http\Message\ServerRequestInterface;

class BroadcastWebSocket implements MessageComponentInterface {
    public $clients;
    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }
    public function onMessage(ConnectionInterface $from, $msg) {
        echo "Received message from {$from->resourceId}: $msg\n";
        foreach ($this->clients as $client) {
            $client->send($msg);
        }
    }
    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
    }
    public function broadcast($msg) {
        foreach ($this->clients as $client) {
            $client->send($msg);
        }
    }
}

$wsPort = getenv('WS_PORT') ?: 8081;
$httpPort = $wsPort + 1;
$loop = Factory::create();

$wsComponent = new BroadcastWebSocket();

// WebSocket server
$wsSocket = new ReactSocketServer("0.0.0.0:$wsPort", $loop);
$wsServer = new Ratchet\Server\IoServer(
    new Ratchet\Http\HttpServer(
        new WsServer($wsComponent)
    ),
    $wsSocket,
    $loop
);

// HTTP broadcast endpoint
$httpServer = new ReactHttpServer(function (ServerRequestInterface $request) use ($wsComponent) {
    try {
        if ($request->getMethod() === 'POST' && $request->getUri()->getPath() === '/broadcast') {
            $body = $request->getBody()->getContents();
            if (empty($body)) {
                echo "POST /broadcast: No message received\n";
                return new ReactHttpResponse(400, ['Content-Type' => 'text/plain'], 'No message');
            }
            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "POST /broadcast: Invalid JSON - " . json_last_error_msg() . "\n";
                return new ReactHttpResponse(400, ['Content-Type' => 'text/plain'], 'Invalid JSON: ' . json_last_error_msg());
            }
            // Optionally validate required fields
            if (!isset($data['type']) || !isset($data['data'])) {
                echo "POST /broadcast: Missing required fields\n";
                return new ReactHttpResponse(400, ['Content-Type' => 'text/plain'], 'Missing required fields');
            }
            $wsComponent->broadcast(json_encode($data));
            echo "POST /broadcast: Broadcasted successfully\n";
            $responseBody = json_encode([
                'status' => 'success',
                'message' => 'Broadcasted',
                'data' => $data
            ]);
            if (!is_string($responseBody)) {
                $responseBody = 'Broadcasted';
            }
            return new ReactHttpResponse(200, ['Content-Type' => 'application/json'], $responseBody);
        }
        return new ReactHttpResponse(404, ['Content-Type' => 'text/plain'], 'Not found');
    } catch (Throwable $e) {
        echo "POST /broadcast: Global Exception - " . $e->getMessage() . "\n";
        return new ReactHttpResponse(500, ['Content-Type' => 'text/plain'], 'Internal Server Error: ' . $e->getMessage());
    }
});
$httpSocket = new ReactSocketServer("0.0.0.0:$httpPort", $loop);
$httpServer->listen($httpSocket);

echo "WebSocket server running on ws://localhost:$wsPort\n";
echo "HTTP broadcast endpoint running on http://localhost:$httpPort/broadcast\n";

$loop->run();

# PHP WebSocket Server with Docker (Ratchet)

## Overview
This guide explains how to set up a minimal PHP WebSocket server using Ratchet, running in Docker. It covers cleanup, cross-platform notes, and steps for deployment on Linux or Windows servers.

## Requirements
- Docker and Docker Compose
- PHP 8.1+ (handled by Docker)
- Git (for dependency management)

## Setup Steps

### 1. Clone the Repository
```
git clone <your-repo-url>
cd flashit-milkshakepub
```

### 2. Build and Start the WebSocket Server
```
docker compose up -d --build flashit-milkshakepub-websocket
```
- The server will listen on port 8081 (mapped to host).
- To connect: `ws://<host-ip>:8081`

### 3. Clean Workspace
- No test scripts or unnecessary dependencies remain.
- Only `server.php`, `composer.json`, and the `vendor` folder are required.

### 4. Cross-Platform Notes
- **Linux servers:**
  - No changes needed. Docker handles all dependencies and permissions.
  - Port 8081 is non-privileged; no root required.
- **Windows servers:**
  - Docker Desktop required.
  - Port mapping works the same way.

### 5. Troubleshooting
- If you cannot connect:
  - Check Docker logs: `docker logs flashit-milkshakepub-websocket`
  - Ensure port 8081 is open in firewall.
  - Verify no other service is using port 8081.
- To restart: `docker compose restart flashit-milkshakepub-websocket`

### 6. Updating Dependencies
```
docker exec flashit-milkshakepub-websocket composer install
```

### 7. Deploying on a Server
- Follow steps 1–3.
- Ensure Docker is installed and running.
- Open port 8081 in firewall.
- Use `ws://<server-ip>:8081` for clients.

## Minimal Dockerfile Example
```
FROM php:8.1-cli
RUN apt-get update && apt-get install -y git unzip curl net-tools
RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer
WORKDIR /var/www/websocket
COPY websocket_php/composer.json ./
RUN composer install --no-interaction --no-progress --prefer-dist --ignore-platform-reqs
COPY websocket_php/. ./
EXPOSE 8081
CMD ["php", "server.php"]
```

## Minimal docker-compose.yml Example
```
services:
  flashit-milkshakepub-websocket:
    build:
      context: .
      dockerfile: docker/websocket/Dockerfile
    container_name: flashit-milkshakepub-websocket
    restart: unless-stopped
    ports:
      - "8081:8081"
    networks:
      - flashit-milkshakepub-network
networks:
  flashit-milkshakepub-network:
    driver: bridge
```

## Minimal server.php Example
```
<?php
require __DIR__ . '/vendor/autoload.php';
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
class DummyWebSocket implements MessageComponentInterface {
    public function onOpen(ConnectionInterface $conn) {
        echo "New connection! ({$conn->resourceId})\n";
    }
    public function onMessage(ConnectionInterface $from, $msg) {
        echo "Received message from {$from->resourceId}: $msg\n";
        $from->send("Echo: $msg");
    }
    public function onClose(ConnectionInterface $conn) {
        echo "Connection {$conn->resourceId} has disconnected\n";
    }
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        if (strpos($e->getMessage(), '$request can not be null') !== false) {
            echo "Received invalid request, ignoring.\n";
        }
    }
}
echo "Starting WebSocket server on 0.0.0.0:8081...\n";
$server = IoServer::factory(
    new HttpServer(
        new WsServer(new DummyWebSocket())
    ),
    8081,
    '0.0.0.0'
);
$server->run();
```

## Security
- Use a firewall to restrict access to port 8081 if needed.
- For production, consider HTTPS termination (nginx or Caddy) and secure WebSocket proxying.

---
For further automation or deployment scripts, just ask!

# Flashit order sheet for MilkshakePub

A lightweight order sheet web app for FlashIT's MilkshakePub, containerized with Docker Compose.

## Contributors

This project was originally created by:
- Filur - (https://www.github.com/Filuren123)
- Ponky - (https://www.github.com/BaguetteConnoisseur)

From FlashIT'25.

## Stack

- `frontend`: Nginx
- `backend`: PHP-FPM (`mysqli`)
- `db`: MySQL 8.4

## Quick Start

### Windows (one-click)

Run `quick-start.bat`.

## WebSocket Server Setup (Ratchet)

See WEBSOCKET_SETUP.md for full details. Here are the essentials:

### Quick WebSocket Start
1. Build and start:
   ```bash
   docker compose up -d --build flashit-milkshakepub-websocket
   ```
2. Connect your client to: `ws://localhost:${WS_PORT}` (default: 8081; see .env)

### Firewall Automation (Windows & Linux)
After starting the WebSocket server, open port ${WS_PORT} for external access (default: 8081; see .env):

#### Windows (PowerShell)
```powershell
if ($IsWindows) {
    New-NetFirewallRule -DisplayName "WebSocket ${WS_PORT}" -Direction Inbound -Action Allow -Protocol TCP -LocalPort $env:WS_PORT
}
```

#### Linux (UFW)
```bash
if [ "$(uname)" = "Linux" ]; then
    sudo ufw allow $WS_PORT/tcp
    sudo ufw reload
fi
```

- Run the appropriate command for your OS.
- This ensures your WebSocket server is accessible from other devices.

### Minimal Dockerfile
```
FROM php:8.1-cli
RUN apt-get update && apt-get install -y git unzip curl net-tools
RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer
WORKDIR /var/www/websocket
COPY websocket_php/composer.json ./
RUN composer install --no-interaction --no-progress --prefer-dist --ignore-platform-reqs
COPY websocket_php/. ./
EXPOSE ${WS_PORT}
CMD ["php", "server.php"]
```

### Minimal docker-compose.yml
```
services:
  flashit-milkshakepub-websocket:
    build:
      context: .
      dockerfile: docker/websocket/Dockerfile
    container_name: flashit-milkshakepub-websocket
    restart: unless-stopped
    ports:
    - "${WS_PORT:-8081}:8081"
    networks:
      - flashit-milkshakepub-network
networks:
  flashit-milkshakepub-network:
    driver: bridge
```

### Minimal server.php
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
echo "Starting WebSocket server on 0.0.0.0:${WS_PORT}...\n";
$server = IoServer::factory(
    new HttpServer(
        new WsServer(new DummyWebSocket())
    ),
    getenv('WS_PORT') ?: 8081,
    '0.0.0.0'
);
$server->run();
```

### Linux/Windows Notes
- Docker handles all dependencies and permissions.
- Port 8081 is non-privileged; no root required.
- Open port 8081 in firewall for external access.

### Troubleshooting
- Check logs: `docker logs flashit-milkshakepub-websocket`
- Restart: `docker compose restart flashit-milkshakepub-websocket`
- Update dependencies: `docker exec flashit-milkshakepub-websocket composer install`

---
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
echo "Starting WebSocket server on 0.0.0.0:${WS_PORT}...\n";
$server = IoServer::factory(
    new HttpServer(
        new WsServer(new DummyWebSocket())
    ),
    getenv('WS_PORT') ?: 8081,
    '0.0.0.0'
);
$server->run();
```

### Linux/Windows Notes
- Docker handles all dependencies and permissions.
- Port 8081 is non-privileged; no root required.
- Open port 8081 in firewall for external access.

### Troubleshooting
- Check logs: `docker logs flashit-milkshakepub-websocket`
- Restart: `docker compose restart flashit-milkshakepub-websocket`
- Update dependencies: `docker exec flashit-milkshakepub-websocket composer install`
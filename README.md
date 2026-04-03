# Flashit MilkshakePub Order System

A modern, containerized web app for managing orders at FlashIT's MilkshakePub.

## Stack
- **Frontend:** Nginx
- **Backend:** PHP-FPM
- **Database:** MySQL 8.4 (with PDO MySQL extension)
- **WebSocket:** Node.js

## Prerequisites
- Node.js v18+ for running the websocket
- Docker for running other services

## Quick Start

### Windows (one-click)
```sh
quick-start.bat
```

### Manual (All Platforms)
1. Copy env template:
   ```sh
   cp .env.example .env
   # or on PowerShell:
   Copy-Item .env.example .env
   ```
2. Edit `.env` and set strong, unique credentials.
3. Start the stack:
   ```sh
   docker compose up -d --build
   ```
4. Open [http://localhost:8080](http://localhost:8080)

## Firewall Note

### Required Ports

For normal use, you only need to allow incoming connections to **8081**:

- **8081** — WebSocket server (used for real-time updates to clients)

Port **8082** is the internal broadcast API used by the PHP backend to talk to the websocket container over the Docker network. It is not exposed on the host in the default compose setup, so it does not need a firewall rule.

If 8081 is blocked, real-time updates will not work.

By default, the websocket server listens on port 8081 for client connections. If you run the stack locally, Windows Defender Firewall may block this port.

**To allow websocket traffic through the firewall:**

1. When you first run the websocket server, Windows may prompt you to allow access. Click **Allow access**.

2. To add the rule manually, you can use the Windows Firewall GUI or run one of these commands in an administrator PowerShell:

   **PowerShell (Windows):**
   ```powershell
   New-NetFirewallRule -DisplayName "Flashit RealTime WS (8081)" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 8081
   ```

   **Linux (ufw):**
   ```sh
   sudo ufw allow 8081/tcp comment 'Flashit RealTime WS'
   ```

This ensures that all clients on your network can receive real-time updates.

## Useful Commands
- Start: `docker compose up -d --build`
- Stop: `docker compose down`
- Reset DB: `docker compose down -v`
- Logs: `docker compose logs -f`

## Contributors
This project was created and is managed by:
- [Filur](https://www.github.com/Filuren123)
- [Ponky](https://www.github.com/BaguetteConnoisseur)

From FlashIT'25.

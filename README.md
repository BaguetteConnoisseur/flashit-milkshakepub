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

For the system to function correctly, you must allow incoming connections to **both** of these ports:

- **8081** — WebSocket server (used for real-time updates to clients)
- **8082** — Broadcast API (used for backend to trigger broadcasts)

If either port is blocked, real-time updates or broadcasts will not work.

By default, the websocket server listens on port 8081 for client connections and on port 8082 for broadcast API requests. If you run the stack locally, Windows Defender Firewall may block these ports.

**To allow the broadcast through the firewall:**

1. When you first run the websocket server, Windows may prompt you to allow access. Click **Allow access**.

2. To add the rule manually, you can use the Windows Firewall GUI or run one of these commands in an administrator PowerShell:

   **PowerShell (Windows):**
   ```powershell
   New-NetFirewallRule -DisplayName "Flashit RealTime WS (8081)" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 8081
   New-NetFirewallRule -DisplayName "Flashit Broadcast API (8082)" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 8082
   ```

   **Linux (ufw):**
   ```sh
   sudo ufw allow 8081/tcp comment 'Flashit RealTime WS'
   sudo ufw allow 8082/tcp comment 'Flashit Broadcast API'
   ```

This ensures that all clients on your network can receive real-time updates.

## Useful Commands
- Start: `docker compose up -d --build`
- Stop: `docker compose down`
- Reset DB: `docker compose down -v`
- Logs: `docker compose logs -f`

## Contributors
This project was originally created by:
- [Filur](https://www.github.com/Filuren123)
- [Ponky](https://www.github.com/BaguetteConnoisseur)

From FlashIT'25.
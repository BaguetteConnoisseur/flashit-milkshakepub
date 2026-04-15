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

For production use, only the reverse proxy/web server entrypoint should be open externally:

- **80/443** — HTTP/HTTPS at the edge (Nginx or your front reverse proxy)

Port **8081** (websocket) is now internal-only and reached through Nginx at `/ws/`.

Port **8082** is the internal broadcast API used by the PHP backend to talk to the websocket container over the Docker network. It is not exposed on the host, so it does not need a firewall rule.

In this compose setup, the app is bound to `127.0.0.1:8080` for local host-only access. Put a front proxy in front of it for public URL access.

If your public reverse proxy can reach this app on localhost, real-time updates work through `/ws/` without opening an extra websocket firewall port.

This keeps the websocket service hidden from the outside network while preserving instant updates.

No dedicated inbound firewall rule for websocket port 8081 is required.

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

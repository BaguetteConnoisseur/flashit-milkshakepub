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
3. Create the external Traefik network once (if missing):
   ```sh
   docker network create traefik
   ```
4. Start the stack:
   ```sh
   docker compose up -d --build
   ```
5. Open [http://localhost:8080/](http://localhost:8080/)

## Required Ports

- **80/443**: external HTTP/HTTPS entrypoint for Nginx or the front reverse proxy
- **8080**: local host binding for the app in this compose setup
- **8081**: internal websocket service, reached through Nginx at `/ws/`
- **8082**: internal broadcast API used by PHP to reach the websocket container over the Docker network

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

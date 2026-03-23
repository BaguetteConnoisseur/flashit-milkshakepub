# Flashit MilkshakePub Order System

A modern, containerized web app for managing orders at FlashIT's MilkshakePub.

## Stack
- **Frontend:** Nginx
- **Backend:** PHP-FPM
- **Database:** MySQL 8.4
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

## Firewall Note (Windows)

If you want to receive live order updates (broadcasts) via WebSocket, make sure your firewall allows incoming connections to the WebSocket port. By default, the websocket server listens on port 8081. If you run the stack locally, Windows Defender Firewall may block it.

**To allow the broadcast through the firewall:**

1. When you first run the websocket server, Windows may prompt you to allow access. Click **Allow access**.
2. To add the rule manually, you can use the Windows Firewall GUI or run one of these commands in an administrator PowerShell:

   **PowerShell:**
   ```powershell
   New-NetFirewallRule -DisplayName "Flashit WebSocket" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 8081
   ```

   **Command Prompt (netsh):**
   ```cmd
   netsh advfirewall firewall add rule name="Flashit WebSocket" dir=in action=allow protocol=TCP localport=8081
   ```

   Or, in the GUI:
   - Open **Windows Defender Firewall** > **Advanced settings**
   - Go to **Inbound Rules** > **New Rule...**
   - Select **Port**, then **TCP**, and enter `8081` (or your configured port)
   - Allow the connection, apply to all profiles, and give it a name like `Flashit WebSocket`

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
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

### Manual

1. Copy env template:
   ```bash
   cp .env.example .env
   ```
   PowerShell:
   ```powershell
   Copy-Item .env.example .env
   ```

2. Edit `.env` and set your own secure credentials.

3. Start the stack:
   ```bash
   docker compose up -d --build
   ```

4. Open:
   - `http://localhost:8080`

## Security

Before deployment:
- Change `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, and `ADMIN_PASSWORD`
- Never commit `.env`
- Review [SECURITY.md](SECURITY.md)

## Useful Commands

- Start: `docker compose up -d --build`
- Stop: `docker compose down`
- Reset DB volume: `docker compose down -v`
- Logs: `docker compose logs -f`
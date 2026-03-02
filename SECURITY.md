# Security Guidelines

## Before Deploying to Production

### 1. Change All Default Credentials

**Critical:** The `.env.example` file contains placeholder passwords that MUST be changed:

```bash
# Copy the example file
cp .env.example .env

# Edit .env and change these values:
MYSQL_ROOT_PASSWORD=<strong-unique-password>
MYSQL_PASSWORD=<strong-unique-password>
ADMIN_USERNAME=<your-admin-username>
ADMIN_PASSWORD=<strong-unique-password>
```

### 2. Never Commit Credentials

- `.env` is already in `.gitignore` - never remove it
- Never commit real passwords to the repository
- Use environment variables for all sensitive data

### 3. Secure Database Access

- Change default MySQL user from `flashit` to something unique
- Use strong passwords (minimum 16 characters, mixed case, numbers, symbols)
- Limit database user permissions to only what's needed

### 4. HTTPS in Production

- Never run in production without HTTPS
- Use Let's Encrypt or similar for SSL certificates
- Update nginx config to redirect HTTP to HTTPS

### 5. Regular Updates

- Keep Docker images updated: `docker compose pull`
- Monitor for PHP and MySQL security updates
- Review application logs regularly

## Reporting Security Issues

If you find a security vulnerability, please DO NOT open a public issue. Contact the repository maintainers directly.

## Current Security Measures

✅ Environment variable based credential management
✅ Session-based authentication for admin pages
✅ SQL injection protection via mysqli prepared statements (where implemented)
✅ Separate Docker containers for isolation
✅ Non-root MySQL user

## Recommended Improvements for Production

- [ ] Implement HTTPS/SSL
- [ ] Add rate limiting for login attempts
- [ ] Implement CSRF protection
- [ ] Add password hashing (currently uses plain comparison)
- [ ] Set up database backups
- [ ] Configure firewall rules
- [ ] Use prepared statements throughout
- [ ] Add input validation and sanitization

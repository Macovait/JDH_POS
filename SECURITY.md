# Security Policy

## Reporting Vulnerabilities

If you discover a security vulnerability, please report it responsibly:

- Email: security@jakababa.com
- Do NOT open a public issue for security vulnerabilities

## Secrets Management

### Initial Setup

After cloning this repository, generate your environment file with fresh secrets:

```bash
php scripts/setup/generate_env.php
```

This creates `.env` from `.env.example` with cryptographically secure random values.

### Rotating Secrets

If you suspect a secret has been compromised, rotate it immediately:

**ENCRYPTION_KEY:**
```bash
php -r "echo bin2hex(random_bytes(32));"
```
Update the `ENCRYPTION_KEY` value in your `.env` file with the new key.

> ⚠️ **WARNING**: Rotating `ENCRYPTION_KEY` will invalidate any data encrypted with the old key. Plan a migration if you have encrypted tenant configs.

**Database Password:**
```sql
ALTER USER 'jakababa_prod'@'localhost' IDENTIFIED BY 'new_strong_password_here';
FLUSH PRIVILEGES;
```
Then update `DB_PASS` in `.env`.

**M-Pesa API Keys:**
Regenerate via the [Safaricom Daraja Portal](https://developer.safaricom.co.ke/).

### Known Compromised Secrets

The following secrets were previously committed to this repository and **MUST NOT** be reused:

| Secret | Status | Action Required |
|--------|--------|-----------------|
| `ENCRYPTION_KEY=c1624c8c...f95137f8` | ❌ COMPROMISED | Generate new key |

The `SecretsValidator` class (`src/Security/SecretsValidator.php`) automatically rejects known-compromised keys at application boot time.

### Best Practices

1. **Never commit `.env` files** — they are in `.gitignore`
2. **Use `scripts/setup/generate_env.php`** for new installations
3. **Set file permissions**: `chmod 600 .env` (Unix) to restrict access
4. **Use environment-specific configs**: different keys per environment
5. **Rotate secrets regularly**: at minimum every 90 days for production
6. **Use a secrets manager** in production (e.g., AWS Secrets Manager, HashiCorp Vault)

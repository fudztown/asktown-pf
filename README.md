# asktown.co.uk Finance Dashboard

Personal finance monitoring system using TrueLayer Open Banking.

## Security Notice

**Never commit real secrets to this repository.**

### Required Local Setup

1. Copy the example environment file:
   ```bash
   cp config/.env.example /opt/finance/.env
   ```

2. Edit `/opt/finance/.env` and add your real credentials.

3. Set proper permissions:
   ```bash
   chmod 600 /opt/finance/.env
   chmod 600 /opt/finance/tokens.enc
   ```

### What is Gitignored

- `.env` and `*.env`
- `tokens.enc`
- Any file containing real API keys or passwords

### Production Recommendations

- Run services as a dedicated low-privilege user
- Use systemd `LoadCredential` instead of `EnvironmentFile` when possible
- Enable rate limiting on the OAuth callback endpoint

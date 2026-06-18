# Project Status: asktown-pf (June 2026)

## Core Architecture
- **Tech Stack**: PHP 8.x, Supabase (Postgres & Auth), Libsodium, TrueLayer.
- **Security**: Application-layer encryption (Vault) using XChaCha20-Poly1305.
- **Identity**: Supabase JWT authentication verified in PHP backend (`get_user_accounts.php`).

## Current State (Commit 3baf31b)
- **Dashboard**: Working navy/white UI with real-time assets/liabilities/net position.
- **Integrations**: Amex, Halifax (Current/Joint), Revolut.
- **Automation**: Cron `asktown-vault-pulse` runs every 6 hours.
- **Hardened**: Fixed nested JS template bugs and ID alignment issues.

## Pending Work
- [ ] **Multi-User Scaling**: Transition from hardcoded `userId` in `index.php` to `session.user.id`.
- [ ] **Onboarding**: Create "Empty State" UI for users with 0 bank connections.
- [ ] **Insights**: Expand beyond total metrics to simple category spending cards.
- [ ] **Reliability**: Add better error handling for TrueLayer 403s on cold-start connections.

## Critical Files
- `public/get_user_accounts.php`: Main API (Encryption/Decryption/Auth).
- `public/index.php`: Main Dashboard & SPA Logic.
- `scripts/gather.php`: The backend sync engine.
- `lib/Vault.php`: Encryption wrapper.

## Target User (Current Session)
- Pete Town (UUID: f6b1e9eb-03a4-470b-a5da-8de341f3c15a)

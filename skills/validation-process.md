# Validation Process Skill

## When to Use
Before presenting **any** code change to the user.

## Required Checks (in order)

1. **PHP Syntax**
   - Run `php -l` on every modified `.php` file
   - Must report "No syntax errors detected"

2. **Validation Script**
   - Run `./scripts/validate.sh`
   - Must exit with code 0

3. **Test Suite**
   - Run `php tests/run_tests.php`
   - All tests must pass

4. **Supabase Pattern Check**
   - Ensure no `const supabase =` remains in any file
   - Use `window.supabaseClient` only

5. **TrueLayer Safety**
   - Never include `truelayer.php` at the top level of a page without try-catch
   - Prefer using `get_user_accounts.php` via AJAX for account display

## Updating Tests
- When adding new functionality in `config/*.php`, add corresponding tests in `tests/`
- When modifying `truelayer.php` or `state_signer.php`, update `tests/test_truelayer.php`
- When changing login/registration flow, update `tests/run_tests.php`

## Subagent Usage
Before showing changes to the user, the orchestrator should delegate validation to a QA subagent when possible.

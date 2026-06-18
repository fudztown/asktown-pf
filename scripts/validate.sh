#!/bin/bash
set -e

echo "=== asktown-pf Full Validation ==="
echo "Started at: $(date)"
echo ""

FAILED=0

# 1. PHP Syntax
echo "[1/6] PHP Syntax Check..."
if ! find . -name "*.php" \
    -not -path "./vendor/*" \
    -not -path "./.hermes/*" \
    -not -path "./.git/*" \
    -exec php -l {} + 2>/dev/null | grep -v "No syntax errors detected" > /dev/null; then
    echo "   ✓ All PHP files have valid syntax"
else
    echo "   ✗ PHP syntax errors found"
    FAILED=1
fi

# 2. Supabase Pattern
echo "[2/6] Supabase Client Pattern Check..."
if grep -r "const supabase =" --include="*.php" --include="*.js" . > /dev/null 2>&1; then
    echo "   ✗ Found 'const supabase =' declarations"
    FAILED=1
else
    echo "   ✓ No old Supabase pattern found"
fi

# 3. Run Core Tests
echo "[3/6] Running Test Suite..."
if php tests/run_tests.php > /dev/null 2>&1; then
    echo "   ✓ All tests passed"
else
    echo "   ✗ Test suite failed"
    php tests/run_tests.php
    FAILED=1
fi

# 4. TrueLayer Safety
echo "[4/6] TrueLayer Include Safety..."
if grep -r "require_once.*truelayer.php" --include="*.php" . | grep -v "get_user_accounts.php" | grep -v "tests/" > /dev/null 2>&1; then
    echo "   ✗ truelayer.php included outside safe locations"
    FAILED=1
else
    echo "   ✓ truelayer.php includes are safe"
fi

# 5. Secrets Check (only real keys, not env var names)
echo "[5/6] Secrets Check..."
if grep -rE "(TOKEN_ENCRYPTION_KEY|OAUTH_STATE_SECRET)=[a-zA-Z0-9]" --include="*.php" --include="*.js" . | grep -v ".env" | grep -v "config/" > /dev/null 2>&1; then
    echo "   ✗ Possible secret leakage detected"
    FAILED=1
else
    echo "   ✓ No secrets found in code"
fi

# 6. File Permissions
echo "[6/6] Critical File Permissions..."
if [ -f /opt/finance/.env ]; then
    PERMS=$(stat -c "%a" /opt/finance/.env 2>/dev/null || echo "000")
    if [ "$PERMS" != "640" ]; then
        echo "   ⚠ .env permissions are $PERMS (recommended: 640)"
    else
        echo "   ✓ .env permissions OK"
    fi
fi

echo ""
if [ $FAILED -eq 0 ]; then
    echo "=== ✅ All validations passed ==="
    exit 0
else
    echo "=== ❌ Validation failed ==="
    exit 1
fi

#!/bin/bash
# Pre-commit validation for asktown-pf
# Can be used as: cp scripts/pre-commit.sh .git/hooks/pre-commit && chmod +x .git/hooks/pre-commit

echo "Running pre-commit validation..."

if ! ./scripts/validate.sh; then
    echo ""
    echo "❌ Validation failed. Commit aborted."
    echo "Fix the issues above and try again."
    exit 1
fi

echo "✅ Pre-commit validation passed"
exit 0

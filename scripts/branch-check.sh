#!/bin/bash
# Branch safety check

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")

if [ "$CURRENT_BRANCH" = "main" ] || [ "$CURRENT_BRANCH" = "master" ]; then
    echo "⚠️  WARNING: You are on the $CURRENT_BRANCH branch"
    echo "   Consider creating a feature branch instead:"
    echo "   git checkout -b feature/your-change"
    echo ""
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

echo "Branch check passed"

#!/bin/bash
set -e

PROJECT_DIR="$HOME/asktown-finance"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

cd "$PROJECT_DIR"

# Load GitHub token
GITHUB_TOKEN=$(php -r "
require '$SCRIPT_DIR/github_token.php';
echo get_github_token();
")

if [ -z "$GITHUB_TOKEN" ]; then
    echo "ERROR: Could not load GitHub token"
    exit 1
fi

REMOTE_URL="https://$GITHUB_TOKEN@github.com/fudztown/asktown-pf.git"

if ! git remote | grep -q origin; then
    git remote add origin "$REMOTE_URL"
else
    git remote set-url origin "$REMOTE_URL"
fi

git branch -M main 2>/dev/null || true
git push -u origin main

echo "Push completed successfully"

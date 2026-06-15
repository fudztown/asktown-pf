#!/bin/bash
set -e

REPO_URL="https://github.com/fudztown/asktown-pf.git"
PROJECT_DIR="$HOME/asktown-finance"

cd "$PROJECT_DIR"

# Load GitHub token securely
GITHUB_TOKEN=$(php -r '
require __DIR__ . "/github_token.php";
echo get_github_token();
')

if [ -z "$GITHUB_TOKEN" ]; then
    echo "ERROR: Could not load GitHub token"
    exit 1
fi

# Set remote if not already set
if ! git remote | grep -q origin; then
    git remote add origin "https://$GITHUB_TOKEN@github.com/fudztown/asktown-pf.git"
    echo "Remote added"
else
    git remote set-url origin "https://$GITHUB_TOKEN@github.com/fudztown/asktown-pf.git"
fi

# Push
git branch -M main 2>/dev/null || true
git push -u origin main

echo "Push completed successfully"

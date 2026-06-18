#!/bin/bash
# Set the current goal for the validation monitor

GOAL_FILE="/root/asktown-finance/.current_goal"

if [ -z "$1" ]; then
    echo "Usage: $0 \"Goal description\""
    exit 1
fi

echo "$1" > "$GOAL_FILE"
echo "Current goal set to: $1"

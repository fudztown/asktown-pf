#!/bin/bash
# Validation Monitor - 5-minute progress updates

PAUSE_FILE="/root/asktown-finance/.pause_validation"
LOG_FILE="/root/asktown-finance/logs/validation.log"
GOAL_FILE="/root/asktown-finance/.current_goal"
ACTIVITY_FILE="/root/asktown-finance/logs/activity.log"

if [ -f "$PAUSE_FILE" ]; then
    echo "$(date): [PAUSED]"
    exit 0
fi

if [ ! -f "$GOAL_FILE" ]; then
    echo "$(date): [CONTINUING] No goal set"
    exit 0
fi

GOAL=$(cat "$GOAL_FILE")
echo "$(date): Goal: $GOAL"

if /root/asktown-finance/scripts/validate.sh >> "$LOG_FILE" 2>&1; then
    echo "$(date): [STOPPING] Goal achieved - pausing cron"
    touch "$PAUSE_FILE"
    echo "GOAL_ACHIEVED" >> "$LOG_FILE"
else
    echo "$(date): [CONTINUING]"
    
    if [ -f "$ACTIVITY_FILE" ]; then
        echo "Recent progress (last 5 min):"
        # Show activity since last run (simple approach: last 8 lines)
        tail -8 "$ACTIVITY_FILE" | sed 's/^/  - /'
    else
        echo "  No activity logged yet"
    fi
fi

#!/bin/bash
# Autonomous Full-Lifecycle Development Loop for asktown-pf
# Stages: Goal → Validate → (Implement) → Push → Deploy

set -e
cd /root/asktown-finance

TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
echo "[$TIMESTAMP] === Autonomous Lifecycle Loop ===" >> logs/activity.log

CURRENT_GOAL=$(cat .current_goal 2>/dev/null || echo "")

if [ -z "$CURRENT_GOAL" ] || [ "$CURRENT_GOAL" = "No active goal" ]; then
    echo "[$TIMESTAMP] No active goal set. Nothing to do." >> logs/activity.log
    exit 0
fi

echo "[$TIMESTAMP] Goal: $CURRENT_GOAL" >> logs/activity.log

# Stage 1: Validation
echo "[$TIMESTAMP] Running validation..." >> logs/activity.log
if ! ./scripts/validate.sh >> logs/validation.log 2>&1; then
    echo "[$TIMESTAMP] Validation FAILED. Pausing implementation." >> logs/activity.log
    exit 1
fi

echo "[$TIMESTAMP] Validation PASSED." >> logs/activity.log

# Stage 2: Implementation placeholder
# In a full system this would trigger subagents or code generation.
# For now we log that implementation phase is ready.
echo "[$TIMESTAMP] Implementation phase ready (manual or subagent)." >> logs/activity.log

# Stage 3: Git Push (only if there are uncommitted changes)
if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "[$TIMESTAMP] Changes detected. Attempting push..." >> logs/activity.log
    if ./scripts/push_to_github.sh >> logs/activity.log 2>&1; then
        echo "[$TIMESTAMP] Push successful." >> logs/activity.log
    else
        echo "[$TIMESTAMP] Push failed." >> logs/activity.log
    fi
else
    echo "[$TIMESTAMP] No changes to push." >> logs/activity.log
fi

echo "[$TIMESTAMP] Lifecycle cycle complete." >> logs/activity.log
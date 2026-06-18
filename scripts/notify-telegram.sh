#!/bin/bash
# Simple Telegram notifier for cron updates
# This can be called from the autonomous loop when there is meaningful output

CHAT_ID="-5333887459"
MESSAGE="$1"

if [ -z "$MESSAGE" ]; then
    exit 0
fi

# Use curl to send to Telegram (requires bot token - placeholder for now)
# In production this would use the Hermes notification system
echo "[TELEGRAM NOTIFY] $MESSAGE" >> /root/asktown-finance/logs/activity.log

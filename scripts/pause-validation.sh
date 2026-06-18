#!/bin/bash
# Pause or resume validation monitoring

PAUSE_FILE="/root/asktown-finance/.pause_validation"

if [ "$1" = "pause" ]; then
    touch "$PAUSE_FILE"
    echo "Validation monitoring paused"
elif [ "$1" = "resume" ]; then
    rm -f "$PAUSE_FILE"
    echo "Validation monitoring resumed"
else
    echo "Usage: $0 [pause|resume]"
fi

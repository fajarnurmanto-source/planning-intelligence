<?php
// Local PI guardrails, not Google quota. Set 0 to prevent semantic egress.
return [
    'gemini_daily_requests'=>(int) env('PI_GEMINI_SEMANTIC_DAILY_REQUESTS',100),
    'gemini_daily_reservation_units'=>(int) env('PI_GEMINI_SEMANTIC_DAILY_RESERVATION_UNITS',300000),
];

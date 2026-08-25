<?php
class RateLimiter {
    public static function check(string $identifier, int $maxRequests = 60, int $windowMinutes = 1): bool {
        if (!filter_var(getenv('RATE_LIMIT_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN)) return true;
        $maxRequests = $maxRequests ?: (int)(getenv('RATE_LIMIT_MAX_REQUESTS') ?: 60);
        $windowMinutes = $windowMinutes ?: (int)(getenv('RATE_LIMIT_WINDOW_MINUTES') ?: 1);
        $timeSlot = floor(time() / ($windowMinutes * 60));
        $key = sys_get_temp_dir() . '/ratelimit_' . md5($identifier . '_' . $timeSlot) . '.tmp';
        $count = file_exists($key) ? (int)file_get_contents($key) : 0;
        if ($count >= $maxRequests) return false;
        file_put_contents($key, $count + 1);
        return true;
    }
}

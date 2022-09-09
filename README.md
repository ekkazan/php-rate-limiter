# PHP Rate Limiter
A simple PHP rate limiter based on Redis key-value pair noSQL in-memory database.

## Usage
```php
// Init RateLimiter
$rateLimiter = new RateLimiter();

// Set maximum number of request per minute as 100
$rateLimiter->setLimit('index', 60, 100);

// This way, Rate Limiter will call function named over_requested_fallback
// if a user exceed the limit.
$rateLimiter->setLimit('index', 60, 100, 'over_requested_fallback');
```

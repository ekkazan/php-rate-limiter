<?php
class RateLimiter {
  private Redis $redis;
  private string $userIP;
  
  /**
   * RateLimiter constructor. Set Redis port and host if different
   * than default port and localhost.
   * 
   * @param int $port
   * @param string $host
   */
  public function __construct(int $port = 6379, string $host = '127.0.0.1') {
    $this->redis = new Redis();

    $this->redis->connect($host, $port); 
    
    if(!$this->redis->ping()) {
      throw new RuntimeException('Redis connection could not be established.');
    }

    if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
      $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    $this->userIP = $ip;
  }

  /**
   * Set a limit for the specified page. Pages can be differentiated via using
   * $name parameter or same $name parameter can be used to group pages.
   * 
   * Also if $fallback parameter is specified, a special action can be taken
   * against over requested connections.
   * 
   * $interval parameter is in seconds
   * $maxRequest is the number of maximum request per $interval
   * 
   * for example: RateLimiter::setLimit('api', 60, 100) means if user exceed
   * 100 requests per minute he will get a HTTP 429 response or your fallback.
   * 
   * @param string $name
   * @param int $interval
   * @param int $maxRequest
   * @param callable $fallback
   * 
   * @return void
   */
  public function setLimit(string $name, int $interval, int $maxRequest, callable $fallback = '') {
    $ipToInt = str_replace('.', '', $this->userIP);

    $key = $name.':'.$ipToInt;

    if(!$this->redis->exists($key)) {
      $this->redis->set($key, 1);
      $this->redis->expire($key, $interval); 
    } else {
      $isValidRequest = $this->check($key, $maxRequest);

      if(!$isValidRequest) {
        if($fallback) {
          call_user_func($fallback);
        } else {
          http_response_code(429);
        }
        
        return;
      }

      $this->increase($key);
    }
  }

  /**
   * Increase the current request number of a user.
   * 
   * @param string $key
   * 
   * @return void
   */
  public function increase(string $key) {
    $currentRequests = (int) $this->redis->get($key);
    $timeleft = $this->redis->ttl($key);

    $increase = $currentRequests + 1;

    $this->redis->set($key, $increase);
    $this->redis->expire($key, $timeleft); 
  }

  /**
   * Check if a user exceeded maximum request number in interval.
   * 
   * @param string $key
   * @param int $maxRequest
   * 
   * @return bool
   */
  public function check(string $key, int $maxRequest) {
    $requests = (int) $this->redis->get($key);

    if($requests > $maxRequest) {
      return FALSE;
    }

    return TRUE;
  }
}

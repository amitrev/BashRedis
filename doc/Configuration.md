## BashRedis Documentation

### Step 1
```
# /config/packages/bash_cahce.yaml
bash_cache:
  clients:
    main:
      $options:
        expires:
          short: 60
      $parameters:
          dsn: tcp://127.0.0.1
          port: 6379
          database: 0
          timeout: 3
    counter:
      $options:
        prefix: sportal
        expires:
          long: 60
      $parameters:
        dsn: tcp://127.0.0.1
        port: 6379
        database: 1
        timeout: 3
```

### Step 2
```
# /config/service.yaml
    Bash\Bundle\CacheBundle\BashRedis\Client $mainRedis: '@bash_cache.main'
    Bash\Bundle\CacheBundle\BashRedis\Client $counterRedis: '@bash_cache.counter'
```

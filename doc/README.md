# BashRedis Documentation

```[
// values from config/packages/bash_redis.yaml
bash_cache:
  prefix: sportal
  main:
      scheme: tcp
      host: 127.0.0.1
      port: 6379
      db: 0
      timeout: 3
  counter:
      scheme: tcp
      host: 127.0.0.1
      port: 6379
      db: 1
      timeout: 3
  expires:
    short: 60
    medium: 120
    long: 3600
```

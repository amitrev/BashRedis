## BashRedis Documentation

### Step 1
```
# /config/packages/bash_cache.yaml
bash_cache:
  clients:
    main:
      $options:
        prefix: sportal
        expires:
          short: 60
      $parameters:
          dsn: tcp://127.0.0.1
          port: 6379
          database: 0
          timeout: 3
    counter:
      $options:
        persistent: counters
        prefix: sportal
        expires:
          long: 60
      $parameters:
        dsn: tcp://127.0.0.1
        port: 6379
        database: 1
        timeout: 3
    storage:
      $options:
        prefix: storage
        expires:
          long: 3600
      $parameters:
        dsn: tcp://127.0.0.1
        port: 6379
        database: 2
        timeout: 3

```

### Step 2
```
# /config/service.yaml
    Bash\Bundle\CacheBundle\BashRedis\Client $mainRedis: '@bash_cache.main'
    Bash\Bundle\CacheBundle\BashRedis\Client $counterRedis: '@bash_cache.counter'
```

or

```
class IndexController extends AbstractController
{
    /**
     * @Route("/",priority=100, methods="GET", name="homepage")
     */
    public function __invoke(ContainerInterface $container): Response
    {
        dump($container->get('bash_cache.main'));
        dump($container->get('bash_cache.counter'));
        dump($container->get('bash_cache.storage'));

        $number = rand(0, 100);

        return new Response(
            '<html><body>'.$number.'</body></html>'
        );
    }
}
```

### Step 3: Usage
```
class IndexController extends AbstractController
{
    /**
     * @Route("/",priority=100, methods="GET", name="homepage")
     */
    public function __invoke(Client $mainRedis): Response
    {
       $number = $mainRedis->get('number');

       if ($number === false) {
            $number = rand(0, 100);
            $mainRedis->set('number', $number, 60);
       }

        return new Response(
            '<html><body>'.$number.'</body></html>'
        );
    }
}
```

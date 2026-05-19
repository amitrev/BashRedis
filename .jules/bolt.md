## 2025-05-14 - Initial Scan of BashCacheBundle
**Learning:** Found that `preg_replace` is used for simple prefix removal, which is slower than basic string functions in PHP. Also noticed N+1 style Redis calls in `findAndHGetAll` and potentially inefficient array merging in `findAllKeys`.
**Action:** Use `strpos` and `substr` (or `str_starts_with` if PHP 8.0+) for prefix removal. Use Redis pipelines or multi-commands where possible to reduce roundtrips.

## 2025-05-14 - Redis SCAN behavior
**Learning:** Redis `SCAN` is iterative and the `COUNT` argument is only a hint. A single `SCAN` call might return zero keys even if matches exist, as long as the iterator is not 0.
**Action:** Always wrap `SCAN` in a loop checking the iterator until it's finished or the desired result is found.

## 2025-05-14 - Pipeline and Memory
**Learning:** While pipelines reduce latency, a pipeline that is too large can consume significant memory on both the PHP client and the Redis server.
**Action:** Process large datasets in chunked pipelines (e.g., 1000 items per pipeline) to balance latency gains with memory efficiency.

## 2025-05-14 - Lazy Connection and State Tracking
**Learning:** Lazy connection improves performance when services are injected but not used. Tracking internal states (like current serializer) avoids redundant `setOption` calls to the Redis extension.
**Action:** Defer `connect()` until the first command. Use class properties to mirror the Redis client state.

## 2025-05-14 - Correct phpredis SCAN termination
**Learning:** In `phpredis`, `scan()` does not return `false` to indicate completion. Instead, the iterator reference passed to the call is updated, and the loop should terminate when the iterator returns to `0`.
**Action:** Use `while(true)` or similar, calling `scan($iterator, ...)` and breaking when `(int)$iterator === 0`.

## 2025-05-14 - Internal State Caching
**Learning:** Calling extension methods like `isConnected()` or PHP functions like `strlen()` repeatedly in tight loops can add up.
**Action:** Use internal class properties to cache these values once they are known or changed.

## 2025-05-14 - Magic Method and Function Resolution Overhead
**Learning:** PHP magic methods like `__call` are significantly slower than direct method calls. Similarly, global functions should be imported or prefixed with `\` to avoid dynamic resolution in the global namespace.
**Action:** Implement frequently used Redis methods explicitly. Import all used functions at the top of the file.

## 2025-05-14 - Pipelined Read-and-Expire
**Learning:** Refreshing TTL on read (e.g., `get` + `expire` or `hGet` + `expire`) is a common pattern that can be optimized with pipelines to reduce roundtrips.
**Action:** Add `$expire` parameter to read methods and use pipelines when it's provided.

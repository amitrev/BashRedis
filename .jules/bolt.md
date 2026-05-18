## 2025-05-14 - Initial Scan of BashCacheBundle
**Learning:** Found that `preg_replace` is used for simple prefix removal, which is slower than basic string functions in PHP. Also noticed N+1 style Redis calls in `findAndHGetAll` and potentially inefficient array merging in `findAllKeys`.
**Action:** Use `strpos` and `substr` (or `str_starts_with` if PHP 8.0+) for prefix removal. Use Redis pipelines or multi-commands where possible to reduce roundtrips.

## 2025-05-14 - Redis SCAN behavior
**Learning:** Redis `SCAN` is iterative and the `COUNT` argument is only a hint. A single `SCAN` call might return zero keys even if matches exist, as long as the iterator is not 0.
**Action:** Always wrap `SCAN` in a loop checking the iterator until it's finished or the desired result is found.

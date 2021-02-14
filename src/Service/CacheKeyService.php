<?php

namespace Bash\Bundle\CacheBundle\Service;

class CacheKeyService
{
    public function getBaseKey(string $entitySlug, $criteria, $additional = null)
    {
        $baseKey = $criteria;
        $baseKey['base'] = $entitySlug;

        if (isset($additional)) {
            $baseKey['base'] .= '_' . $additional;
        }

        return $baseKey;
    }

    public function generateCacheKey($baseKey): string
    {
        $cacheKey = $baseKey;
        if (\is_array($baseKey)) {
            if (isset($baseKey['base'])) {
                $base = $baseKey['base'];
                unset($baseKey['base']);

                return $base . '_' . md5(json_encode($baseKey));
            }

            return md5(json_encode($baseKey));
        }

        return $cacheKey;
    }
}

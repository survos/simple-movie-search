<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Survos\GridGroupBundle\Service\CsvCache;
use App\Service\CsvDatabase;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\CallbackInterface;

/**
 * An CSV-based cache storage.
 *
 */
class CsvCacheAdapter implements AdapterInterface, CacheInterface, LoggerAwareInterface, ResettableInterface
{
    use LoggerAwareTrait;

    private CsvDatabase $csvDatabase;

    private static \Closure $createCacheItem;

    /**
     * @param bool $storeSerialized Disabling serialization can lead to cache corruptions when storing mutable values but increases performance otherwise
     */
    public function __construct(string $csvFilename, string $keyName, array $headers)
    {
        $this->csvDatabase = new CsvDatabase($csvFilename, $keyName, $headers);

        self::$createCacheItem ??= \Closure::bind(
            static function ($key, $value, $isHit, $tags) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;
                if (null !== $tags) {
                    $item->metadata[CacheItem::METADATA_TAGS] = $tags;
                }

                return $item;
            },
            null,
            CacheItem::class
        );
    }

    public function getItem(mixed $key): CacheItem
    {
        if (!$isHit = $this->hasItem($key)) {
            $value = null;
        } else {
            return (self::$createCacheItem)($key, $this->csvDatabase->get($key), $isHit, $this->tags[$key] ?? null);
        }

        return (self::$createCacheItem)($key, $value, $isHit, $this->tags[$key] ?? null);
    }

    public function getItems(array $keys = []): iterable
    {
        $data = [];

        foreach ($keys as $key) {
            $data[] = $this->getItem($key);
        }

        return $data;
    }

    public function clear(string $prefix = ''): bool
    {
        $this->csvDatabase->flushFile();

        return true;
    }

    public function get(string $key, callable $callback, float $beta = null, array &$metadata = null): mixed
    {
        $item = ($callback)($this->getItem($key));
        if (!$isHit = $this->hasItem($key)) {
            $this->csvDatabase->set($key, (array) $item);

            return (self::$createCacheItem)($key, $item, $isHit, $this->tags[$key] ?? null);
        }

        return $this->getItem($key);
    }

    public function delete(string $key): bool
    {
        $this->csvDatabase->delete($key);

        return true;
    }

    public function hasItem(string $key): bool
    {
        return $this->csvDatabase->has($key);
    }

    public function deleteItem(string $key): bool
    {
        $this->csvDatabase->delete($key);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        // TODO: Implement save() method.
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        // TODO: Implement saveDeferred() method.
    }

    public function commit(): bool
    {
        // TODO: Implement commit() method.
    }

    public function reset(): void
    {
        // TODO: Implement reset() method.
    }
}

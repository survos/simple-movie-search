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


    private CsvCache $csvCache;

    /**
     * @param bool $storeSerialized Disabling serialization can lead to cache corruptions when storing mutable values but increases performance otherwise
     */
    public function __construct(string $csvFilenamem, string $keyName, array $headers)
    {
        $this->csvCache = new CsvCache($csvFilenamem, ['keyName' => $keyName, 'headers' => $headers]);

    }

    public function getItem(mixed $key): CacheItem
    {
        $data = $this->csvCache->get($key);
        // TODO: Implement getItem() method.
    }

    public function getItems(array $keys = []): iterable
    {
        // TODO: Implement getItems() method.
    }

    public function clear(string $prefix = ''): bool
    {
        // TODO: Implement clear() method.
    }

    public function get(string $key, callable $callback, float $beta = null, array &$metadata = null): mixed
    {
        // TODO: Implement get() method.
    }

    public function delete(string $key): bool
    {
        // TODO: Implement delete() method.
    }

    public function hasItem(string $key): bool
    {
        // TODO: Implement hasItem() method.
    }

    public function deleteItem(string $key): bool
    {
        // TODO: Implement deleteItem() method.
    }

    public function deleteItems(array $keys): bool
    {
        // TODO: Implement deleteItems() method.
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

    public function reset()
    {
        // TODO: Implement reset() method.
    }


}

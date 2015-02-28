<?php

/*
 * This file is part of the RollerworksSearch Component package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Rollerworks\Component\Search\Doctrine\Dbal;

use Doctrine\Common\Cache\Cache;
use Rollerworks\Component\Search\Exception\BadMethodCallException;

/***
 * Handles caching of the Doctrine DBAL WhereBuilder.
 *
 * Note. For best performance caching of the WhereClause should be done on a
 * per user-session FieldSet basis. This ensures enough uniqueness and
 * no complex serialization.
 *
 * This checks if there is a cached result, if not it delegates
 * the creating to the parent and caches the result.
 *
 * Instead of calling getWhereClause() on the WhereBuilder class
 * you should call getWhereClause() on this class instead.
 *
 * WARNING. Any changes to the mapping-data should invalidate the cache,
 * the system doesn't do this automatically.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class CacheWhereBuilder implements WhereBuilderInterface
{
    /**
     * @var Cache
     */
    private $cacheDriver;

    /**
     * @var int
     */
    private $cacheLifeTime;

    /**
     * @var WhereBuilderInterface
     */
    private $whereBuilder;

    /**
     * @var string
     */
    private $cacheKey;

    /**
     * @var string
     */
    private $keySuffix = '';

    /**
     * @var string
     */
    private $whereClause;

    /**
     * Constructor.
     *
     * @param WhereBuilderInterface $whereBuilder The WhereBuilder to use for generating and updating the query
     * @param Cache                 $cacheDriver  Doctrine Cache instance
     * @param int                   $lifeTime     Lifetime in seconds after which the cache is expired
     *                                            Set this 0 to never expire.
     */
    public function __construct(WhereBuilderInterface $whereBuilder, Cache $cacheDriver, $lifeTime = 0)
    {
        $this->cacheDriver = $cacheDriver;
        $this->cacheLifeTime = (int) $lifeTime;
        $this->whereBuilder = $whereBuilder;
    }

    /**
     * Set the cache key.
     *
     * This method also accepts a callback that can calculate the key for you.
     * The callback will receive wherebuilder.
     *
     * @param string   $key
     * @param callable $callback
     *
     * @return self
     *
     * @throws BadMethodCallException
     */
    public function setCacheKey($key = null, $callback = null)
    {
        if ((null === $key && null === $callback) || ($callback && !is_callable($callback))) {
            throw new BadMethodCallException('Either a key or legal callback must be given.');
        }

        if ($callback) {
            $key = call_user_func($callback, $this->whereBuilder);
        }

        $this->cacheKey = (string) $key;

        return $this;
    }

    /**
     * Set an extra suffix for the caching key.
     *
     * This allows to make the key more unique.
     * For example, you can set the key to calculate automatically,
     * and add this suffix to ensure there is no problem with different mapping.
     *
     * @param string $key
     *
     * @return self
     *
     * @deprecated since version 1.0.0-beta7, to be removed in 2.0.
     *             Use setCacheKey() instead with a unique key.
     */
    public function setCacheKeySuffix($key)
    {
        $this->keySuffix = $key.'_';

        return $this;
    }

    /**
     * Returns the generated/cached where-clause.
     *
     * @see WhereBuilder::getWhereClause()
     *
     * @return string
     */
    public function getWhereClause()
    {
        if ($this->whereClause) {
            return $this->whereClause;
        }

        $cacheKey = 'rw_search.doctrine.dbal.where.'.$this->cacheKey;

        if ($this->cacheDriver->contains($cacheKey)) {
            $this->whereClause = $this->cacheDriver->fetch($cacheKey);
        } else {
            $this->whereClause = $this->whereBuilder->getWhereClause();

            $this->cacheDriver->save(
                $cacheKey,
                $this->whereClause,
                $this->cacheLifeTime
            );
        }

        return $this->whereClause;
    }

    /**
     * Returns the original WhereBuilder that is used for generating
     * the where-clause.
     *
     * @return WhereBuilderInterface
     */
    public function getInnerWhereBuilder()
    {
        return $this->whereBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchCondition()
    {
        return $this->whereBuilder->getSearchCondition();
    }
}
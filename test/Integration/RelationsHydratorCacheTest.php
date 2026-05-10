<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

use ContenirTest\Db\Model\TestAsset\OrderEntity;
use Laminas\Db\Adapter\Profiler\Profiler;

class RelationsHydratorCacheTest extends IntegrationTestCase
{
    public function testRepeatedFkLookupsHitTheCache(): void
    {
        $profiler = new Profiler();
        $this->adapter->getDriver()->setProfiler($profiler);

        // orders 1 + 2 both belong to user 1; order 3 belongs to user 2.
        // Without the cache, iterating and reading $order->user would issue
        // three SELECTs against the users table. With the cache, only two
        // (one for user 1, one for user 2).
        $orders = iterator_to_array($this->orders->find());

        $beforeCount = count($profiler->getProfiles());

        foreach ($orders as $order) {
            $this->assertNotNull($order->user);
        }

        $userQueries = 0;
        foreach (array_slice($profiler->getProfiles(), $beforeCount) as $entry) {
            $sql = $entry['sql'] ?? '';
            if (stripos($sql, 'FROM "users"') !== false || stripos($sql, 'FROM users') !== false) {
                $userQueries++;
            }
        }

        $this->assertSame(2, $userQueries);
    }

    public function testCacheReturnsIdenticalInstanceForSameLookup(): void
    {
        $orders = iterator_to_array($this->orders->find());

        $userOfFirstOrder  = $orders[0]->user;
        $userOfSecondOrder = $orders[1]->user;

        $this->assertSame($userOfFirstOrder, $userOfSecondOrder);
        $this->assertInstanceOf(OrderEntity::class, $orders[0]);
    }
}

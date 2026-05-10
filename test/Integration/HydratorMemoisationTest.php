<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

use Laminas\Db\Adapter\Profiler\Profiler;

use function array_slice;
use function count;
use function iterator_to_array;
use function stripos;

class HydratorMemoisationTest extends AbstractIntegrationTestCase
{
    public function testGetHydratorReturnsTheSameInstanceAcrossCalls(): void
    {
        $first  = $this->users->getHydrator();
        $second = $this->users->getHydrator();

        $this->assertSame($first, $second);
    }

    public function testRelationCacheSpansSeparateFindCalls(): void
    {
        $profiler = new Profiler();
        $this->adapter->getDriver()->setProfiler($profiler);

        // First find: caches user 1 + user 2.
        foreach (iterator_to_array($this->orders->find(['user_id' => 1])) as $order) {
            $this->assertNotNull($order->user);
        }

        $beforeSecond = count($profiler->getProfiles());

        // Second find on the same repository: relation cache should still
        // be warm; touching $order->user must not requery the users table.
        foreach (iterator_to_array($this->orders->find(['user_id' => 1])) as $order) {
            $this->assertNotNull($order->user);
        }

        $afterSecond = count($profiler->getProfiles());
        $newProfiles = array_slice($profiler->getProfiles(), $beforeSecond, $afterSecond - $beforeSecond);

        $userQueries = 0;
        foreach ($newProfiles as $entry) {
            $sql = $entry['sql'] ?? '';
            if (stripos($sql, 'FROM "users"') !== false || stripos($sql, 'FROM users') !== false) {
                $userQueries++;
            }
        }

        $this->assertSame(0, $userQueries);
    }

    public function testCacheIsClearedOnWritesFromTheSameRepository(): void
    {
        $profiler = new Profiler();
        $this->adapter->getDriver()->setProfiler($profiler);

        // Warm the cache.
        foreach (iterator_to_array($this->orders->find()) as $order) {
            $this->assertNotNull($order->user);
        }

        // A write from the orders repository must invalidate its own
        // RelationsHydrator cache so the next iteration re-reads users.
        $this->orders->insert(['user_id' => 1, 'total' => 1, 'created_at' => '2024-12-31']);

        $beforeRereads = count($profiler->getProfiles());

        foreach (iterator_to_array($this->orders->find()) as $order) {
            $this->assertNotNull($order->user);
        }

        $afterRereads = count($profiler->getProfiles());
        $newProfiles  = array_slice($profiler->getProfiles(), $beforeRereads, $afterRereads - $beforeRereads);

        $userQueries = 0;
        foreach ($newProfiles as $entry) {
            $sql = $entry['sql'] ?? '';
            if (stripos($sql, 'FROM "users"') !== false || stripos($sql, 'FROM users') !== false) {
                $userQueries++;
            }
        }

        // Two distinct user FK targets in the result (user 1 and user 2),
        // both must be re-fetched.
        $this->assertSame(2, $userQueries);
    }
}

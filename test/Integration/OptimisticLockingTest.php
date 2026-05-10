<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

use Contenir\Db\Model\Exception\StaleEntityException;
use ContenirTest\Db\Model\TestAsset\VersionedWidgetEntity;

class OptimisticLockingTest extends AbstractIntegrationTestCase
{
    public function testSuccessfulUpdateBumpsTheVersion(): void
    {
        /** @var VersionedWidgetEntity $widget */
        $widget       = $this->widgets->findOne(['id' => 1]);
        $widget->name = 'Renamed';

        $this->widgets->save($widget);

        $reloaded = $this->widgets->findOne(['id' => 1]);
        $this->assertSame('Renamed', $reloaded->name);
        $this->assertSame(2, (int) $reloaded->version);
        // The in-memory entity also reflects the bumped version.
        $this->assertSame(2, (int) $widget->version);
    }

    public function testConcurrentUpdateIsRejectedWithStaleEntityException(): void
    {
        /** @var VersionedWidgetEntity $a */
        $a = $this->widgets->findOne(['id' => 1]);
        /** @var VersionedWidgetEntity $b */
        $b = $this->widgets->findOne(['id' => 1]);

        $a->name = 'First';
        $this->widgets->save($a);

        $b->name = 'Second';

        $this->expectException(StaleEntityException::class);
        $this->expectExceptionMessage('Optimistic-lock failure');
        $this->widgets->save($b);
    }

    public function testStaleEntityExceptionLeavesPersistedRowUntouched(): void
    {
        /** @var VersionedWidgetEntity $a */
        $a = $this->widgets->findOne(['id' => 1]);
        /** @var VersionedWidgetEntity $b */
        $b = $this->widgets->findOne(['id' => 1]);

        $a->name = 'First';
        $this->widgets->save($a);

        $b->name = 'Second';
        try {
            $this->widgets->save($b);
            $this->fail('expected StaleEntityException');
        } catch (StaleEntityException) {
            // expected
        }

        $reloaded = $this->widgets->findOne(['id' => 1]);
        $this->assertSame('First', $reloaded->name);
        $this->assertSame(2, (int) $reloaded->version);
    }
}

<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

use ContenirTest\Db\Model\Trait\SqliteAdapterTrait;
use PhpDb\Sql\Sql;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Group('integration')]
final class SqliteAdapterSmokeTest extends TestCase
{
    use SqliteAdapterTrait;

    #[Test]
    public function insertedRowIsReadBackThroughPhpDbSql(): void
    {
        $sql = new Sql($this->adapter, 'widgets');
        $sql->prepareStatementForSqlObject($sql->insert()->values(['name' => 'sprocket']))->execute();

        $result = $sql->prepareStatementForSqlObject($sql->select()->where(['name' => 'sprocket']))->execute();

        static::assertSame(['id' => 1, 'name' => 'sprocket'], $result->current());
    }

    protected function setUp(): void
    {
        $this->setUpSqliteAdapter('CREATE TABLE widgets (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    }
}

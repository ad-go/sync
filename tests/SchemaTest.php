<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use CodeIgniter\Database\Config as DbConfig;
use AdGo\Sync\Database\SyncSchema;

final class SchemaTest extends \PHPUnit\Framework\TestCase
{
    public function testDownRestoresTheDatabaseExactly(): void
    {
        $db    = DbConfig::connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBDebug' => true], false);
        $forge = DbConfig::forge($db);
        $db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $before = $db->listTables();

        SyncSchema::up($forge);
        $this->assertEqualsCanonicalizing(array_merge($before, SyncSchema::TABLES), array_filter($db->listTables(), fn ($t) => $t !== 'sqlite_sequence'));

        SyncSchema::down($forge);
        $this->assertEqualsCanonicalizing($before, array_filter($db->listTables(), fn ($t) => $t !== 'sqlite_sequence'));
        $this->assertSame([], $db->getFieldNames('users') === ['id', 'name'] ? [] : ['users table changed']);
    }
}

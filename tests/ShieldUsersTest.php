<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use CodeIgniter\Database\Config as DbConfig;
use AdGo\Sync\Libraries\Options;

final class ShieldUsersTest extends TwoNodes
{
    protected function tables(): array
    {
        return ['items' => ['policy' => 'tolerant']] + Options::SHIELD_USERS;
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([$this->a, $this->b] as $n) {
            $f = DbConfig::forge($n->db);
            $f->addField([
                'id'          => ['type' => 'INTEGER', 'auto_increment' => true],
                'username'    => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'status'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'active'      => ['type' => 'INTEGER', 'default' => 0],
                'last_active' => ['type' => 'DATETIME', 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $f->addKey('id', true);
            $f->createTable('users');
            $f->addField([
                'id'           => ['type' => 'INTEGER', 'auto_increment' => true],
                'user_id'      => ['type' => 'INTEGER'],
                'type'         => ['type' => 'VARCHAR', 'constraint' => 255],
                'name'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'secret'       => ['type' => 'VARCHAR', 'constraint' => 255],
                'secret2'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'last_used_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $f->addKey('id', true);
            $f->createTable('auth_identities');
            $f->addField([
                'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
                'user_id'    => ['type' => 'INTEGER'],
                'group'      => ['type' => 'VARCHAR', 'constraint' => 255],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $f->addKey('id', true);
            $f->createTable('auth_groups_users');
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int} user id, identity id, group row id
     */
    private function addUser(Node $n, string $name, ?int $id = null): array
    {
        $row = ['username' => $name, 'active' => 1, 'created_at' => '2026-10-15 10:00:00'];

        if ($id !== null) {
            $row['id'] = $id;
        }

        $n->db->table('users')->insert($row);
        $uid = (int) $n->db->insertID();
        $n->db->table('auth_identities')->insert(['user_id' => $uid, 'type' => 'email_password', 'secret' => "{$name}@x.test", 'secret2' => 'hash-1', 'created_at' => '2026-10-15 10:00:00']);
        $iid = (int) $n->db->insertID();
        $n->db->table('auth_groups_users')->insert(['user_id' => $uid, 'group' => 'user', 'created_at' => '2026-10-15 10:00:00']);
        $gid = (int) $n->db->insertID();
        $n->k->scanner()->scan();

        return [$uid, $iid, $gid];
    }

    private function addAdminWithoutScan(Node $n): void
    {
        $n->db->table('users')->insert(['id' => 1, 'username' => 'admin', 'active' => 1, 'created_at' => '2026-10-14 19:00:00']);
        $n->db->table('auth_identities')->insert(['id' => 1, 'user_id' => 1, 'type' => 'email_password', 'secret' => 'a@x.test', 'secret2' => 'h0', 'created_at' => '2026-10-14 19:00:00']);
        $n->db->table('auth_groups_users')->insert(['id' => 1, 'user_id' => 1, 'group' => 'superadmin', 'created_at' => '2026-10-14 19:00:00']);
    }

    public function testExistingUsersAreAdoptedByTheDeterministicSeedWithoutTraffic(): void
    {
        foreach ([$this->a, $this->b] as $n) {
            $this->addAdminWithoutScan($n);
            $n->k->scanner()->seed();
        }

        // the first scan after seeding must find nothing to report
        $this->a->k->scanner()->scan();
        $this->b->k->scanner()->scan();
        $this->assertSame([], $this->a->k->meta->changesSince(0, 100));
        $this->assertSame([], $this->b->k->meta->changesSince(0, 100));
        $this->sync();
        $this->assertSame([], $this->a->conflicts());
        $this->assertSame([], $this->b->conflicts());
        $this->assertSame($this->a->k->meta->findByRow('users', 1)['uuid'], $this->b->k->meta->findByRow('users', 1)['uuid']);
    }

    public function testScanningBeforeSeedingWouldDuplicateUsers(): void
    {
        // Documents WHY seeding must come first: an unseeded scan gives the rows random uuids.
        foreach ([$this->a, $this->b] as $n) {
            $this->addAdminWithoutScan($n);
            $n->k->scanner()->scan();
        }

        $this->assertNotSame($this->a->k->meta->findByRow('users', 1)['uuid'], $this->b->k->meta->findByRow('users', 1)['uuid']);
    }

    public function testNewUserWithIdentityAndGroupReachesThePeerWithItsOwnLocalIds(): void
    {
        // The peer used a low id for something else before, so local ids cannot match.
        $this->b->db->table('users')->insert(['id' => 1, 'username' => 'temp', 'active' => 1]);
        $this->b->k->scanner()->scan();
        $this->b->db->table('users')->where('id', 1)->delete();
        $this->b->k->scanner()->scan();

        [$uid] = $this->addUser($this->a, 'maria', 40);
        $this->sync();

        $bu = $this->b->db->table('users')->where('username', 'maria')->get()->getRowArray();
        $this->assertNotNull($bu);
        $this->assertNotSame($uid, (int) $bu['id'], 'the primary key is local to each node');

        $bi = $this->b->db->table('auth_identities')->where('secret', 'maria@x.test')->get()->getRowArray();
        $this->assertSame((int) $bu['id'], (int) $bi['user_id'], "identity points at the peer's local user id");
        $this->assertSame('hash-1', $bi['secret2']);

        $bg = $this->b->db->table('auth_groups_users')->where('user_id', $bu['id'])->get()->getRowArray();
        $this->assertSame('user', $bg['group']);
    }

    public function testPasswordChangeGroupChangeAndRemovalPropagate(): void
    {
        [$uid, $iid, $gid] = $this->addUser($this->a, 'ion');
        $this->sync();
        $this->tick();

        $this->a->db->table('auth_identities')->where('id', $iid)->update(['secret2' => 'hash-2']);
        $this->a->db->table('auth_groups_users')->insert(['user_id' => $uid, 'group' => 'admin']);
        $this->a->db->table('auth_groups_users')->where('id', $gid)->delete();
        $this->a->k->scanner()->scan();
        $this->sync();

        $bu = $this->b->db->table('users')->where('username', 'ion')->get()->getRowArray();
        $this->assertSame('hash-2', $this->b->db->table('auth_identities')->where('user_id', $bu['id'])->get()->getRowArray()['secret2']);
        $this->assertSame(['admin'], array_column($this->b->db->table('auth_groups_users')->where('user_id', $bu['id'])->get()->getResultArray(), 'group'));

        // soft delete (Shield) travels as an ordinary field
        $this->tick();
        $this->a->db->table('users')->where('id', $uid)->update(['deleted_at' => '2026-10-16 00:00:00']);
        $this->a->k->scanner()->scan();
        $this->sync();
        $this->assertSame('2026-10-16 00:00:00', $this->b->db->table('users')->where('id', $bu['id'])->get()->getRowArray()['deleted_at']);
        $this->assertSame([], $this->a->conflicts());
        $this->assertSame([], $this->b->conflicts());
    }

    public function testLoginBookkeepingIsNotAChange(): void
    {
        $this->addUser($this->a, 'ana');
        $this->sync();
        $before = count($this->b->k->meta->changesSince(0, 1000));

        $this->tick();
        $this->b->db->table('users')->update(['last_active' => '2026-10-16 08:00:00', 'updated_at' => '2026-10-16 08:00:00']);
        $this->b->db->table('auth_identities')->update(['last_used_at' => '2026-10-16 08:00:00', 'updated_at' => '2026-10-16 08:00:00']);
        $this->b->k->scanner()->scan();

        $this->assertSame($before, count($this->b->k->meta->changesSince(0, 1000)), 'last_active / last_used_at must not generate sync traffic');
    }

    public function testConflictingPasswordEditsAreEscalatedNotOverwritten(): void
    {
        [, $iid] = $this->addUser($this->a, 'dan');
        $this->sync();
        $bi = $this->b->db->table('auth_identities')->where('secret', 'dan@x.test')->get()->getRowArray();

        $this->tick();
        $this->a->db->table('auth_identities')->where('id', $iid)->update(['secret2' => 'hash-A']);
        $this->a->k->scanner()->scan();
        $this->tick();
        $this->b->db->table('auth_identities')->where('id', $bi['id'])->update(['secret2' => 'hash-B']);
        $this->b->k->scanner()->scan();
        $this->sync();

        $this->assertNotEmpty(array_merge($this->a->conflicts('pending'), $this->b->conflicts('pending')));
        $this->assertSame('hash-A', $this->a->db->table('auth_identities')->where('id', $iid)->get()->getRowArray()['secret2']);
        $this->assertSame('hash-B', $this->b->db->table('auth_identities')->where('id', $bi['id'])->get()->getRowArray()['secret2']);
    }
}

<?php

namespace Tests\Concerns;

use PDO;

/**
 * Mode SQL membuka file database sendiri (read-only), sehingga tidak bisa
 * melihat database :memory: milik test. Trait ini membuat file SQLite
 * sementara berisi tabel data dan mengarahkan SqlRunner ke file itu.
 */
trait UsesSqlFixtureDatabase
{
    private ?string $sqlFixturePath = null;

    protected function sqlFixture(): string
    {
        $this->sqlFixturePath = sys_get_temp_dir().'/'.uniqid('sql_fixture_', true).'.sqlite';

        $pdo = new PDO('sqlite:'.$this->sqlFixturePath);
        $pdo->exec('CREATE TABLE ds_1 (_row_id INTEGER PRIMARY KEY, kecamatan TEXT, status TEXT, jumlah_art INTEGER, tanggal TEXT)');
        $pdo->exec("INSERT INTO ds_1 (kecamatan, status, jumlah_art, tanggal) VALUES
            ('Bangli', 'SUBMITTED', 4, '2026-06-01'),
            ('Bangli', 'OPEN', 3, '2026-06-02'),
            ('Susut', 'SUBMITTED', 5, '2026-06-03')");
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, password TEXT)');
        $pdo->exec("INSERT INTO users (email, password) VALUES ('admin@x.id', 'hash-rahasia')");

        config(['database.connections.sqlite.database' => $this->sqlFixturePath]);

        return $this->sqlFixturePath;
    }

    protected function tearDownSqlFixture(): void
    {
        if ($this->sqlFixturePath !== null) {
            @unlink($this->sqlFixturePath);
        }
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CreateCanonicalSchema
 *
 * Baseline migration that creates all tables in the current ekklesiaRD schema.
 */
class CreateCanonicalSchema extends Migration
{
    private string $sqlFile = __DIR__ . '/../canonical_schema_tables.sql';

    public function up()
    {
        // The canonical schema dump is MySQL-specific. Skip it for non-MySQL
        // drivers (e.g. the SQLite in-memory test database) so tests can build
        // only the tables they need.
        if ($this->db->DBDriver !== 'MySQLi' && $this->db->DBDriver !== 'mysql') {
            return;
        }

        if (!file_exists($this->sqlFile)) {
            throw new \RuntimeException("Canonical schema file not found: {$this->sqlFile}");
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS=0');

        try {
            $sql = file_get_contents($this->sqlFile);
            $statements = $this->parseSqlStatements($sql);

            foreach ($statements as $statement) {
                if ($this->shouldExecute($statement)) {
                    try {
                        $this->db->query($statement);
                    } catch (\Throwable $e) {
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            throw $e;
                        }
                    }
                }
            }
        } finally {
            $this->db->query('SET FOREIGN_KEY_CHECKS=1');
        }

        // The dump just created ~140 tables outside the forge, so the
        // connection's cached table list is stale. Later migrations guard on
        // tableExists(), which would read the stale cache and try to recreate
        // existing tables.
        $this->db->resetDataCache();
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'MySQLi' && $this->db->DBDriver !== 'mysql') {
            return;
        }

        $tables = $this->getAllTables();
        foreach (array_reverse($tables) as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function getAllTables(): array
    {
        $result = $this->db->query("SHOW TABLES")->getResultArray();
        $tables = [];
        foreach ($result as $row) {
            $tables[] = reset($row);
        }
        return $tables;
    }

    private function shouldExecute(string $sql): bool
    {
        $sql = trim($sql);
        if ($sql === '') return false;
        return (stripos($sql, 'CREATE TABLE') === 0);
    }

    private function parseSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $escaped = false;

        $lines = explode("\n", $sql);
        foreach ($lines as $line) {
            $strippedLine = $line;

            // Handle comments on the line if not inside a string
            if (!$inString) {
                $commentPos = strpos($line, '--');
                if ($commentPos !== false) {
                    $strippedLine = substr($line, 0, $commentPos);
                }
            }

            $len = strlen($strippedLine);
            for ($i = 0; $i < $len; $i++) {
                $char = $strippedLine[$i];
                $current .= $char;

                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($inString) {
                    if ($char === $stringChar) {
                        $inString = false;
                    }
                } else {
                    if ($char === "'" || $char === '"') {
                        $inString = true;
                        $stringChar = $char;
                    } elseif ($char === ';') {
                        $statements[] = trim($current);
                        $current = '';
                    }
                }
            }
            // Add newline that was lost by explode
            if (!$inString || $current !== '') {
                $current .= "\n";
            }
        }

        if (trim($current) !== '') {
            $statements[] = trim($current);
        }

        return $statements;
    }
}

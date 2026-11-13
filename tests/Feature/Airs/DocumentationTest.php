<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use PHPUnit\Framework\TestCase;

final class DocumentationTest extends TestCase
{
    private function root(string $path): string
    {
        return dirname(__DIR__, 3).DIRECTORY_SEPARATOR.$path;
    }

    public function test_adr_383_exists_and_is_indexed(): void
    {
        $this->assertFileExists($this->root('docs/adr/ADR-383-airs-merged-into-indecs.md'));
        $this->assertStringContainsString('ADR-383', (string) file_get_contents($this->root('docs/adr/README.md')));
    }

    public function test_runbook_no_longer_describes_http_connector(): void
    {
        $runbook = (string) file_get_contents($this->root('docs/runbooks/airs-integration.md'));
        $this->assertStringNotContainsString('AIRS_SERVICE_TOKEN', $runbook);
        $this->assertStringNotContainsString('airs:sync', $runbook);
        $this->assertStringContainsString('airs:recover-checks', $runbook);
        $this->assertStringContainsString('paid-check:hand-over-expired', $runbook);
    }

    public function test_env_example_documents_db_timezone(): void
    {
        $this->assertStringContainsString('DB_TIMEZONE=UTC', (string) file_get_contents($this->root('.env.example')));
    }
}

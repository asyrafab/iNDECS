<?php

namespace App\Console\Commands;

use App\Services\Airs\AirsClient;
use Illuminate\Console\Command;

final class AirsConnectionCheck extends Command
{
    protected $signature = 'airs:check';

    protected $description = 'Check authenticated AIRS connectivity without submitting documents or calling AI';

    public function handle(AirsClient $client): int
    {
        try {
            $capabilities = $client->capabilities();
            if (($capabilities['api_version'] ?? null) !== 'v1') {
                throw new \RuntimeException;
            }
            $this->info('AIRS v1 disambungkan. Modul: '.implode(', ', $capabilities['modules'] ?? []));

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('AIRS tidak tersedia atau konfigurasi/token tidak sah.');

            return self::FAILURE;
        }
    }
}

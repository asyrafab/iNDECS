<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DocumentCatalogueService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/** Publishes a department-supplied document checklist from a reviewed JSON file (same payload as the catalogue screen). */
final class PublishDocumentCatalogue extends Command
{
    protected $signature = 'catalogue:publish {type : Application type, e.g. LJUP} {file : JSON with version, source_reference and items} {--actor= : E-mail of the authorised officer publishing it}';

    protected $description = 'Publish a department document checklist version from a JSON file';

    public function handle(DocumentCatalogueService $catalogues): int
    {
        $actor = User::query()->where('email', (string) $this->option('actor'))->first();
        if ($actor === null) {
            $this->error('Pegawai (--actor) tidak dijumpai.');

            return self::FAILURE;
        }
        $file = (string) $this->argument('file');
        $payload = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($payload)) {
            $this->error('Fail katalog tidak sah.');

            return self::FAILURE;
        }
        try {
            $catalogues->publish(strtoupper((string) $this->argument('type')), $payload, $actor);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                $this->error(implode(' ', $messages));
            }

            return self::FAILURE;
        }
        $this->info('Katalog diterbitkan.');

        return self::SUCCESS;
    }
}

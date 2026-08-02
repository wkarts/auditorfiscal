<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class VerifyObjectStorage extends Command
{
    protected $signature = 'storage:verify';

    protected $description = 'Valida escrita, leitura e remoção no armazenamento de objetos';

    public function handle(): int
    {
        $disk = Storage::disk(config('filesystems.default'));
        $path = 'health/'.Str::uuid().'.txt';
        $contents = 'auditor-fiscal-storage-check';
        $written = false;

        try {
            if (! $disk->put($path, $contents)) {
                throw new RuntimeException('O armazenamento recusou a escrita do arquivo de verificação.');
            }
            $written = true;

            if ($disk->get($path) !== $contents) {
                throw new RuntimeException('O conteúdo lido do armazenamento diverge do conteúdo gravado.');
            }
        } finally {
            if ($written) {
                $disk->delete($path);
            }
        }

        $this->components->info('Armazenamento de objetos pronto para leitura e escrita.');

        return self::SUCCESS;
    }
}

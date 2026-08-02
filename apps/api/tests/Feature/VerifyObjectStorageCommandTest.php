<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerifyObjectStorageCommandTest extends TestCase
{
    public function test_it_validates_storage_without_leaving_probe_files(): void
    {
        Storage::fake('verification');
        config(['filesystems.default' => 'verification']);

        $this->artisan('storage:verify')
            ->expectsOutputToContain('Armazenamento de objetos pronto')
            ->assertSuccessful();

        Storage::disk('verification')->assertDirectoryEmpty('health');
    }
}

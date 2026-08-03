<?php

namespace Tests\Unit;

use App\Models\AnalysisBatch;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalysisBatchReprocessPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_failed_and_completed_batches_can_be_reprocessed(): void
    {
        $this->assertTrue($this->batch('failed')->canBeReprocessed());
        $this->assertTrue($this->batch('completed')->canBeReprocessed());
    }

    public function test_only_stale_queued_batches_can_be_reprocessed(): void
    {
        Carbon::setTestNow('2026-08-03 12:00:00');
        config(['analysis.stale_queue_minutes' => 15]);

        $recent = $this->batch('queued', now()->subMinutes(14));
        $stale = $this->batch('queued', now()->subMinutes(16));

        $this->assertFalse($recent->canBeReprocessed());
        $this->assertTrue($stale->canBeReprocessed());
    }

    public function test_processing_and_superseded_batches_cannot_be_reprocessed(): void
    {
        $this->assertFalse($this->batch('processing')->canBeReprocessed());
        $this->assertFalse($this->batch('retrying')->canBeReprocessed());
        $this->assertFalse($this->batch('superseded')->canBeReprocessed());
    }

    private function batch(string $status, $updatedAt = null): AnalysisBatch
    {
        $batch = new AnalysisBatch(['status' => $status]);
        $batch->updated_at = $updatedAt ?? now();

        return $batch;
    }
}

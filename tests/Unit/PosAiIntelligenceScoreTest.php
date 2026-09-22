<?php

namespace Tests\Unit;

use App\Services\JapsAi\PosAiIntelligenceService;
use PHPUnit\Framework\TestCase;

class PosAiIntelligenceScoreTest extends TestCase
{
    public function test_collection_score_rises_with_age_lateness_and_size(): void
    {
        $low = PosAiIntelligenceService::collectionScore(5, 10, 100, 1000);
        $high = PosAiIntelligenceService::collectionScore(90, 60, 1000, 1000);

        $this->assertGreaterThan($low, $high);
        $this->assertGreaterThanOrEqual(0, $low);
        $this->assertLessThanOrEqual(100, $high);
    }

    public function test_forecast_confidence_refuses_thin_history(): void
    {
        $this->assertSame('insufficient', PosAiIntelligenceService::forecastConfidence(2, true));
        $this->assertSame('low', PosAiIntelligenceService::forecastConfidence(5, true));
        $this->assertSame('medium', PosAiIntelligenceService::forecastConfidence(12, false));
        $this->assertSame('high', PosAiIntelligenceService::forecastConfidence(12, true));
    }

    public function test_suggested_qty_covers_lead_time_and_does_not_go_negative(): void
    {
        $this->assertSame(0.0, PosAiIntelligenceService::suggestedQty(1, 100, 0, 7));
        $this->assertGreaterThan(0, PosAiIntelligenceService::suggestedQty(2, 5, 0, 7));
    }

    public function test_tolerance_accepts_small_variance_only(): void
    {
        $this->assertTrue(PosAiIntelligenceService::withinTolerance(100, 101, 0.02));
        $this->assertFalse(PosAiIntelligenceService::withinTolerance(100, 110, 0.02));
        $this->assertTrue(PosAiIntelligenceService::withinTolerance(0, 0, 0.02));
    }
};

<?php

namespace Tests\Unit;

use App\Models\ResUser;
use App\Services\GamificationService;
use PHPUnit\Framework\TestCase;

class GamificationLevelTest extends TestCase
{
    /**
     * @dataProvider levelCases
     */
    public function test_level_progress_is_calculated_from_xp(
        int $xp,
        int $expectedLevel,
        string $expectedName,
        ?int $expectedNextXp,
        int $expectedProgress
    ): void {
        $service = new class($xp) extends GamificationService {
            private $testXp;

            public function __construct(int $testXp)
            {
                $this->testXp = $testXp;
            }

            public function getUserXp(ResUser $user): int
            {
                return $this->testXp;
            }
        };

        $level = $service->getUserLevel(new ResUser());

        $this->assertSame($expectedLevel, $level['level']);
        $this->assertSame($expectedName, $level['level_name']);
        $this->assertSame($expectedNextXp, $level['next_level_xp']);
        $this->assertSame($expectedProgress, $level['progress_percent']);
    }

    public function levelCases(): array
    {
        return [
            'new explorer' => [0, 1, 'New Explorer', 100, 0],
            'active miner progress' => [120, 2, 'Active Miner', 500, 5],
            'network builder' => [500, 3, 'Network Builder', 1000, 0],
            'elite' => [2500, 5, 'Blockrium Elite', null, 100],
        ];
    }
}

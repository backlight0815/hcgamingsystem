<?php

namespace Tests\Unit;

use App\Models\PmCycle;
use App\Models\PmProject;
use App\Models\PmRelease;
use App\Services\PmsQatAssignmentResolver;
use Tests\TestCase;

class PmsQatAssignmentResolverTest extends TestCase
{
    public function test_multiple_candidates_require_choice_until_one_current_release_is_defined(): void
    {
        $project = (new PmProject)->forceFill(['id' => 10]);
        $cycles = collect([
            (new PmCycle)->forceFill(['id' => 20, 'project_id' => 10, 'status' => 'Active']),
            (new PmCycle)->forceFill(['id' => 21, 'project_id' => 10, 'status' => 'Planned']),
        ]);
        $first = (new PmRelease)->forceFill(['id' => 30, 'project_id' => 10, 'cycle_id' => 20, 'status' => 'Planned']);
        $second = (new PmRelease)->forceFill(['id' => 31, 'project_id' => 10, 'cycle_id' => 21, 'status' => 'Planned']);
        $resolver = new PmsQatAssignmentResolver;
        $choice = $resolver->forProjects(collect([$project]), collect([$first, $second]), $cycles)[10];
        $this->assertNull($choice['release']['id']);
        $this->assertFalse($choice['release']['locked']);
        $this->assertSame(20, $choice['cycle']['id']);
        $this->assertTrue($choice['cycle']['locked']);

        $first->status = 'Development';
        $resolved = $resolver->forProjects(collect([$project]), collect([$first, $second]), $cycles)[10];
        $this->assertSame(30, $resolved['release']['id']);
        $this->assertTrue($resolved['release']['locked']);
        $this->assertSame(20, $resolved['cycle']['id']);
        $this->assertTrue($resolved['cycle']['locked']);
    }
}

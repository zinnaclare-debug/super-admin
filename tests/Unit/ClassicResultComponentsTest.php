<?php

namespace Tests\Unit;

use App\Support\ClassicResultComponents;
use App\Support\ResultPdfTemplate;
use PHPUnit\Framework\TestCase;

class ClassicResultComponentsTest extends TestCase
{
    private function classicSchema(): array
    {
        return [
            'layout' => 'classic',
            'psychomotor_maxes' => [2, 2, 2, 2, 2],
            'quiz_max' => 5,
            'note_copying_max' => 5,
        ];
    }

    public function test_blank_classic_components_receive_the_school_defaults(): void
    {
        $components = ClassicResultComponents::normalize(null, $this->classicSchema());

        $this->assertSame([2, 2, 2, 2, 2], $components['psychomotor']);
        $this->assertSame(5, $components['quiz']);
        $this->assertSame(5, $components['note_copying']);
        $this->assertFalse($components['_initialized']);
    }

    public function test_an_initialized_all_zero_entry_stays_zero(): void
    {
        $components = ClassicResultComponents::normalize([
            'psychomotor' => [0, 0, 0, 0, 0],
            'quiz' => 0,
            'note_copying' => 0,
            '_initialized' => true,
        ], $this->classicSchema());

        $this->assertSame([0, 0, 0, 0, 0], $components['psychomotor']);
        $this->assertSame(0, $components['quiz']);
        $this->assertSame(0, $components['note_copying']);
        $this->assertTrue($components['_initialized']);
    }

    public function test_classic_profile_preserves_custom_labels_and_allocations(): void
    {
        $profile = [
            'ca_maxes' => [10, 10, 10, 5, 5],
            'ca_labels' => ['Test 1', 'Test 2', 'Project', 'CA 4', 'CA 5'],
            'exam_max' => 40,
            'psychomotor_maxes' => [2, 2, 2, 2, 2],
            'psychomotor_labels' => ['Neatness', 'Speech', 'Reading', 'Punctuality', 'Teamwork'],
            'quiz_max' => 5,
            'quiz_label' => 'Reading Quiz',
            'note_copying_max' => 5,
            'note_copying_label' => 'Note Taking',
        ];

        $this->assertNull(ResultPdfTemplate::validateScoreProfile($profile, 'classic'));

        $normalized = ResultPdfTemplate::normalizeScoreProfile($profile, 'classic');
        $this->assertSame([2, 2, 2, 2, 2], $normalized['psychomotor_maxes']);
        $this->assertSame('Neatness', $normalized['psychomotor_labels'][0]);
        $this->assertSame('Reading Quiz', $normalized['quiz_label']);
        $this->assertSame('Note Taking', $normalized['note_copying_label']);
    }
}

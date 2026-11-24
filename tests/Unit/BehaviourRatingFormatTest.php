<?php

namespace Tests\Unit;

use App\Models\StudentBehaviourRating;
use App\Support\BehaviourSchema;
use PHPUnit\Framework\TestCase;

class BehaviourRatingFormatTest extends TestCase
{
    public function test_letter_format_maps_existing_numeric_values_to_letters(): void
    {
        $this->assertSame('E', BehaviourSchema::formatValue(1, 'letter'));
        $this->assertSame('D', BehaviourSchema::formatValue(2, 'letter'));
        $this->assertSame('C', BehaviourSchema::formatValue(3, 'letter'));
        $this->assertSame('B', BehaviourSchema::formatValue(4, 'letter'));
        $this->assertSame('A', BehaviourSchema::formatValue(5, 'letter'));
        $this->assertSame('1', BehaviourSchema::formatValue(1, 'number'));
    }

    public function test_trait_display_uses_the_format_saved_on_that_rating(): void
    {
        $rating = new StudentBehaviourRating([
            'behaviour_rating_format' => 'letter',
            'ratings' => ['criterion_1' => 1],
        ]);

        $traits = BehaviourSchema::traits($rating, ['criteria' => [['label' => 'Handwriting']]]);

        $this->assertSame('E', $traits[0]['value']);
        $this->assertSame('letter', $traits[0]['format']);
    }
}

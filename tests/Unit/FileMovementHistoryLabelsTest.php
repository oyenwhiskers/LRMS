<?php

namespace Tests\Unit;

use App\Models\FileMovement;
use App\Models\Rack;
use App\Models\Shelf;
use App\Models\Staff;
use Tests\TestCase;

class FileMovementHistoryLabelsTest extends TestCase
{
    public function test_history_labels_and_badge_tones(): void
    {
        $shelf = new Shelf(['code' => '01', 'name' => 'Bin 1']);
        $shelf->setRelation('rack', new Rack(['code' => 'A']));

        $borrow = new FileMovement(['type' => 'borrow']);
        $borrow->setRelation('employee', new Staff(['full_name' => 'Alex Tan']));
        $borrow->setRelation('shelf', $shelf);

        $this->assertSame('Borrow', $borrow->typeLabel());
        $this->assertSame('Alex Tan', $borrow->historyPersonLabel());
        $this->assertSame('From A / Bin 1', $borrow->historyShelfLabel());
        $this->assertSame('borrowed', $borrow->historyBadgeTone());

        $return = new FileMovement(['type' => 'return']);
        $return->setRelation('employee', new Staff(['full_name' => 'Alex Tan']));
        $return->setRelation('receiver', new Staff(['full_name' => 'Jamie Lee']));
        $return->setRelation('shelf', $shelf);

        $this->assertSame('Alex Tan · PIC Jamie Lee', $return->historyPersonLabel());
        $this->assertSame('To A / Bin 1', $return->historyShelfLabel());
        $this->assertSame('approved', $return->historyBadgeTone());
    }
}

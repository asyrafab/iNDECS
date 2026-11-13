<?php

namespace Tests\Feature\Amira;

use App\Services\Amira\ProposalSanitiser;
use Tests\TestCase;

class ProposalSanitiserTest extends TestCase
{
    private array $options = [
        'application_types' => ['PB' => 'Pelan Bangunan Penuh', 'TEL' => 'Telekomunikasi'],
        'work_types' => ['baharu' => 'Bangunan baharu', 'tambahan' => 'Tambahan'],
        'building_categories' => ['teres' => 'Kediaman teres'],
        'site_conditions' => ['lereng_bukit' => 'Lereng bukit', 'warisan' => 'Zon warisan'],
    ];

    public function test_keeps_only_offered_values_in_range(): void
    {
        $clean = ProposalSanitiser::clean(['application_type' => 'PKM', 'work_type' => 'tambahan', 'building_category' => 'istana',
            'storeys' => 3, 'floor_area' => 120.5, 'site_conditions' => ['warisan', 'bulan', 'warisan'], 'title' => 'x'], $this->options);
        $this->assertSame(['work_type' => 'tambahan', 'storeys' => 3, 'floor_area' => 120.5, 'site_conditions' => ['warisan']], $clean);
    }

    public function test_out_of_range_numbers_are_dropped(): void
    {
        $this->assertNull(ProposalSanitiser::clean(['storeys' => 0], $this->options));
        $this->assertNull(ProposalSanitiser::clean(['storeys' => 101], $this->options));
        $this->assertNull(ProposalSanitiser::clean(['floor_area' => 0], $this->options));
        $this->assertNull(ProposalSanitiser::clean(['storeys' => '3'], $this->options));
    }

    public function test_nothing_left_is_null(): void
    {
        $this->assertNull(ProposalSanitiser::clean(null, $this->options));
        $this->assertNull(ProposalSanitiser::clean(['application_type' => 'XX', 'site_conditions' => []], $this->options));
        $this->assertSame(['application_type' => 'PB'], ProposalSanitiser::clean(['application_type' => 'PB'], $this->options));
    }
}

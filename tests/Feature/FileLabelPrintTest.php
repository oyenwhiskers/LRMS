<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\LegalFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileLabelPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_label_type_is_front(): void
    {
        [$admin, $file] = $this->adminWithFile();

        $html = $this->actingAs($admin)
            ->get(route('files.label', $file))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'class="file-label file-label--front"'));
        $this->assertSame(0, substr_count($html, 'class="file-label file-label--side"'));
    }

    public function test_side_and_both_label_types_render_expected_pages(): void
    {
        [$admin, $file] = $this->adminWithFile();

        $sideHtml = $this->actingAs($admin)
            ->get(route('files.label', ['file' => $file, 'type' => 'side']))
            ->assertOk()
            ->getContent();

        $this->assertSame(0, substr_count($sideHtml, 'class="file-label file-label--front"'));
        $this->assertSame(1, substr_count($sideHtml, 'class="file-label file-label--side"'));
        $this->assertStringContainsString('file-label--side__canvas', $sideHtml);
        $this->assertStringContainsString('rotate(90deg)', $sideHtml);
        $this->assertStringContainsString('width: 40mm', $sideHtml);
        $this->assertStringContainsString('height: 80mm', $sideHtml);
        $this->assertStringNotContainsString($file->property, $sideHtml);

        $bothHtml = $this->actingAs($admin)
            ->get(route('files.label', ['file' => $file, 'type' => 'both']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($bothHtml, 'class="file-label file-label--front"'));
        $this->assertSame(1, substr_count($bothHtml, 'class="file-label file-label--side"'));
    }

    public function test_invalid_label_type_returns_not_found(): void
    {
        [$admin, $file] = $this->adminWithFile();

        $this->actingAs($admin)
            ->get(route('files.label', ['file' => $file, 'type' => 'spine']))
            ->assertNotFound();
    }

    public function test_label_pdf_downloads_for_each_type(): void
    {
        [$admin, $file] = $this->adminWithFile();

        foreach (['front', 'side', 'both'] as $type) {
            $pdf = $this->actingAs($admin)
                ->get(route('files.label.pdf', ['file' => $file, 'type' => $type]))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');

            $paper = $type === 'side' ? '226\.77\d*\s+113\.38\d*' : '425\.19\d*\s+283\.46\d*';
            $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+'.$paper.'\s*\]/', $pdf->getContent());
            $this->assertSame($type === 'both' ? 2 : 1, preg_match_all('/\/Type\s*\/Page\b/', $pdf->getContent()), $type);
        }
    }

    public function test_print_page_declares_landscape_front_and_original_side_size(): void
    {
        [$admin, $file] = $this->adminWithFile();

        foreach (['front', 'side'] as $type) {
            $this->actingAs($admin)
                ->get(route('files.label', ['file' => $file, 'type' => $type]))
                ->assertOk()
                ->assertSee($type === 'front' ? 'size: 150mm 100mm' : 'size: 80mm 40mm', false);
        }
    }

    /**
     * @return array{0: User, 1: LegalFile}
     */
    private function adminWithFile(): array
    {
        $admin = User::factory()->admin()->create();
        $category = CaseCategory::query()->firstOrFail();
        $file = LegalFile::create([
            'reference_number' => 'LOA/2026/0035',
            'case_category_id' => $category->id,
            'matter_type' => $category->name,
            'purchaser' => 'Evelyn Chia Shu Wen',
            'property' => 'Unit 33-01, Mulia Avenue Shop Offices, Jalan SS 2/72, Petaling Jaya, Selangor',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        return [$admin, $file];
    }
}

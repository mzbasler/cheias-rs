<?php

namespace Tests\Feature\Admin;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportModerationTest extends TestCase
{
    use RefreshDatabase;

    private function createReport(): Report
    {
        return Report::create([
            'latitude' => -30.0,
            'longitude' => -51.2,
            'position_source' => 'gps',
            'photo_path' => 'reports/test.jpg',
            'consent' => true,
        ]);
    }

    public function test_guest_cannot_access_report_moderation(): void
    {
        $this->get('/admin/reports')->assertRedirect(route('login'));
    }

    public function test_approving_a_report_marks_it_reviewed(): void
    {
        $report = $this->createReport();
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.reports.approve', $report))
            ->assertRedirect();

        $report->refresh();

        $this->assertSame('approved', $report->status);
        $this->assertSame($admin->id, $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
    }

    public function test_rejecting_a_report_marks_it_reviewed(): void
    {
        $report = $this->createReport();
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.reports.reject', $report));

        $this->assertSame('rejected', $report->fresh()->status);
    }

    public function test_only_approved_reports_reach_the_public_map(): void
    {
        $this->createReport();
        $approved = $this->createReport();
        $approved->update(['status' => 'approved']);

        $reports = $this->get('/')->viewData('reports');

        $this->assertCount(1, $reports);
        $this->assertSame($approved->id, $reports->first()['id']);
    }
}

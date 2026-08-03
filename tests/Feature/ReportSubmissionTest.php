<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_report_is_stored_as_pending(): void
    {
        Storage::fake('public');

        $response = $this->postJson('/api/reports', [
            'photo' => UploadedFile::fake()->image('rio.jpg'),
            'latitude' => -30.0,
            'longitude' => -51.2,
            'position_source' => 'gps',
            'consent' => '1',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('reports', [
            'latitude' => -30.0,
            'longitude' => -51.2,
            'position_source' => 'gps',
            'status' => 'pending',
        ]);

        Storage::disk('public')->assertExists(Report::first()->photo_path);
    }

    public function test_it_rejects_a_report_without_a_photo(): void
    {
        Storage::fake('public');

        $this->postJson('/api/reports', [
            'latitude' => -30.0,
            'longitude' => -51.2,
            'position_source' => 'gps',
            'consent' => '1',
        ])->assertJsonValidationErrors('photo');
    }

    public function test_it_rejects_a_report_without_consent(): void
    {
        Storage::fake('public');

        $this->postJson('/api/reports', [
            'photo' => UploadedFile::fake()->image('rio.jpg'),
            'latitude' => -30.0,
            'longitude' => -51.2,
            'position_source' => 'gps',
        ])->assertJsonValidationErrors('consent');
    }

    public function test_it_rejects_a_non_image_mime(): void
    {
        Storage::fake('public');

        $this->postJson('/api/reports', [
            'photo' => UploadedFile::fake()->create('rio.svg', 10, 'image/svg+xml'),
            'latitude' => -30.0,
            'longitude' => -51.2,
            'position_source' => 'gps',
            'consent' => '1',
        ])->assertJsonValidationErrors('photo');
    }

    public function test_it_throttles_repeated_submissions(): void
    {
        Storage::fake('public');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/reports', [
                'photo' => UploadedFile::fake()->image('rio.jpg'),
                'latitude' => -30.0,
                'longitude' => -51.2,
                'position_source' => 'gps',
                'consent' => '1',
            ])->assertCreated();
        }

        $this->postJson('/api/reports', [
            'photo' => UploadedFile::fake()->image('rio.jpg'),
            'latitude' => -30.0,
            'longitude' => -51.2,
            'position_source' => 'gps',
            'consent' => '1',
        ])->assertStatus(429);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AmmsNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_list_notifications_with_action_url_and_unread_count(): void
    {
        $user = User::where('email', 'tech.director@example.test')->firstOrFail();

        AmmsNotification::create([
            'user_id' => $user->id,
            'type' => 'MEETING_REQUEST',
            'title' => 'درخواست جلسه آزمایشی',
            'message' => 'یک درخواست جلسه نیازمند بررسی است.',
            'data' => ['meeting_request_id' => 99],
        ]);

        AmmsNotification::create([
            'user_id' => $user->id,
            'type' => 'MEETING_RESCHEDULE',
            'title' => 'تغییر زمان جلسه',
            'message' => 'جلسه بازتنظیم شد.',
            'data' => ['meeting_id' => 55],
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonPath('unread_count', 2);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Check action_url dynamic calculation
        $urls = collect($data)->pluck('action_url')->all();
        $this->assertContains('/meeting-requests/99', $urls);
        $this->assertContains('/meetings/55', $urls);
    }

    public function test_user_can_filter_unread_notifications(): void
    {
        $user = User::where('email', 'tech.director@example.test')->firstOrFail();

        $n1 = AmmsNotification::create([
            'user_id' => $user->id,
            'type' => 'MEETING_REQUEST',
            'title' => 'پیام خوانده شده',
            'message' => 'متن پیام.',
            'read_at' => now(),
        ]);

        $n2 = AmmsNotification::create([
            'user_id' => $user->id,
            'type' => 'MEETING_REQUEST',
            'title' => 'پیام خوانده نشده',
            'message' => 'متن پیام.',
            'read_at' => null,
        ]);

        $responseAll = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');
        $responseAll->assertOk();
        $this->assertCount(2, $responseAll->json('data'));
        $responseAll->assertJsonPath('unread_count', 1);

        $responseUnread = $this->actingAs($user, 'sanctum')->getJson('/api/notifications?filter=UNREAD');
        $responseUnread->assertOk();
        $this->assertCount(1, $responseUnread->json('data'));
        $this->assertSame($n2->id, $responseUnread->json('data.0.id'));
    }

    public function test_user_can_mark_single_notification_as_read(): void
    {
        $user = User::where('email', 'tech.director@example.test')->firstOrFail();

        $n = AmmsNotification::create([
            'user_id' => $user->id,
            'type' => 'MEETING_REQUEST',
            'title' => 'اعلان تست',
            'message' => 'پیام تست.',
            'read_at' => null,
        ]);

        $this->assertNull($n->read_at);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/notifications/{$n->id}/read");
        $response->assertOk();
        $response->assertJsonPath('unread_count', 0);
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user1 = User::where('email', 'tech.director@example.test')->firstOrFail();
        $user2 = User::where('email', 'tech.exec@example.test')->firstOrFail();

        $n = AmmsNotification::create([
            'user_id' => $user1->id,
            'type' => 'MEETING_REQUEST',
            'title' => 'اعلان خصوصی',
            'message' => 'پیام.',
            'read_at' => null,
        ]);

        $response = $this->actingAs($user2, 'sanctum')->postJson("/api/notifications/{$n->id}/read");
        $response->assertForbidden();
        $this->assertNull($n->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user1 = User::where('email', 'tech.director@example.test')->firstOrFail();
        $user2 = User::where('email', 'tech.exec@example.test')->firstOrFail();

        AmmsNotification::create([
            'user_id' => $user1->id,
            'type' => 'MEETING_REQUEST',
            'title' => 'اعلان 1 کاربر 1',
            'message' => 'پیام 1.',
            'read_at' => null,
        ]);

        AmmsNotification::create([
            'user_id' => $user1->id,
            'type' => 'MEETING_REQUEST',
            'title' => 'اعلان 2 کاربر 1',
            'message' => 'پیام 2.',
            'read_at' => null,
        ]);

        $nOther = AmmsNotification::create([
            'user_id' => $user2->id,
            'type' => 'MEETING_REQUEST',
            'title' => 'اعلان کاربر 2',
            'message' => 'پیام دیگر.',
            'read_at' => null,
        ]);

        $response = $this->actingAs($user1, 'sanctum')->postJson('/api/notifications/read-all');
        $response->assertOk();
        $response->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $user1->notifications()->whereNull('read_at')->count());
        $this->assertSame(1, $user2->notifications()->whereNull('read_at')->count());
        $this->assertNull($nOther->fresh()->read_at);
    }

    public function test_user_can_fetch_unread_count(): void
    {
        $user = User::where('email', 'tech.director@example.test')->firstOrFail();

        AmmsNotification::create([
            'user_id' => $user->id,
            'type' => 'MEETING_REQUEST',
            'title' => 'اعلان خوانده‌نشده',
            'message' => 'پیام.',
            'read_at' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notifications/unread-count');
        $response->assertOk();
        $response->assertJsonPath('unread_count', 1);
    }

    public function test_server_side_pagination_and_meta(): void
    {
        $user = User::where('email', 'tech.director@example.test')->firstOrFail();

        for ($i = 1; $i <= 25; $i++) {
            AmmsNotification::create([
                'user_id' => $user->id,
                'type' => 'MEETING_REQUEST',
                'title' => "اعلان شماره {$i}",
                'message' => "متن پیام {$i}",
                'read_at' => $i % 2 === 0 ? now() : null,
            ]);
        }

        // Page 1 with per_page 10
        $page1 = $this->actingAs($user, 'sanctum')->getJson('/api/notifications?page=1&per_page=10');
        $page1->assertOk();
        $page1->assertJsonPath('meta.current_page', 1);
        $page1->assertJsonPath('meta.per_page', 10);
        $page1->assertJsonPath('meta.last_page', 3);
        $page1->assertJsonPath('meta.total', 25);
        $page1->assertJsonPath('total_count', 25);
        $page1->assertJsonPath('unread_count', 13); // odd numbers are unread: 1,3,5,7,9,11,13,15,17,19,21,23,25 = 13
        $this->assertCount(10, $page1->json('data'));

        // Page 2 with per_page 10
        $page2 = $this->actingAs($user, 'sanctum')->getJson('/api/notifications?page=2&per_page=10');
        $page2->assertOk();
        $page2->assertJsonPath('meta.current_page', 2);
        $this->assertCount(10, $page2->json('data'));

        // Page 3 with per_page 10
        $page3 = $this->actingAs($user, 'sanctum')->getJson('/api/notifications?page=3&per_page=10');
        $page3->assertOk();
        $page3->assertJsonPath('meta.current_page', 3);
        $this->assertCount(5, $page3->json('data'));

        // Server-side filtered unread pagination
        $unreadPage1 = $this->actingAs($user, 'sanctum')->getJson('/api/notifications?page=1&per_page=10&filter=UNREAD');
        $unreadPage1->assertOk();
        $unreadPage1->assertJsonPath('meta.total', 13);
        $unreadPage1->assertJsonPath('meta.last_page', 2);
        $this->assertCount(10, $unreadPage1->json('data'));
    }
}

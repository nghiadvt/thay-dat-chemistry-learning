<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\Group;
use App\Models\Keyboard;
use App\Models\Quiz;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupedListTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Game $game;

    private Keyboard $keyboard;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Role::query()->exists()) {
            Role::query()->insert([
                ['name' => 'Quản trị viên', 'slug' => 'admin', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Giáo viên', 'slug' => 'teacher', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        $this->teacher = User::factory()->teacher()->create();
        $this->keyboard = Keyboard::create([
            'name' => 'Bàn phím test',
            'subject' => 'chemistry',
            'config' => ['rows' => []],
        ]);
        $this->game = Game::create([
            'name' => 'Game test',
            'created_by' => $this->teacher->id,
        ]);
    }

    private function makeQuiz(string $name, ?Group $group = null, ?string $updatedAt = null): Quiz
    {
        $quiz = Quiz::create([
            'game_id' => $this->game->id,
            'keyboard_id' => $this->keyboard->id,
            'name' => $name,
            'group_id' => $group?->id,
            'is_active' => true,
        ]);

        if ($updatedAt) {
            $quiz->forceFill(['updated_at' => $updatedAt])->saveQuietly();
        }

        return $quiz->fresh();
    }

    public function test_trang_khong_loc_hien_dang_nhom_va_muc_gan_day(): void
    {
        $group = Group::findOrCreateFromName('Chương 1', Group::SCOPE_QUIZ);
        $this->makeQuiz('Quiz trong nhóm', $group);
        $this->makeQuiz('Quiz chưa phân nhóm');

        $response = $this->actingAs($this->teacher)->get(route('admin.quizzes.index'));

        $response->assertOk()
            ->assertSee('Thao tác gần đây')
            ->assertSee('Chương 1')
            ->assertSee('Khác'); // nhóm cho record chưa phân nhóm
    }

    public function test_nhom_gap_lai_va_khong_render_san_noi_dung(): void
    {
        $group = Group::findOrCreateFromName('Chương 2', Group::SCOPE_QUIZ);
        // Đủ nhiều quiz cũ để «Quiz cũ nhất» bị đẩy khỏi danh sách gần đây (giới hạn 8).
        $this->makeQuiz('Quiz cũ nhất', $group, '2019-01-01 00:00:00');
        for ($i = 1; $i <= 12; $i++) {
            $this->makeQuiz("Quiz đệm {$i}", $group, '2020-01-01 00:00:00');
        }
        $this->makeQuiz('Quiz mới nhất', null, '2030-01-01 00:00:00');

        $response = $this->actingAs($this->teacher)->get(route('admin.quizzes.index'));

        $response->assertOk()
            ->assertSee('Quiz mới nhất')          // có trong «gần đây»
            ->assertDontSee('Quiz cũ nhất')       // nằm trong nhóm đang gập, chưa tải
            ->assertSee('is-collapsed', false);
    }

    public function test_mo_nhom_tra_ve_20_record_dau_va_bao_con_du_lieu(): void
    {
        $group = Group::findOrCreateFromName('Chương 3', Group::SCOPE_QUIZ);
        for ($i = 1; $i <= 25; $i++) {
            $this->makeQuiz("Quiz {$i}", $group);
        }

        $response = $this->actingAs($this->teacher)
            ->getJson(route('admin.quizzes.group-rows', ['group_id' => $group->id, 'offset' => 0]));

        $response->assertOk()
            ->assertJson(['has_more' => true, 'next_offset' => 20]);

        $this->assertSame(20, substr_count($response->json('html'), '<tr'));
    }

    public function test_trang_cuoi_cua_nhom_bao_het_du_lieu(): void
    {
        $group = Group::findOrCreateFromName('Chương 4', Group::SCOPE_QUIZ);
        for ($i = 1; $i <= 25; $i++) {
            $this->makeQuiz("Quiz {$i}", $group);
        }

        $response = $this->actingAs($this->teacher)
            ->getJson(route('admin.quizzes.group-rows', ['group_id' => $group->id, 'offset' => 20]));

        $response->assertOk()->assertJson(['has_more' => false]);
        $this->assertSame(5, substr_count($response->json('html'), '<tr'));
    }

    public function test_noi_dung_nhom_sap_xep_theo_thao_tac_gan_nhat(): void
    {
        $group = Group::findOrCreateFromName('Chương 5', Group::SCOPE_QUIZ);
        $this->makeQuiz('Quiz cũ', $group, '2020-01-01 00:00:00');
        $this->makeQuiz('Quiz vừa sửa', $group, '2030-01-01 00:00:00');

        $html = $this->actingAs($this->teacher)
            ->getJson(route('admin.quizzes.group-rows', ['group_id' => $group->id]))
            ->json('html');

        $this->assertLessThan(
            strpos($html, 'Quiz cũ'),
            strpos($html, 'Quiz vừa sửa'),
            'Record thao tác gần nhất phải đứng trước.'
        );
    }

    public function test_nhom_khac_lay_dung_record_chua_phan_nhom(): void
    {
        $group = Group::findOrCreateFromName('Chương 6', Group::SCOPE_QUIZ);
        $this->makeQuiz('Quiz có nhóm', $group);
        $this->makeQuiz('Quiz không nhóm');

        $html = $this->actingAs($this->teacher)
            ->getJson(route('admin.quizzes.group-rows', ['group_id' => 'none']))
            ->json('html');

        $this->assertStringContainsString('Quiz không nhóm', $html);
        $this->assertStringNotContainsString('Quiz có nhóm', $html);
    }

    public function test_khi_tim_kiem_thi_quay_ve_bang_phang(): void
    {
        $group = Group::findOrCreateFromName('Chương 7', Group::SCOPE_QUIZ);
        $this->makeQuiz('Quiz tìm được', $group, '2020-01-01 00:00:00');
        $this->makeQuiz('Quiz khác', $group, '2020-01-01 00:00:00');

        $response = $this->actingAs($this->teacher)
            ->get(route('admin.quizzes.index', ['q' => 'tìm được']));

        $response->assertOk()
            ->assertSee('Quiz tìm được')
            ->assertDontSee('Quiz khác')
            ->assertDontSee('Thao tác gần đây');
    }

    public function test_trang_bo_cau_hoi_hien_dang_nhom_va_mo_duoc_nhom(): void
    {
        $group = Group::findOrCreateFromName('Ngân hàng HK1', Group::SCOPE_QUESTION_BANK);
        for ($i = 1; $i <= 25; $i++) {
            \App\Models\QuestionBankItem::create([
                'group_id' => $group->id,
                'content' => "Câu hỏi số {$i}",
                'answer_type' => 'essay',
                'correct_answer_normalized' => 'x',
                'time_limit_seconds' => 30,
                'points' => 1,
                'is_active' => true,
            ]);
        }

        $this->actingAs($this->teacher)
            ->get(route('admin.question-bank.index'))
            ->assertOk()
            ->assertSee('Thao tác gần đây')
            ->assertSee('Ngân hàng HK1');

        $response = $this->actingAs($this->teacher)
            ->getJson(route('admin.question-bank.group-rows', ['group_id' => $group->id]));

        $response->assertOk()->assertJson(['has_more' => true]);
        $this->assertSame(20, substr_count($response->json('html'), '<tr'));
    }

    public function test_trang_phong_choi_hien_dang_nhom_va_mo_duoc_nhom(): void
    {
        $group = Group::findOrCreateFromName('Lớp 10A', Group::SCOPE_SESSION);
        $quiz = $this->makeQuiz('Quiz cho phòng');

        for ($i = 1; $i <= 25; $i++) {
            \App\Models\GameSession::create([
                'pin' => str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT),
                'group_id' => $group->id,
                'name' => "Phòng số {$i}",
                'host_id' => $this->teacher->id,
                'game_id' => $quiz->game_id,
                'quiz_id' => $quiz->id,
                'status' => 'waiting',
                'is_active' => true,
            ]);
        }

        $this->actingAs($this->teacher)
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('Thao tác gần đây')
            ->assertSee('Lớp 10A');

        $response = $this->actingAs($this->teacher)
            ->getJson(route('admin.sessions.group-rows', ['group_id' => $group->id]));

        $response->assertOk()->assertJson(['has_more' => true]);
        $this->assertSame(20, substr_count($response->json('html'), '<tr'));
    }

    public function test_muc_gan_day_gioi_han_va_van_trung_voi_record_trong_nhom(): void
    {
        $group = Group::findOrCreateFromName('Chương 8', Group::SCOPE_QUIZ);
        for ($i = 1; $i <= 12; $i++) {
            $this->makeQuiz("Quiz {$i}", $group);
        }

        $response = $this->actingAs($this->teacher)->get(route('admin.quizzes.index'));
        $response->assertOk();

        // «Gần đây» tối đa 8 record (đếm ô dữ liệu, không tính <col>/<th>).
        $this->assertSame(8, substr_count($response->getContent(), '<td data-col="name"'));

        // Số đếm của nhóm vẫn là đủ 12 — record gần đây không bị trừ ra khỏi nhóm.
        $response->assertSee('>12</span>', false);
    }
}

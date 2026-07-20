<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\GameSession;
use App\Models\Group;
use App\Models\Keyboard;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->teacher = User::factory()->teacher()->create();
    }

    private function seedRoles(): void
    {
        if (Role::query()->exists()) {
            return;
        }

        Role::query()->insert([
            ['name' => 'Quản trị viên', 'slug' => 'admin', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Giáo viên', 'slug' => 'teacher', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function makeQuiz(array $overrides = []): Quiz
    {
        $keyboard = Keyboard::create([
            'name' => 'Bàn phím test '.uniqid(),
            'subject' => 'chemistry',
            'config' => ['rows' => []],
        ]);

        $game = Game::create([
            'name' => 'Game test '.uniqid(),
            'created_by' => $this->teacher->id,
        ]);

        return Quiz::create(array_merge([
            'game_id' => $game->id,
            'keyboard_id' => $keyboard->id,
            'name' => 'Quiz test',
            'is_active' => true,
        ], $overrides));
    }

    public function test_trang_quan_ly_nhom_mo_duoc_cho_tung_scope(): void
    {
        foreach (array_keys(Group::SCOPE_LABELS) as $scope) {
            $this->actingAs($this->teacher)
                ->get(route('admin.groups.index', ['scope' => $scope]))
                ->assertOk()
                ->assertSee('Tạo nhóm mới');
        }
    }

    public function test_tao_nhom_luu_dung_scope_va_slug(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('admin.groups.store'), [
                'scope' => Group::SCOPE_QUIZ,
                'name' => 'Chương 1 — Nguyên tử',
                'color' => '#059669',
            ])
            ->assertRedirect(route('admin.groups.index', ['scope' => Group::SCOPE_QUIZ]));

        $group = Group::sole();
        $this->assertSame(Group::SCOPE_QUIZ, $group->scope);
        $this->assertSame('Chương 1 — Nguyên tử', $group->name);
        $this->assertSame('#059669', $group->color);
        $this->assertNotSame('', $group->slug);
    }

    public function test_moi_trang_co_danh_sach_nhom_rieng(): void
    {
        // Cùng tên nhưng khác scope thì vẫn tạo được — đây là hai nhóm độc lập.
        Group::findOrCreateFromName('Học kỳ 1', Group::SCOPE_QUIZ);
        Group::findOrCreateFromName('Học kỳ 1', Group::SCOPE_SESSION);

        $this->assertSame(2, Group::count());
        $this->assertSame(1, Group::ofScope(Group::SCOPE_QUIZ)->count());
        $this->assertSame(1, Group::ofScope(Group::SCOPE_SESSION)->count());
        $this->assertSame(0, Group::ofScope(Group::SCOPE_QUESTION_BANK)->count());
    }

    public function test_khong_tao_trung_ten_trong_cung_mot_scope(): void
    {
        Group::findOrCreateFromName('Trùng tên', Group::SCOPE_QUIZ);

        $this->actingAs($this->teacher)
            ->post(route('admin.groups.store'), [
                'scope' => Group::SCOPE_QUIZ,
                'name' => 'Trùng tên',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Group::count());
    }

    public function test_tao_quiz_gan_duoc_nhom_da_co(): void
    {
        $quiz = $this->makeQuiz();
        $group = Group::findOrCreateFromName('Chương 2', Group::SCOPE_QUIZ);

        $this->actingAs($this->teacher)
            ->post(route('admin.quizzes.store'), [
                'game_id' => $quiz->game_id,
                'keyboard_id' => $quiz->keyboard_id,
                'name' => 'Quiz có nhóm',
                'group_id' => $group->id,
            ])
            ->assertRedirect();

        $this->assertSame($group->id, Quiz::where('name', 'Quiz có nhóm')->sole()->group_id);
    }

    public function test_tao_quiz_tao_luon_nhom_moi_tu_ten_nhap_tay(): void
    {
        $quiz = $this->makeQuiz();

        $this->actingAs($this->teacher)
            ->post(route('admin.quizzes.store'), [
                'game_id' => $quiz->game_id,
                'keyboard_id' => $quiz->keyboard_id,
                'name' => 'Quiz nhóm mới',
                'new_group_name' => 'Nhóm vừa tạo',
            ])
            ->assertRedirect();

        $group = Group::ofScope(Group::SCOPE_QUIZ)->sole();
        $this->assertSame('Nhóm vừa tạo', $group->name);
        $this->assertSame($group->id, Quiz::where('name', 'Quiz nhóm mới')->sole()->group_id);
    }

    public function test_khong_gan_duoc_nhom_thuoc_scope_khac(): void
    {
        $quiz = $this->makeQuiz();
        $groupCuaPhongChoi = Group::findOrCreateFromName('Nhóm phòng chơi', Group::SCOPE_SESSION);

        $this->actingAs($this->teacher)
            ->post(route('admin.quizzes.store'), [
                'game_id' => $quiz->game_id,
                'keyboard_id' => $quiz->keyboard_id,
                'name' => 'Quiz sai nhóm',
                'group_id' => $groupCuaPhongChoi->id,
            ])
            ->assertSessionHasErrors('group_id');
    }

    public function test_loc_danh_sach_quiz_theo_nhom_va_theo_chua_phan_nhom(): void
    {
        $group = Group::findOrCreateFromName('Chương 3', Group::SCOPE_QUIZ);
        $this->makeQuiz(['name' => 'Quiz trong nhóm', 'group_id' => $group->id]);
        $this->makeQuiz(['name' => 'Quiz ngoài nhóm']);

        $this->actingAs($this->teacher)
            ->get(route('admin.quizzes.index', ['group_id' => $group->id]))
            ->assertOk()
            ->assertSee('Quiz trong nhóm')
            ->assertDontSee('Quiz ngoài nhóm');

        $this->actingAs($this->teacher)
            ->get(route('admin.quizzes.index', ['group_id' => 'none']))
            ->assertOk()
            ->assertSee('Quiz ngoài nhóm')
            ->assertDontSee('Quiz trong nhóm');
    }

    public function test_xoa_nhom_khong_xoa_cac_muc_ben_trong(): void
    {
        $group = Group::findOrCreateFromName('Sắp xóa', Group::SCOPE_QUIZ);
        $quiz = $this->makeQuiz(['name' => 'Quiz giữ lại', 'group_id' => $group->id]);

        $this->actingAs($this->teacher)
            ->delete(route('admin.groups.destroy', $group))
            ->assertRedirect(route('admin.groups.index', ['scope' => Group::SCOPE_QUIZ]));

        $this->assertNull(Group::find($group->id));
        $this->assertNotNull($quiz->fresh(), 'Quiz phải còn nguyên sau khi xóa nhóm.');
        $this->assertNull($quiz->fresh()->group_id, 'Quiz phải trở về trạng thái chưa phân nhóm.');
    }

    public function test_o_chon_nhom_hien_tren_moi_trang_danh_sach_va_form(): void
    {
        $quiz = $this->makeQuiz();
        Group::findOrCreateFromName('Nhóm quiz', Group::SCOPE_QUIZ);
        Group::findOrCreateFromName('Nhóm ngân hàng', Group::SCOPE_QUESTION_BANK);
        Group::findOrCreateFromName('Nhóm phòng', Group::SCOPE_SESSION);

        $pages = [
            route('admin.quizzes.index') => 'Nhóm quiz',
            route('admin.quizzes.create') => 'Nhóm quiz',
            route('admin.quizzes.show', $quiz) => 'Nhóm quiz',
            route('admin.question-bank.index') => 'Nhóm ngân hàng',
            route('admin.question-bank.create') => 'Nhóm ngân hàng',
            route('admin.sessions.index') => 'Nhóm phòng',
            route('admin.sessions.create') => 'Nhóm phòng',
        ];

        foreach ($pages as $url => $expectedGroupName) {
            $this->actingAs($this->teacher)
                ->get($url)
                ->assertOk()
                ->assertSee('group-select')
                ->assertSee($expectedGroupName);
        }
    }

    public function test_moi_trang_chi_thay_nhom_cua_rieng_no(): void
    {
        Group::findOrCreateFromName('Chỉ của quiz', Group::SCOPE_QUIZ);
        Group::findOrCreateFromName('Chỉ của phòng chơi', Group::SCOPE_SESSION);

        $this->actingAs($this->teacher)
            ->get(route('admin.quizzes.create'))
            ->assertOk()
            ->assertSee('Chỉ của quiz')
            ->assertDontSee('Chỉ của phòng chơi');

        $this->actingAs($this->teacher)
            ->get(route('admin.sessions.create'))
            ->assertOk()
            ->assertSee('Chỉ của phòng chơi')
            ->assertDontSee('Chỉ của quiz');
    }

    public function test_items_count_dem_dung_bang_theo_scope(): void
    {
        $groupQuiz = Group::findOrCreateFromName('Đếm quiz', Group::SCOPE_QUIZ);
        $groupBank = Group::findOrCreateFromName('Đếm ngân hàng', Group::SCOPE_QUESTION_BANK);
        $groupSession = Group::findOrCreateFromName('Đếm phòng', Group::SCOPE_SESSION);

        $quiz = $this->makeQuiz(['group_id' => $groupQuiz->id]);

        QuestionBankItem::create([
            'group_id' => $groupBank->id,
            'content' => 'Câu hỏi test',
            'answer_type' => 'essay',
            'correct_answer_normalized' => 'x',
            'time_limit_seconds' => 30,
            'points' => 1,
            'is_active' => true,
        ]);

        GameSession::create([
            'pin' => '123456',
            'group_id' => $groupSession->id,
            'name' => 'Phòng test',
            'host_id' => $this->teacher->id,
            'game_id' => $quiz->game_id,
            'quiz_id' => $quiz->id,
            'status' => 'waiting',
            'is_active' => true,
        ]);

        $this->assertSame(1, $groupQuiz->itemsCount());
        $this->assertSame(1, $groupBank->itemsCount());
        $this->assertSame(1, $groupSession->itemsCount());
    }
}

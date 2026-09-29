<?php

namespace Tests\Feature\Recruitment;

use App\Models\CustomQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered()
    {
        CustomQuestion::create(['question' => 'Expected salary?', 'type' => 'text']);
        CustomQuestion::create(['question' => 'Willing to relocate?', 'type' => 'radio', 'options' => ['Yes', 'No'], 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.custom-questions.index', ['search' => 'relocate']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/custom-questions/index')
                ->has('customQuestions.data', 1)
                ->where('customQuestions.data.0.options', ['Yes', 'No']));

        $this->get(route('hr.recruitment.custom-questions.index', ['status' => 'active']))
            ->assertInertia(fn ($page) => $page->has('customQuestions.data', 1)->where('customQuestions.data.0.question', 'Expected salary?'));
    }

    public function test_custom_questions_can_be_created_updated_and_deleted()
    {
        $user = $this->userWithRole();
        $this->actingAs($user);

        $this->post(route('hr.recruitment.custom-questions.store'), ['question' => '', 'type' => 'bogus', 'status' => 'active'])
            ->assertSessionHasErrors(['question', 'type']);
        $this->post(route('hr.recruitment.custom-questions.store'), ['question' => 'Pick one', 'type' => 'select', 'status' => 'active'])
            ->assertSessionHasErrors('options');

        $this->post(route('hr.recruitment.custom-questions.store'), [
            'question' => 'Pick one', 'type' => 'select', 'options' => ['A', 'B'], 'required' => true, 'sort_order' => 2, 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $record = CustomQuestion::where('question', 'Pick one')->firstOrFail();
        $this->assertSame($user->id, $record->created_by);
        $this->assertSame(['A', 'B'], $record->options);
        $this->assertTrue($record->required);

        // Switching to a free-text type drops the options.
        $this->put(route('hr.recruitment.custom-questions.update', $record), ['question' => 'Tell us', 'type' => 'textarea', 'options' => ['A'], 'status' => 'inactive'])
            ->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame('Tell us', $record->question);
        $this->assertNull($record->options);

        $this->delete(route('hr.recruitment.custom-questions.destroy', $record));
        $this->assertModelMissing($record);
    }

    public function test_hr_and_employees_cannot_manage_custom_questions()
    {
        $record = CustomQuestion::create(['question' => 'Q?', 'type' => 'text']);

        foreach (['hr', 'employee'] as $role) {
            $this->actingAs($this->userWithRole($role));
            $this->get(route('hr.recruitment.custom-questions.index'))->assertForbidden();
            $this->delete(route('hr.recruitment.custom-questions.destroy', $record))->assertForbidden();
        }

        $this->assertModelExists($record);
    }

    public function test_manage_own_users_only_see_their_own_records()
    {
        $user = $this->userWithRole('hr');
        $user->syncRoles([])->givePermissionTo(['manage-custom-questions', 'manage-own-custom-questions', 'edit-custom-questions']);
        $mine = CustomQuestion::create(['question' => 'Mine', 'type' => 'text', 'created_by' => $user->id]);
        $other = CustomQuestion::create(['question' => 'Other', 'type' => 'text']);

        $this->actingAs($user)
            ->get(route('hr.recruitment.custom-questions.index'))
            ->assertInertia(fn ($page) => $page->has('customQuestions.data', 1)->where('customQuestions.data.0.id', $mine->id));

        $this->put(route('hr.recruitment.custom-questions.update', $other), ['question' => 'X', 'type' => 'text', 'status' => 'active'])->assertForbidden();
    }
}

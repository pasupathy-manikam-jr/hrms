<?php

namespace Tests\Feature\Hr;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_sorted_and_paginated()
    {
        // Fixed names so the descending sort below is deterministic.
        Branch::factory()->count(12)->sequence(fn ($sequence) => ['name' => 'Branch '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)])->create();
        Branch::factory()->create(['name' => 'Zeta Hub']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.branches.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/branches/index')
                ->has('branches.data', 1)
                ->where('branches.data.0.name', 'Zeta Hub')
                ->where('filters.search', 'Zeta'));

        $this->get(route('hr.branches.index', ['sort_field' => 'name', 'sort_direction' => 'desc', 'per_page' => 25]))
            ->assertInertia(fn ($page) => $page
                ->has('branches.data', 13)
                ->where('branches.data.0.name', 'Zeta Hub'));

        // Unknown sort columns and page sizes fall back to defaults instead of reaching SQL.
        $this->get(route('hr.branches.index', ['sort_field' => 'password', 'per_page' => 9999]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('branches.per_page', 10));
    }

    public function test_branches_can_be_created_updated_toggled_and_deleted()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.branches.store'), ['name' => '', 'status' => 'bogus'])->assertSessionHasErrors(['name', 'status']);
        $this->post(route('hr.branches.store'), ['name' => 'Harbour', 'email' => 'harbour@example.com', 'status' => 'active'])->assertSessionHasNoErrors();

        $branch = Branch::where('name', 'Harbour')->firstOrFail();

        $this->put(route('hr.branches.update', $branch), ['name' => 'Harbour Point', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('Harbour Point', $branch->fresh()->name);

        $this->put(route('hr.branches.toggle-status', $branch));
        $this->assertSame('inactive', $branch->fresh()->status);

        $this->delete(route('hr.branches.destroy', $branch));
        $this->assertModelMissing($branch);
    }

    public function test_employees_cannot_manage_branches()
    {
        $branch = Branch::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.branches.index'))->assertForbidden();
        $this->post(route('hr.branches.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->delete(route('hr.branches.destroy', $branch))->assertForbidden();
        $this->assertModelExists($branch);
    }
}

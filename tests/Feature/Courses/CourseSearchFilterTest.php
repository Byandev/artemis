<?php

use Modules\Courses\Models\Course;

beforeEach(function () {
    ['workspace' => $this->workspace] = actingAsWorkspaceOwner();
    $this->workspace->update(['courses_module_enabled' => true]);
    $this->url = "/workspaces/{$this->workspace->slug}/courses";

    Course::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Meta Ads Manager',
        'description' => 'Running paid campaigns.',
        'category' => 'Ads',
        'status' => 'published',
    ]);

    Course::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Creative Briefs',
        'description' => 'Hook structure and testing.',
        'category' => 'Creatives',
        'status' => 'draft',
    ]);
});

it('searches on the course name', function () {
    $this->get("{$this->url}?search=Meta")->assertOk()->assertInertia(
        fn ($page) => $page
            ->has('courses.data', 1)
            ->where('courses.data.0.name', 'Meta Ads Manager'),
    );
});

it('searches on the description too', function () {
    $this->get("{$this->url}?search=Hook")->assertOk()->assertInertia(
        fn ($page) => $page
            ->has('courses.data', 1)
            ->where('courses.data.0.name', 'Creative Briefs'),
    );
});

it('filters by category, accepting several at once', function () {
    $this->get("{$this->url}?categories[]=Ads")->assertOk()->assertInertia(
        fn ($page) => $page->has('courses.data', 1),
    );

    $this->get("{$this->url}?categories[]=Ads&categories[]=Creatives")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('courses.data', 2));
});

it('combines search with a filter', function () {
    // Matches the search but not the category, so nothing comes back.
    $this->get("{$this->url}?search=Meta&categories[]=Creatives")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('courses.data', 0));
});

it('offers only categories that are actually in use', function () {
    $this->get($this->url)->assertOk()->assertInertia(
        fn ($page) => $page->where('categoryOptions', ['Ads', 'Creatives']),
    );
});

it('echoes the applied filters back to the toolbar', function () {
    $this->get("{$this->url}?search=Meta&categories[]=Ads")->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('filters.search', 'Meta')
            ->where('filters.categories', ['Ads']),
    );
});

it('never surfaces a draft to a learner', function () {
    $learner = makeMemberWithPermissions($this->workspace, ['View Courses']);

    $this->actingAs($learner)->get($this->url)->assertOk()->assertInertia(
        fn ($page) => $page
            ->has('courses.data', 1)
            ->where('courses.data.0.name', 'Meta Ads Manager'),
    );
});

it('paginates past the first page', function () {
    // 15 per page, so 18 courses means a second page must be reachable.
    for ($i = 0; $i < 16; $i++) {
        Course::create([
            'workspace_id' => $this->workspace->id,
            'name' => "Filler {$i}",
            'status' => 'published',
        ]);
    }

    $this->get($this->url)->assertOk()->assertInertia(
        fn ($page) => $page
            ->has('courses.data', 15)
            ->where('courses.last_page', 2)
            ->where('courses.total', 18),
    );

    $this->get("{$this->url}?page=2")->assertOk()->assertInertia(
        fn ($page) => $page
            ->has('courses.data', 3)
            ->where('courses.current_page', 2),
    );
});

it('keeps the search applied across pages', function () {
    for ($i = 0; $i < 20; $i++) {
        Course::create([
            'workspace_id' => $this->workspace->id,
            'name' => "Widget {$i}",
            'status' => 'published',
        ]);
    }

    // Paging must not silently drop the filter and show everything.
    $this->get("{$this->url}?search=Widget&page=2")->assertOk()->assertInertia(
        fn ($page) => $page
            ->has('courses.data', 5)
            ->where('courses.total', 20)
            ->where('filters.search', 'Widget'),
    );
});

it('offers a learner only categories from published courses', function () {
    $learner = makeMemberWithPermissions($this->workspace, ['View Courses']);

    // "Creatives" belongs to a draft, so it must not be offered.
    $this->actingAs($learner)->get($this->url)->assertOk()->assertInertia(
        fn ($page) => $page->where('categoryOptions', ['Ads']),
    );
});

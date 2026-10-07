<?php

use Modules\Courses\Models\Course;

beforeEach(function () {
    ['workspace' => $this->workspace] = actingAsWorkspaceOwner();
    $this->workspace->update(['courses_module_enabled' => true]);
    $this->url = "/workspaces/{$this->workspace->slug}/courses";
    $this->api = "/api/workspaces/{$this->workspace->slug}/courses";

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
    $this->getJson("{$this->api}?search=Meta")->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Meta Ads Manager');
});

it('searches on the description too', function () {
    $this->getJson("{$this->api}?search=Hook")->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Creative Briefs');
});

it('filters by category, accepting several at once', function () {
    $this->getJson("{$this->api}?categories[]=Ads")->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson("{$this->api}?categories[]=Ads&categories[]=Creatives")->assertOk()
        ->assertJsonCount(2, 'data');
});

it('combines search with a filter', function () {
    // Matches the search but not the category, so nothing comes back.
    $this->getJson("{$this->api}?search=Meta&categories[]=Creatives")->assertOk()
        ->assertJsonCount(0, 'data');
});

it('offers only categories that are actually in use', function () {
    $this->get($this->url)->assertOk()->assertInertia(
        fn ($page) => $page->where('categoryOptions', ['Ads', 'Creatives']),
    );
});

it('opens the page with the filters from the URL', function () {
    $this->get("{$this->url}?search=Meta&categories[]=Ads&page=2")->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('filters.search', 'Meta')
            ->where('filters.categories', ['Ads'])
            ->where('filters.page', 2),
    );
});

it('never surfaces a draft to a learner', function () {
    $learner = makeMemberWithPermissions($this->workspace, ['View Courses']);

    $this->actingAs($learner)->getJson($this->api)->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Meta Ads Manager');
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

    $this->getJson($this->api)->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('last_page', 2)
        ->assertJsonPath('total', 18);

    $this->getJson("{$this->api}?page=2")->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('current_page', 2);
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
    $this->getJson("{$this->api}?search=Widget&page=2")->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('total', 20);
});

it('offers a learner only categories from published courses', function () {
    $learner = makeMemberWithPermissions($this->workspace, ['View Courses']);

    // "Creatives" belongs to a draft, so it must not be offered.
    $this->actingAs($learner)->get($this->url)->assertOk()->assertInertia(
        fn ($page) => $page->where('categoryOptions', ['Ads']),
    );
});

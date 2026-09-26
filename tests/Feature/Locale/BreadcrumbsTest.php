<?php

use App\Models\Organization;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Breadcrumbs (Phase 9.2 design). The extractor only sees literal `__('…')`
 * calls, so a breadcrumb label can no longer be a key translated later
 * (`titleKey` + `t(key)` was invisible to it). Instead:
 *
 * - `BreadcrumbItem.title` is the text shown, already final.
 * - A static label is written as `title: __('Cards')` inside the page's
 *   layout FUNCTION (`Page.layout = () => ({ breadcrumbs: [...] })`), which
 *   Inertia calls at render time, after the catalog has loaded. A layout
 *   OBJECT would run `__()` at import time and freeze English.
 * - Data (an organization name, a card code) is passed as is, and nothing
 *   ever translates `title` afterwards.
 */

/**
 * The `breadcrumbs: [...]` block of every page that has one.
 *
 * @return array<string, string> Page path => block source.
 */
function breadcrumbBlocks(): array
{
    $blocks = [];

    foreach (File::allFiles(resource_path('js/pages')) as $file) {
        $source = $file->getContents();
        $start = strpos($source, 'breadcrumbs:');

        if ($start === false) {
            continue;
        }

        $end = strpos($source, "\n    ],", $start) ?: strpos($source, ']', $start);
        $blocks[$file->getRelativePathname()] = substr($source, $start, $end - $start);
    }

    return $blocks;
}

test('breadcrumb data labels are not translated', function () {
    // "Admin" is also a static label with a Spanish translation.
    expect(catalogLine('es', 'Admin'))->toBe('Administración');

    $organization = Organization::factory()->create(['name' => 'Admin']);

    $admin = superadmin();
    $admin->forceFill(['locale' => 'es'])->save();

    $this->actingAs($admin)
        ->get(route('admin.organizations.show', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/organizations/show')
            ->where('locale.current', 'es')
            ->where('organization.name', 'Admin'));

    // The page builds its crumbs with the static label translated at the
    // call site and the organization name passed through untouched.
    $page = File::get(resource_path('js/pages/admin/organizations/show.tsx'));
    expect($page)->toContain("{ title: __('Admin'), href: adminIndex() }")
        ->and($page)->toContain('title: props.organization.name,')
        ->and($page)->not->toContain('__(props.organization.name');

    // The card code is data too.
    expect(File::get(resource_path('js/pages/cards/show.tsx')))
        ->toContain('{ title: props.card.code, href: show(props.card) }');

    // The component shows `title` as is: no translator, no second lookup.
    $component = File::get(resource_path('js/components/breadcrumbs.tsx'));
    expect($component)->toContain('{item.title}')
        ->and($component)->not->toContain('__(')
        ->and($component)->not->toContain('titleKey');
});

test('every static breadcrumb label is a __() call in a layout function', function () {
    $blocks = breadcrumbBlocks();

    // Sanity: the scan finds the pages (15 had breadcrumbs in Phase 8a).
    expect(count($blocks))->toBeGreaterThanOrEqual(15);

    foreach ($blocks as $path => $block) {
        $source = File::get(resource_path('js/pages/'.$path));

        // A layout object would evaluate __() at import time.
        preg_match('/\.layout = .*$/m', $source, $layout);

        expect($layout[0] ?? '')->toMatch('/^\.layout = \([^)]*\) => \(\{$/', $path.' must build its layout in a function')
            ->and($block)->not->toContain('titleKey', $path.' still uses titleKey')
            ->and($block)->not->toMatch("/\\btitle:\\s*['\"`]/", $path.' passes an untranslated literal breadcrumb title');

        // Every static label was extracted and has a Spanish line.
        preg_match_all("/title:\\s*__\\('((?:[^'\\\\]|\\\\.)+)'\\)/", $block, $matches);

        foreach ($matches[1] as $label) {
            expect(catalogLine('es', stripslashes($label)))->not->toBeNull($path.': "'.$label.'" is not in the Spanish catalog');
        }
    }
});

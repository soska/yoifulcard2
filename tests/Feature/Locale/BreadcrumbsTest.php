<?php

use App\Models\Organization;
use App\Support\Translations;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Runs `breadcrumbLabel` from resources/js/lib/breadcrumbs.ts in Node
 * (which strips the TypeScript types) with the given translations.
 *
 * @param  array<int, array<string, string>>  $items
 * @param  array<string, string>  $translations
 * @return array<int, string>
 */
function breadcrumbLabels(array $items, array $translations): array
{
    $module = 'file://'.resource_path('js/lib/breadcrumbs.ts');
    $script = <<<'JS'
        const { breadcrumbLabel } = await import(process.env.BREADCRUMBS_MODULE);
        const lines = JSON.parse(process.env.BREADCRUMB_LINES);
        const items = JSON.parse(process.env.BREADCRUMB_ITEMS);
        const t = (key) => lines[key] ?? key;
        process.stdout.write(JSON.stringify(items.map((item) => breadcrumbLabel(item, t))));
        JS;

    $result = Process::env([
        'BREADCRUMBS_MODULE' => $module,
        'BREADCRUMB_LINES' => json_encode($translations, JSON_THROW_ON_ERROR),
        'BREADCRUMB_ITEMS' => json_encode($items, JSON_THROW_ON_ERROR),
    ])->run(['node', '--no-warnings', '--input-type=module', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());

    return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
}

test('breadcrumb data labels are not translated', function () {
    // "Admin" is also a static label with a Spanish line.
    $spanish = Translations::for('es');
    expect($spanish['Admin'])->toBe('Administración');

    $organization = Organization::factory()->create(['name' => 'Admin']);

    $admin = superadmin();
    $admin->forceFill(['locale' => 'es'])->save();

    $this->actingAs($admin)
        ->get(route('admin.organizations.show', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/organizations/show')
            ->where('organization.name', 'Admin'));

    // The admin organization page's breadcrumbs, as the page builds them:
    // static labels are keys, the organization name is data.
    $labels = breadcrumbLabels([
        ['titleKey' => 'Admin', 'href' => '/admin'],
        ['titleKey' => 'Organizations', 'href' => '/admin/organizations'],
        ['title' => $organization->name, 'href' => '/admin/organizations/'.$organization->id],
    ], $spanish);

    expect($labels)->toBe(['Administración', 'Organizaciones', 'Admin']);

    $page = File::get(resource_path('js/pages/admin/organizations/show.tsx'));
    expect($page)->toContain("{ titleKey: 'Admin', href: adminIndex() }")
        ->and($page)->toContain('title: props.organization.name,');

    // The card code is data too.
    expect(File::get(resource_path('js/pages/cards/show.tsx')))
        ->toContain('{ title: props.card.code, href: show(props.card) }');

    // The component translates only through breadcrumbLabel.
    $component = File::get(resource_path('js/components/breadcrumbs.tsx'));
    expect($component)->toContain('breadcrumbLabel(item, t)')
        ->and($component)->not->toContain('t(item.title)');
});

test('every static breadcrumb label is a translation key', function () {
    // Inside a page's `breadcrumbs: [...]`, a quoted string is a static
    // label and must use `titleKey`; `title` is only for data.
    foreach (File::allFiles(resource_path('js/pages')) as $file) {
        $source = $file->getContents();
        $start = strpos($source, 'breadcrumbs:');

        if ($start === false) {
            continue;
        }

        $block = substr($source, $start, strpos($source, ']', $start) - $start);

        expect($block)->not->toMatch("/\\btitle:\\s*['\"]/", $file->getRelativePathname().' passes a literal breadcrumb title as data');
    }
});

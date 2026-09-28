<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\ThemeService;
use Livewire\Volt\Volt;

it('prints the saved theme as CSS variables on every page', function () {
    app(ThemeService::class)->save(['brand' => '#0082BB', 'accent' => '#BED600', 'font' => 'nunito', 'corners' => 'crisp', 'wash' => false]);

    $this->get(route('login'))->assertOk()
        ->assertSee('--brand:#0082BB', false)
        ->assertSee('--accent:#BED600', false)
        ->assertSee("--app-font:'Nunito'", false)
        ->assertSee('--radius-scale:0.4', false)
        ->assertSee('--wash-opacity:0', false);
});

it('defaults to Quicksand and the WanderLink palette', function () {
    $css = app(ThemeService::class)->cssVariables();

    expect($css)->toContain('--brand:#0F766E')->toContain("'Quicksand'");
});

it('picks readable text for light and dark brand colours', function () {
    expect(ThemeService::readableOn('#0F766E'))->toBe('#FFFFFF')
        ->and(ThemeService::readableOn('#BED600'))->toBe('#1F2937');
});

it('lets the owner change the theme and rejects bad values', function () {
    $this->actingAs(User::factory()->withRole(Role::Owner)->create());

    Volt::test('pages.admin.appearance')
        ->call('applyPreset', 'ocean')->assertSet('brand', '#0082BB')
        ->set('font', 'jakarta')
        ->call('save')->assertHasNoErrors()
        ->set('brand', 'red')->call('save')->assertHasErrors('brand')
        ->set('brand', '#0082BB')->set('font', 'comic-sans')->call('save')->assertHasErrors('font');

    expect(app(ThemeService::class)->current())->toMatchArray(['brand' => '#0082BB', 'font' => 'jakarta', 'preset' => 'ocean']);
});

it('keeps Appearance away from consultants', function () {
    $this->actingAs(User::factory()->withRole(Role::Consultant)->create())
        ->get(route('admin.appearance'))->assertForbidden();
});

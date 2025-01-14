<?php

namespace Tests\Feature\Globals;

use Facades\Tests\Factories\GlobalFactory;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Support\Arr;
use Tests\FakesRoles;
use Tests\PreventSavingStacheItemsToDisk;
use Tests\TestCase;

class ViewGlobalsListingTest extends TestCase
{
    use FakesRoles;
    use PreventSavingStacheItemsToDisk;

    /** @test */
    public function it_lists_globals()
    {
        $this->withoutExceptionHandling();
        $this->setTestRoles(['test' => [
            'access cp',
            'edit test_one globals',
            'edit test_three globals',
        ]]);
        $user = User::make()->assignRole('test')->save();
        GlobalFactory::handle('test_one')->create();
        GlobalFactory::handle('test_two')->create();
        GlobalFactory::handle('test_three')->create();

        $this->actingAs($user)
            ->get(cp_route('globals.index'))
            ->assertOk()
            ->assertViewHas('globals', function ($globals) {
                return Arr::get($globals, '0.handle') === 'test_one'
                    && Arr::get($globals, '0.edit_url') === url('/cp/globals/test_one')
                    && Arr::get($globals, '1.handle') === 'test_three'
                    && Arr::get($globals, '1.edit_url') === url('/cp/globals/test_three');
            });
    }

    /** @test */
    public function it_uses_the_configure_url_if_it_doesnt_exist_in_the_selected_site_but_you_have_permission()
    {
        $this->setSites([
            'en' => ['url' => 'http://localhost/', 'locale' => 'en', 'name' => 'English'],
            'fr' => ['url' => 'http://localhost/fr/', 'locale' => 'fr', 'name' => 'French'],
        ]);

        $this->setTestRoles(['test' => [
            'access cp',
            'access en site',
            'access fr site',
            'configure globals',
        ]]);
        $user = User::make()->assignRole('test')->save();
        $one = GlobalFactory::handle('test_one')->create();
        $one->addLocalization($one->makeLocalization('fr'))->save();
        $two = GlobalFactory::handle('test_two')->create();

        Site::setSelected('fr');

        $this->actingAs($user)
            ->get(cp_route('globals.index'))
            ->assertOk()
            ->assertViewHas('globals', function ($globals) {
                return Arr::get($globals, '0.handle') === 'test_one'
                    && Arr::get($globals, '0.edit_url') === url('/cp/globals/test_one?site=fr')
                    && Arr::get($globals, '1.handle') === 'test_two'
                    && Arr::get($globals, '1.edit_url') === url('/cp/globals/test_two/edit');
            });
    }

    /** @test */
    public function it_filters_out_globals_if_it_doesnt_exist_in_the_selected_site_and_you_dont_have_permission_to_configure()
    {
        $this->setSites([
            'en' => ['url' => 'http://localhost/', 'locale' => 'en', 'name' => 'English'],
            'fr' => ['url' => 'http://localhost/fr/', 'locale' => 'fr', 'name' => 'French'],
        ]);

        $this->setTestRoles(['test' => [
            'access cp',
            'access en site',
            'access fr site',
            'edit test_one globals',
            'edit test_three globals',
        ]]);
        $user = User::make()->assignRole('test')->save();
        $one = GlobalFactory::handle('test_one')->create();
        $one->addLocalization($one->makeLocalization('fr'))->save();
        $two = GlobalFactory::handle('test_two')->create();
        $three = GlobalFactory::handle('test_three')->create();

        Site::setSelected('fr');

        $this->actingAs($user)
            ->get(cp_route('globals.index'))
            ->assertOk()
            ->assertViewHas('globals', function ($globals) {
                return $globals->count() === 1;
            })
            ->assertViewHas('globals', function ($globals) {
                return Arr::get($globals, '0.handle') === 'test_one'
                    && Arr::get($globals, '0.edit_url') === url('/cp/globals/test_one?site=fr');
            });
    }

    /** @test */
    public function it_filters_out_globals_in_sites_you_dont_have_permission_to_access()
    {
        $this->setSites([
            'en' => ['url' => 'http://localhost/', 'locale' => 'en', 'name' => 'English'],
            'fr' => ['url' => 'http://localhost/fr/', 'locale' => 'fr', 'name' => 'French'],
        ]);

        $this->setTestRoles(['test' => [
            'access cp',
            'edit fr globals',
            'access fr site',
        ]]);
        $user = User::make()->assignRole('test')->save();
        $one = GlobalFactory::handle('en')->site('en')->create();
        $two = GlobalFactory::handle('fr')->site('fr')->create();

        Site::setSelected('fr');

        $this->actingAs($user)
            ->get(cp_route('globals.index'))
            ->assertOk()
            ->assertViewHas('globals', function ($globals) {
                return $globals->count() === 1;
            })
            ->assertViewHas('globals', function ($globals) {
                $sorted = $globals->sortBy('handle')->values();

                return (Arr::get($sorted, '0.handle') == 'fr') &&
                       (Arr::get($sorted, '0.edit_url') == url('/cp/globals/fr?site=fr'));
            });
    }
}

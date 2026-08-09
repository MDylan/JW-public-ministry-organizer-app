<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Groups\ListUsers;
use App\Models\Group;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 08: a Groups\ListUsers lapozása - és a TODO 48 legkockázatosabb pontja.
 *
 * Ez a komponens NEM a keretrendszer paginate()-jét használja, hanem kézzel
 * épít egy LengthAwarePaginator-t (:915-921):
 *
 *     $current_page = $this->page;
 *     if($current_page < 1) $current_page = 1;
 *     $users = new LengthAwarePaginator($itemsForCurrentPage, $total, 10, $current_page, [...]);
 *
 * A $this->page a Livewire 2 WithPagination trait public $page property-je,
 * amit a setPage() a $paginators tömbbel EGYÜTT ír:
 *
 *     $this->paginators[$pageName] = $page;
 *     $this->{$pageName} = $page;      // <-- ez a sor tűnik el a v3-ban
 *
 * Livewire 3-ban a trait $page property-je megszűnik, és a setPage() már
 * csak a $paginators-t írja. A komponens ugyan saját public $page = 1-et is
 * deklarál (:36), így a property nem szűnik meg - de senki nem fogja
 * frissíteni. A lapozó gombok gotoPage()-et hívnak, az a paginators-t
 * állítja, a render() viszont a $page-et olvassa: a lista HIBAÜZENET NÉLKÜL
 * az 1. oldalon ragadna.
 *
 * Az itteni tesztek épp ezt a kapcsolatot mérik, hogy a TODO 48 alatt
 * elbukjanak, ha a szinkron elvész.
 */
class GroupUserListPaginationTest extends FeatureTestCase
{
    private Group $group;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();

        // Az aktor is tagja a csoportnak, tehát benne van a listában.
        //
        // A rendezés name_index, majd email szerint megy, és a name_index-et
        // a UserObserver által minden íráskor elindított
        // CalulcateUserNameIndexProcess számolja: NÉV szerint rendezi és
        // újraszámozza az összes felhasználót. Az attachManyUsersToGroup()
        // ezért ad egyedi "Page NNN" neveket - a 'Zzz Aktor' pedig a lista
        // végére kerül, így a lapok tartalma kiszámítható.
        $this->actor = $this->createUser([
            'name'  => 'Zzz Aktor',
            'email' => 'zzz-actor@example.test',
        ]);
        $this->attachUserToGroup($this->actor, $this->group, 'admin');
    }

    private function list()
    {
        return Livewire::actingAs($this->actor->fresh())
            ->test(ListUsers::class, ['group' => $this->group->id]);
    }

    private function emailsOnPage($component): array
    {
        return $component->viewData('users')->pluck('email')->values()->all();
    }

    private function expectedEmails(int $from, int $to): array
    {
        $emails = [];
        for ($i = $from; $i <= $to; $i++) {
            $emails[] = 'page-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT).'@example.test';
        }

        return $emails;
    }

    // =========================================================================
    // 1. Lapméret
    // =========================================================================

    public function test_the_member_list_breaks_at_ten_per_page(): void
    {
        // A per_page itt bedrótozott 10 (:914), nem a paginate() 20-a.
        $this->attachManyUsersToGroup($this->group, 25);

        $paginator = $this->list()->viewData('users');

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame(10, $paginator->perPage());
        $this->assertSame(26, $paginator->total(), '25 tag + az aktor.');
        $this->assertSame(3, $paginator->lastPage());
        $this->assertCount(10, $paginator->items());
    }

    // =========================================================================
    // 2. A $this->page és a lap tartalmának összekötése - a v3 kockázat
    // =========================================================================

    public function test_going_to_the_second_page_actually_shows_the_second_ten(): void
    {
        // EZ A TESZT BUKIK EL A LIVEWIRE 3 ALATT, ha a $this->page
        // szinkronizálása elvész: a lista az 1. lap tartalmát adná vissza.
        //
        // A rendezés determinisztikus (name_index = 0 mindenhol, utána
        // email), ezért itt konkrét sorrendre is assertálhatunk.
        $this->attachManyUsersToGroup($this->group, 25);

        $this->assertSame(
            $this->expectedEmails(1, 10),
            $this->emailsOnPage($this->list())
        );

        $this->assertSame(
            $this->expectedEmails(11, 20),
            $this->emailsOnPage($this->list()->call('gotoPage', 2))
        );
    }

    public function test_the_last_page_holds_the_remainder_and_the_actor(): void
    {
        $this->attachManyUsersToGroup($this->group, 25);

        $this->assertSame(
            array_merge($this->expectedEmails(21, 25), ['zzz-actor@example.test']),
            $this->emailsOnPage($this->list()->call('gotoPage', 3))
        );
    }

    public function test_goto_page_writes_both_the_page_property_and_the_paginators_array(): void
    {
        // KARAKTERIZÁLÓ TESZT a rejtett előfeltételről.
        //
        // A WithPagination::setPage() a v2-ben KÉTFELÉ ír, és a komponens
        // működése ezen a kettősségen áll: a render() a $page-et olvassa, a
        // lapozó nézet viszont a $paginators-ból dolgozik. A v3 setPage()-e
        // már csak a $paginators-t írja - ez a teszt teszi a különbséget
        // mérhetővé.
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('gotoPage', 2);

        $this->assertSame(2, $component->get('page'));
        $this->assertSame(['page' => 2], $component->get('paginators'));
    }

    public function test_next_and_previous_page_move_through_the_list(): void
    {
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('nextPage');
        $this->assertSame($this->expectedEmails(11, 20), $this->emailsOnPage($component));

        $component->call('nextPage');
        $this->assertSame(3, $component->get('page'));

        $component->call('previousPage');
        $this->assertSame($this->expectedEmails(11, 20), $this->emailsOnPage($component));
    }

    public function test_a_page_number_below_one_is_clamped_to_the_first_page(): void
    {
        // Két védelem fut egymás után: a setPage() a nem pozitív értéket
        // 1-re emeli, a render() (:916) pedig még egyszer ellenőrzi. A
        // második ma holt kód, de a kézi paginátor miatt indokolt maradnia.
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('gotoPage', 0);

        $this->assertSame(1, $component->get('page'));
        $this->assertSame($this->expectedEmails(1, 10), $this->emailsOnPage($component));
    }

    public function test_a_page_number_beyond_the_last_page_yields_an_empty_slice(): void
    {
        // A kézi paginátor nem korrigál felfelé: a slice() üres tömböt ad,
        // a total() viszont a teljes elemszámot mutatja. A keretrendszer
        // paginate()-je ugyanígy viselkedik, tehát ez nem eltérés - de a
        // felhasználó üres listát lát a lapozó alatt.
        $this->attachManyUsersToGroup($this->group, 25);

        $paginator = $this->list()->call('gotoPage', 9)->viewData('users');

        $this->assertCount(0, $paginator->items());
        $this->assertSame(26, $paginator->total());
    }

    // =========================================================================
    // 3. A resetPage() hívói - és akik nem hívják
    // =========================================================================

    public function test_searching_resets_the_cursor(): void
    {
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('gotoPage', 3);
        $this->assertSame(3, $component->get('page'));

        $component->set('searchTerm', 'page-0');

        $this->assertSame(1, $component->get('page'));
    }

    public function test_filter_myself_resets_the_cursor(): void
    {
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('gotoPage', 3)->call('filterMyself');

        $this->assertSame(1, $component->get('page'));
        $this->assertSame(['zzz-actor@example.test'], $this->emailsOnPage($component));
    }

    public function test_filter_off_resets_the_cursor(): void
    {
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('gotoPage', 3)->call('filterOff');

        $this->assertSame(1, $component->get('page'));
        $this->assertSame($this->expectedEmails(1, 10), $this->emailsOnPage($component));
    }

    public function test_the_online_filter_resets_the_cursor_like_every_other_filter(): void
    {
        // MEGFORDÍTVA a v1-patch B2 javításával.
        //
        // Négy metódus hívott resetPage()-et - updatedSearchTerm(),
        // filterMyself(), filterIcon() és filterOff() -, a filterOnline() és a
        // filterInactive() viszont NEM, pedig ugyanúgy szűkíti a találati
        // halmazt. A 3. oldalon állva az "online" szűrőre kattintva a lista
        // ezért üresen maradt, holott volt találat: a felhasználó számára úgy
        // nézett ki, mintha senki nem lenne online.
        $this->attachManyUsersToGroup($this->group, 25);

        User::where('email', 'page-001@example.test')->update(['last_activity' => now()]);

        $component = $this->list()->call('gotoPage', 3)->call('filterOnline');

        $this->assertSame(1, $component->get('page'), 'A szűrés visszaáll az első oldalra.');

        $paginator = $component->viewData('users');
        $this->assertSame(1, $paginator->total(), 'Egy online tag van.');
        $this->assertCount(1, $paginator->items(), 'És most látszik is.');
    }

    public function test_the_inactive_filter_resets_the_cursor_too(): void
    {
        // A filterInactive() ugyanannak a hibának a másik fele volt; a
        // filterOnline() mellett ez is a v1-patch B2 hatálya alá tartozik.
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('gotoPage', 3)->call('filterInactive');

        $this->assertSame(1, $component->get('page'), 'A szűrés visszaáll az első oldalra.');
    }

    // =========================================================================
    // 4. A szerep és a lapszám összefüggése
    // =========================================================================

    public function test_a_non_editor_sees_fewer_pages_because_pending_members_are_hidden(): void
    {
        // A render() (:878-881) csak szerkesztőnek (admin/roler) mutatja a
        // még el nem fogadott tagságokat. A lapszám tehát szerepfüggő -
        // ugyanaz a csoport két felhasználónak más terjedelmű.
        $this->attachManyUsersToGroup($this->group, 12, 'member', true);
        $this->attachManyUsersToGroup($this->group, 8, 'member', false, 'pending');

        $viewer = $this->createUser(['email' => 'zzy-viewer@example.test']);
        $this->attachUserToGroup($viewer, $this->group, 'member');

        // Az aktor admin: mindenkit lát (12 + 8 + aktor + viewer = 22).
        $this->assertSame(22, $this->list()->viewData('users')->total());

        $asViewer = Livewire::actingAs($viewer->fresh())
            ->test(ListUsers::class, ['group' => $this->group->id]);

        $this->assertSame(14, $asViewer->viewData('users')->total(), '12 elfogadott + aktor + viewer.');
        $this->assertSame(2, $asViewer->viewData('users')->lastPage());
    }

    // =========================================================================
    // 5. A keresés a lapozott halmazon
    // =========================================================================

    public function test_the_search_filters_in_memory_after_the_query(): void
    {
        // A keresés NEM az adatbázisban fut: a render() (:902-911) a már
        // lekérdezett kollekciót szűri, mert a név titkosítva van tárolva.
        // A lapozás tehát a szűrt kollekción történik, és a total() a
        // találatok számát mutatja - nem a csoport méretét.
        $this->attachManyUsersToGroup($this->group, 25);

        $paginator = $this->list()->set('searchTerm', 'page-01')->viewData('users');

        $this->assertSame(10, $paginator->total(), 'page-010 .. page-019.');
        $this->assertSame(1, $paginator->lastPage());
    }

    public function test_a_search_with_no_hits_yields_an_empty_paginator(): void
    {
        $this->attachManyUsersToGroup($this->group, 25);

        $paginator = $this->list()->set('searchTerm', 'nincs-ilyen')->viewData('users');

        $this->assertSame(0, $paginator->total());
        $this->assertCount(0, $paginator->items());
    }

    // =========================================================================
    // 6. A renderelt lapozó
    // =========================================================================

    public function test_the_pagination_control_renders_every_page_link_in_a_small_list(): void
    {
        // A nézet az EGYETLEN hely, ahol a links() argumentumot kap:
        // {{$users->onEachSide(1)->links()}} (list-users.blade.php:312).
        // Kis oldalszámnál a Laravel ablakolása minden lapot megmutat,
        // elválasztó nélkül.
        $this->attachManyUsersToGroup($this->group, 25);

        $this->list()
            ->assertSee('wire:click="gotoPage(2, \'page\')"', false)
            ->assertSee('wire:click="gotoPage(3, \'page\')"', false)
            ->assertDontSee('<span class="page-link">...</span>', false);
    }

    public function test_no_pagination_control_is_rendered_for_a_single_page(): void
    {
        $this->attachManyUsersToGroup($this->group, 5);

        $this->list()->assertDontSee('class="pagination"', false);
    }
}

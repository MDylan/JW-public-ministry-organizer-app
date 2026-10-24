<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\Groups\NewsList;
use App\Http\Livewire\Groups\PosterEditModal;
use App\Models\GroupPosters;
use App\Support\Html\SafeHtml;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * Roadmap TODO 70 (ported to the v1 line by TODO 70.1): group-authored rich text is filtered (App\Support\Html\SafeHtml).
 *
 * News content and poster text were printed raw, so a stored script ran in
 * every member's session. They are cleaned on output (stored content
 * included), on save, and before they are loaded into the editor, where an
 * onerror attribute would run for whoever opens it.
 */
class StoredHtmlSanitizingTest extends FeatureTestCase
{
    private const ATTACK = '<p>Hello <b>world</b></p><script>alert(1)</script>'
        .'<img src="x" onerror="alert(2)"><a href="javascript:alert(3)">x</a>';

    public function test_scripts_handlers_and_script_urls_are_removed(): void
    {
        $clean = SafeHtml::clean(self::ATTACK.'<iframe src="https://evil.test"></iframe><a href="data:text/html,<script>alert(4)</script>">y</a>');

        foreach (['<script', 'onerror', 'javascript:', '<iframe', 'data:text/html'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $clean);
        }
    }

    public function test_the_editors_formatting_survives(): void
    {
        $html = '<p style="text-align: center;"><b>Bold</b> <i>it</i> <u>u</u></p><ul><li>one</li></ul>'
            .'<a href="https://example.test/x" target="_blank">link</a>'
            .'<table><tbody><tr><td colspan="2">cell</td></tr></tbody></table>'
            .'<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==" alt="pic">';

        $clean = SafeHtml::clean($html);

        // Compared without spaces: the purifier normalises "a: b" in styles.
        $flat = str_replace(' ', '', $clean);
        foreach (['text-align:center', '<b>Bold</b>', '<i>it</i>', '<ul><li>one</li></ul>', 'href="https://example.test/x"', 'colspan="2"', 'data:image/png;base64'] as $kept) {
            $this->assertStringContainsString($kept, $flat);
        }
        $this->assertStringContainsString('noopener', $clean);
    }

    public function test_stored_news_content_is_printed_clean(): void
    {
        $group = $this->createGroup();
        $member = $this->createUser(['email' => 'xss-news@example.test']);
        $this->attachUserToGroup($member, $group);
        $this->createGroupNews($group, $member, [config('app.locale', 'hu') => ['title' => 'T', 'content' => self::ATTACK]]);

        Livewire::actingAs($member)->test(NewsList::class, ['group' => $group])
            ->assertSee('<b>world</b>', false)
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('onerror', false);
    }

    public function test_a_poster_is_stored_clean_and_reaches_the_editor_clean(): void
    {
        $group = $this->createGroup();
        $admin = $this->createUser(['email' => 'xss-poster@example.test']);
        $this->attachUserToGroup($admin, $group, 'admin');

        Livewire::actingAs($admin)->test(PosterEditModal::class)
            ->call('openModal', $group->id, 0)
            ->set('state.show_date', now()->toDateString())
            ->set('state.info', self::ATTACK)
            ->call('savePoster');

        $stored = GroupPosters::where('group_id', $group->id)->firstOrFail();
        $this->assertStringNotContainsString('onerror', $stored->info);
        $this->assertStringContainsString('<b>world</b>', $stored->info);

        // A poster stored before the fix, opened in the editor.
        $stored->forceFill(['info' => self::ATTACK])->save();
        // v1 has no poster-editor-opened event; the editor is filled from state.info.
        $info = Livewire::actingAs($admin)->test(PosterEditModal::class)
            ->call('openModal', $group->id, $stored->id)
            ->get('state')['info'];
        $this->assertStringNotContainsString('onerror', $info);
    }
}

<?php

use Illuminate\Support\Facades\Blade;

// Render the real profile body independently of the shared layout's services.
function renderPeopleProfile(array $profile): string
{
    $source = file_get_contents(resource_path('views/friends/person.blade.php'));
    $source = preg_replace('/<x-layouts\.app[^>]*>/', '<main>', $source);
    $source = str_replace('</x-layouts.app>', '</main>', $source);
    return Blade::render($source, ['p' => $profile], deleteCachedView: true);
}

test('a discovered numeric username renders its public details without private contact actions', function () {
    $html = renderPeopleProfile([
        'id' => 7, 'name' => 'Public Person', 'profile_name' => 'Public Person',
        'username' => '111', 'avatar_url' => null, 'is_friend' => false,
        'is_team' => false, 'is_former' => false, 'can_talk' => false,
    ]);
    expect($html)->toContain('Public Person', '111', 'Profile Name', 'Become friends to chat, call or meet.')
        ->not->toContain('@php', '@foreach', 'mailto:', 'startVideo(', 'Chat history');
    expect(substr_count($html, '<button disabled'))->toBe(5);
});

test('a related profile renders details and authorized contact links', function () {
    $html = renderPeopleProfile([
        'id' => 7, 'name' => 'Related Person', 'username' => '111', 'avatar_url' => null,
        'is_friend' => true, 'is_team' => false, 'is_former' => false, 'can_talk' => true,
        'chat_url' => '/friends/chat/u/111', 'email' => 'related@example.test',
        'member_since' => 'October 2026',
    ]);
    expect($html)->toContain('Related Person', 'related@example.test', 'October 2026', 'mailto:related@example.test', 'Chat history')
        ->not->toContain('@php', '@foreach', 'Become friends to chat, call or meet.');
});

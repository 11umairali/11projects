<?php

namespace App\Services\Friends;

use App\Models\User;
use App\Support\PhoneKey;
use Illuminate\Support\Facades\DB;

/** Address-book names belong to the viewer, never to the public user profile. */
class ContactNames
{
    private array $books = [];

    public function sync(User $viewer, array $contacts): void
    {
        DB::transaction(function () use ($viewer, $contacts) {
            // Serialize simultaneous syncs for the same account.
            User::whereKey($viewer->id)->lockForUpdate()->firstOrFail();
            DB::table('device_contact_names')->where('viewer_id', $viewer->id)->delete();
            $rows = [];
            foreach ($contacts as $contact) {
                $name = trim($contact['name']);
                if ($name !== '') $rows[$contact['key']] = ['viewer_id' => $viewer->id, 'phone_key' => $contact['key'], 'name' => $name];
            }
            foreach (array_chunk(array_values($rows), 200) as $chunk) DB::table('device_contact_names')->insert($chunk);
        });
        unset($this->books[$viewer->id]);
    }

    public function fields(User $viewer, $person): array
    {
        $book = $this->books[$viewer->id] ??= DB::table('device_contact_names')->where('viewer_id', $viewer->id)->pluck('name', 'phone_key')->all();
        $key = PhoneKey::of($person->phone ?? null);
        $name = $key ? ($book[$key] ?? null) : null;
        return ['name' => $name ?: $person->name, 'profile_name' => $person->name, 'contact_name' => $name];
    }
}

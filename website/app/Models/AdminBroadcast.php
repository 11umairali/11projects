<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AdminBroadcast extends Model {
    protected $guarded = ['id'];
    protected $casts = ['push' => 'boolean', 'email' => 'boolean'];
    public function renderFor(User $user, string $text): string {
        return strtr($text, ['{{name}}' => $user->name, '{{email}}' => $user->email]);
    }
}

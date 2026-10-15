<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\Friends\FriendService;
use App\Services\Friends\PhoneVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class FriendsController extends Controller
{
    private function reply(array $res): JsonResponse
    {
        return response()->json(['message' => $res[1]], $res[0] ? 200 : 422);
    }

    public function overview(Request $request): JsonResponse
    {
        $u = $request->user();
        return response()->json(['data' => array_merge(app(FriendService::class)->overview($u), ['phone' => app(PhoneVerifier::class)->status($u)], ['features' => \App\Support\FriendSettings::features()])]);
    }

    /** The app sends the phone keys of the person's PHONE contacts; the answer is who of them is on the platform and findable. */
    public function syncContacts(Request $request): JsonResponse
    {
        $d = $request->validate(['directory' => 'sometimes|boolean', 'keys' => 'present|array|max:3000', 'keys.*' => ['string', 'regex:/^\\d{8,10}$/'], 'contacts' => 'sometimes|array|max:3000', 'contacts.*.key' => ['required', 'string', 'regex:/^\\d{8,10}$/'], 'contacts.*.name' => 'required|string|max:255']);
        $directory = $d['directory'] ?? false;
        $limiter = ($directory ? 'friend-directory:' : 'friend-sync:') . $request->user()->id;
        if (RateLimiter::tooManyAttempts($limiter, $directory ? 20 : 60)) return response()->json(['message' => 'Contact sync limit reached. Please try again later.'], 429);
        RateLimiter::hit($limiter, $directory ? 60 : 86400);
        if (array_key_exists('contacts', $d)) app(\App\Services\Friends\ContactNames::class)->sync($request->user(), $d['contacts']);
        return response()->json(['data' => (($d['directory'] ?? false) ? app(FriendService::class)->contactsForKeys($request->user(), $d['keys']) : app(FriendService::class)->suggestionsForKeys($request->user(), $d['keys']))]);
    }

    public function send(Request $request): JsonResponse
    {
        $d = $request->validate(['user_id' => 'required|integer']);
        return $this->reply(app(FriendService::class)->send($request->user(), (int) $d['user_id']));
    }

    public function respond(Request $request, int $id, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['accept', 'decline', 'block'], true), 404);
        return $this->reply(app(FriendService::class)->respond($request->user(), $id, $action));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        return $this->reply(app(FriendService::class)->cancel($request->user(), $id));
    }

    public function remove(Request $request, int $userId): JsonResponse
    {
        return $this->reply(app(FriendService::class)->remove($request->user(), $userId));
    }

    public function dismiss(Request $request, int $userId): JsonResponse
    {
        return $this->reply(app(FriendService::class)->dismiss($request->user(), $userId));
    }

    public function phoneStart(Request $request): JsonResponse
    {
        $d = $request->validate(['phone_country' => 'required|string|size:2', 'phone_national' => 'required|string|max:30', 'channel' => 'nullable|in:sms,whatsapp']);
        [$e164, $err] = \App\Services\Friends\RegistrationPhone::build($d['phone_country'], $d['phone_national']);
        if ($err || !$e164) return response()->json(['message' => $err ?: 'Enter a valid phone number.'], 422);
        $pv = app(PhoneVerifier::class);
        // Verification switched off by the super admin: just save the number (it stays unverified).
        return $this->reply($pv->available() ? $pv->sendCode($request->user(), $e164, $d['channel'] ?? null) : $pv->saveUnverified($request->user(), $e164));
    }

    public function phoneVerify(Request $request): JsonResponse
    {
        $d = $request->validate(['code' => 'required|string|max:10']);
        return $this->reply(app(PhoneVerifier::class)->verify($request->user(), $d['code']));
    }

    public function phoneRemove(Request $request): JsonResponse
    {
        app(PhoneVerifier::class)->remove($request->user());
        return response()->json(['message' => 'Phone number removed.']);
    }

    public function discoverable(Request $request): JsonResponse
    {
        $d = $request->validate(['discoverable' => 'required|boolean']);
        return $this->reply(app(PhoneVerifier::class)->setDiscoverable($request->user(), (bool) $d['discoverable']));
    }
}

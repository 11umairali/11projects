<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Services\Recording\RecordingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Opens a meeting recording for the people it was delivered to (website link in the meeting chat / notification, and the app). */
class MeetingRecordingController extends Controller
{
    public function file(Request $request, int $id)
    {
        $f = app(RecordingService::class)->fileForUser($request->user(), $id);
        abort_unless($f, 404);
        $abs = Storage::disk('local')->path($f->file_path);
        abort_unless(is_file($abs), 404);
        $h = ['Content-Type' => $f->file_mime ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600'];
        return $request->boolean('download')
            ? response()->download($abs, $f->file_name ?: 'recording', $h)
            : response()->file($abs, $h + ['Content-Disposition' => 'inline; filename="' . ($f->file_name ?: 'recording') . '"']);
    }
}

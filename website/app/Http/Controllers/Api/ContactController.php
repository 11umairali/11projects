<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    use AuthorizesApiActions;
    /**
     * List contacts with search, tag filter, segment filter, and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $query = Contact::where('workspace_id', $workspaceId)
            ->with('tags');

        // Search by name, email, company
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by tag
        if ($tagId = $request->input('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        // Filter by country
        if ($country = $request->input('country')) {
            $query->where('country', $country);
        }

        // Filter by lead score range
        if ($request->has('min_score')) {
            $query->where('lead_score', '>=', (int) $request->input('min_score'));
        }
        if ($request->has('max_score')) {
            $query->where('lead_score', '<=', (int) $request->input('max_score'));
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['first_name', 'last_name', 'email', 'company', 'lead_score', 'created_at', 'last_contacted_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->input('per_page', 25), 100);

        return ContactResource::collection($query->paginate($perPage));
    }

    /**
     * Create a new contact.
     */
    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'create')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        // Enforce plan limit on contacts
        if ($deny = $this->denyUnlessPlanAllows($request, 'contacts')) return $deny;

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:100',
            'lead_score' => 'nullable|integer|min:0|max:100',
            'custom_fields' => 'nullable|array',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => ['integer', \Illuminate\Validation\Rule::exists('tags', 'id')->where('workspace_id', $request->user()->active_workspace_id)],
        ], [
            'first_name.required' => 'Please enter the contact\'s first name.',
            'first_name.max' => 'First name is too long (max 255 characters).',
            'email.required' => 'Please enter an email address.',
            'email.email' => 'Please enter a valid email address (e.g., user@example.com).',
            'email.max' => 'Email address is too long (max 255 characters).',
            'phone.max' => 'Phone number is too long (max 50 characters).',
            'company.max' => 'Company name is too long (max 255 characters).',
            'last_name.max' => 'Last name is too long (max 255 characters).',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for duplicate email within workspace
        $exists = Contact::where('workspace_id', $workspaceId)
            ->where('email', $request->input('email'))
            ->exists();

        if ($exists) {
            return response()->json([
                'errors' => ['email' => ['A contact with this email already exists in this workspace.']],
            ], 422);
        }

        $contact = Contact::create([
            'workspace_id' => $workspaceId,
            ...$validator->validated(),
        ]);

        if ($request->has('tag_ids')) {
            $tagIds = $request->input('tag_ids');
            $contact->tags()->sync($tagIds);
            // Contact is freshly created so every attached tag is newly added
            foreach (\App\Models\Tag::whereIn('id', $tagIds)->get() as $addedTag) {
                try { event(new \App\Events\TagAdded($contact, $addedTag)); } catch (\Throwable $e) {}
            }
        }

        $contact->load('tags');

        return (new ContactResource($contact))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single contact.
     */
    public function show(Request $request, int $id): ContactResource|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $contact = Contact::where('workspace_id', $workspaceId)
            ->with('tags')
            ->withCount(['conversations', 'deals'])
            ->find($id);

        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        return new ContactResource($contact);
    }

    /**
     * Update a contact.
     */
    public function update(Request $request, int $id): ContactResource|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $contact = Contact::where('workspace_id', $workspaceId)->find($id);

        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'sometimes|required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:100',
            'lead_score' => 'nullable|integer|min:0|max:100',
            'custom_fields' => 'nullable|array',
            'status' => 'nullable|string|in:active,inactive,unsubscribed',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => ['integer', \Illuminate\Validation\Rule::exists('tags', 'id')->where('workspace_id', $request->user()->active_workspace_id)],
        ], [
            'first_name.required' => 'Please enter the contact\'s first name.',
            'first_name.max' => 'First name is too long (max 255 characters).',
            'email.required' => 'Please enter an email address.',
            'email.email' => 'Please enter a valid email address (e.g., user@example.com).',
            'email.max' => 'Email address is too long (max 255 characters).',
            'phone.max' => 'Phone number is too long (max 50 characters).',
            'company.max' => 'Company name is too long (max 255 characters).',
            'last_name.max' => 'Last name is too long (max 255 characters).',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check duplicate email if changing
        if ($request->has('email') && $request->input('email') !== $contact->email) {
            $exists = Contact::where('workspace_id', $workspaceId)
                ->where('email', $request->input('email'))
                ->where('id', '!=', $contact->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'errors' => ['email' => ['A contact with this email already exists in this workspace.']],
                ], 422);
            }
        }

        $data = $validator->validated();
        unset($data['tag_ids']);
        $contact->update($data);

        if ($request->has('tag_ids')) {
            $tagIds = $request->input('tag_ids');
            $existingTagIds = $contact->tags()->pluck('tags.id')->all();
            $contact->tags()->sync($tagIds);

            $newlyAddedTagIds = array_diff($tagIds, $existingTagIds);
            if (!empty($newlyAddedTagIds)) {
                foreach (\App\Models\Tag::whereIn('id', $newlyAddedTagIds)->get() as $addedTag) {
                    try { event(new \App\Events\TagAdded($contact, $addedTag)); } catch (\Throwable $e) {}
                }
            }
        }

        $contact->load('tags');

        return new ContactResource($contact);
    }

    /**
     * Delete a contact (soft delete).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $contact = Contact::where('workspace_id', $workspaceId)->find($id);

        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        $contact->delete();

        return response()->json(null, 204);
    }

    /**
     * Import contacts from a CSV file.
     */
    public function import(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'create')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        // Enforce plan limit on contacts
        if ($deny = $this->denyUnlessPlanAllows($request, 'contacts')) return $deny;

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');

        // Security: Scan uploaded file before processing
        $scanner = app(\App\Services\FileSecurityService::class);
        $scanResult = $scanner->scan($file);
        if (!$scanResult->passed) {
            return response()->json(['message' => $scanResult->message], 422);
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        $handle = fopen($file->getPathname(), 'r');
        $headers = fgetcsv($handle);

        if (!$headers) {
            fclose($handle);
            return response()->json(['errors' => ['file' => ['Could not read CSV headers.']]], 422);
        }

        // Normalize headers to lowercase
        // 'First Name' (our own export header) and 'first_name' are both accepted, so an export can be re-imported.
        $headers = array_map(fn ($h) => str_replace([' ', '-'], '_', strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)))), $headers);

        $allowedFields = [
            'first_name', 'last_name', 'email', 'phone', 'company',
            'job_title', 'city', 'country', 'timezone', 'lead_score',
        ];

        $row = 1;
        $batch = [];
        $batchSize = 500;
        DB::beginTransaction();

        try {
            while (($data = fgetcsv($handle)) !== false) {
                $row++;
                if (count($data) !== count($headers)) {
                    $skipped++;
                    $errors[] = "Row {$row}: column count mismatch";
                    continue;
                }
                $record = array_combine($headers, $data);

                if (empty($record['email'])) {
                    $skipped++;
                    $errors[] = "Row {$row}: missing email";
                    continue;
                }

                if (!filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
                    $skipped++;
                    $errors[] = "Row {$row}: invalid email '{$record['email']}'";
                    continue;
                }

                $contactData = ['workspace_id' => $workspaceId, 'email' => $record['email']];
                foreach ($allowedFields as $field) {
                    if (isset($record[$field]) && $record[$field] !== '') {
                        // FIX-029: Skip custom_fields column entirely — not allowed via CSV import
                        if ($field === 'custom_fields') continue;

                        // Sanitize CSV values to prevent formula injection
                        $value = $record[$field];
                        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
                            $value = "'" . $value; // Prefix with single quote to neutralize formulas
                        }
                        // Strip null bytes and control characters
                        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
                        $contactData[$field] = $value;
                    }
                }

                $contactData['updated_at'] = now();
                if (!isset($contactData['created_at'])) {
                    $contactData['created_at'] = now();
                }

                $batch[] = $contactData;
                $imported++;

                // Flush batch every 500 rows for performance
                if (count($batch) >= $batchSize) {
                    Contact::upsert($batch, ['workspace_id', 'email'], array_diff(array_keys($batch[0]), ['workspace_id', 'email']));
                    $batch = [];
                }
            }

            // Flush remaining rows
            if (!empty($batch)) {
                Contact::upsert($batch, ['workspace_id', 'email'], array_diff(array_keys($batch[0]), ['workspace_id', 'email']));
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }

        fclose($handle);

        return response()->json([
            'data' => [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 50),
            ],
        ]);
    }

    /**
     * Export contacts as CSV.
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="contacts-export-' . now()->format('Y-m-d') . '.csv"',
        ];

        // Use streaming + chunking to prevent OOM on large datasets
        return response()->stream(function () use ($workspaceId) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'First Name', 'Last Name', 'Email', 'Phone', 'Company',
                'Job Title', 'City', 'Country', 'Timezone', 'Lead Score',
                'Status', 'Tags', 'Created At',
            ]);

            // Chunk to prevent OOM on large contact lists
            Contact::where('workspace_id', $workspaceId)
                ->with('tags')
                ->orderBy('created_at', 'desc')
                ->chunkById(500, function ($contacts) use ($handle) {
                    foreach ($contacts as $contact) {
                        // Sanitize values to prevent CSV formula injection
                        $sanitize = function ($val) {
                            if (is_string($val) && preg_match('/^[=+\-@\t\r]/', $val)) {
                                return "'" . $val;
                            }
                            return $val;
                        };

                        fputcsv($handle, [
                            $sanitize($contact->first_name),
                            $sanitize($contact->last_name),
                            $sanitize($contact->email),
                            $sanitize($contact->phone),
                            $sanitize($contact->company),
                            $sanitize($contact->job_title),
                            $sanitize($contact->city),
                            $sanitize($contact->country),
                            $contact->timezone,
                            $contact->lead_score,
                            $contact->status,
                            $contact->tags->pluck('name')->implode(', '),
                            $contact->created_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }
}

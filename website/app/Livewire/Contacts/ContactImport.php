<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Models\Workspace;
use App\Services\PlanLimitService;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class ContactImport extends Component
{
    use WithFileUploads;
    use AuthorizesWorkspaceActions;

    #[Validate('required|file|mimes:csv,txt|max:10240')]
    public $csvFile = null;

    public array $previewRows = [];
    public array $csvHeaders = [];
    public array $fieldMapping = [];
    public int $totalRows = 0;
    public bool $hasPreview = false;
    public bool $importing = false;
    public int $importedCount = 0;
    public int $skippedCount = 0;
    public bool $importComplete = false;
    public int $progressPercent = 0;

    private array $contactFields = [
        '' => '-- Skip --',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'company' => 'Company',
        'job_title' => 'Job Title',
        'city' => 'City',
        'country' => 'Country',
        'timezone' => 'Timezone',
    ];

    public function getContactFieldsProperty(): array
    {
        return $this->contactFields;
    }

    public function updatedCsvFile(): void
    {
        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $this->parsePreview();
    }

    private function parsePreview(): void
    {
        if (!$this->csvFile) {
            return;
        }

        $path = $this->csvFile->getRealPath();
        $handle = fopen($path, 'r');

        if (!$handle) {
            session()->flash('error', 'Could not read the CSV file.');
            return;
        }

        // Read header row
        $this->csvHeaders = fgetcsv($handle);
        if (!$this->csvHeaders) {
            fclose($handle);
            session()->flash('error', 'CSV file appears to be empty.');
            return;
        }

        // Auto-map headers to fields
        $this->fieldMapping = [];
        foreach ($this->csvHeaders as $index => $header) {
            $normalized = strtolower(trim(str_replace([' ', '_', '-'], '', $header)));
            $mapped = '';

            foreach ($this->contactFields as $key => $label) {
                if ($key === '') continue;
                $normalizedField = strtolower(str_replace([' ', '_'], '', $label));
                if ($normalized === $normalizedField || $normalized === str_replace('_', '', $key)) {
                    $mapped = $key;
                    break;
                }
            }

            $this->fieldMapping[$index] = $mapped;
        }

        // Read preview rows (first 5)
        $this->previewRows = [];
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            if (count($this->previewRows) < 5) {
                $this->previewRows[] = $row;
            }
        }

        $this->totalRows = $rowCount;
        $this->hasPreview = true;

        fclose($handle);
    }

    public function importContacts(): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        if ($this->importing) return;
        $this->importing = true;

        if (!$this->csvFile || empty($this->fieldMapping)) {
            return;
        }

        // Ensure at least email or first_name is mapped
        if (!in_array('email', $this->fieldMapping) && !in_array('first_name', $this->fieldMapping)) {
            session()->flash('error', 'Please map at least an Email or First Name field.');
            return;
        }

        // Enforce plan limit: check remaining quota before starting import
        $workspaceId = auth()->user()->active_workspace_id;
        $workspace = Workspace::findOrFail($workspaceId);
        $limitService = app(PlanLimitService::class);
        $remaining = $limitService->getRemainingQuota($workspace, 'contacts');

        if ($remaining !== null && $remaining <= 0) {
            session()->flash('error', 'You have reached your plan limit for contacts. Please upgrade your plan.');
            return;
        }

        if ($remaining !== null && $this->totalRows > $remaining) {
            session()->flash('error', "Your plan allows {$remaining} more contact(s), but the CSV contains {$this->totalRows} rows. Please upgrade or reduce the file.");
            return;
        }

        $this->importing = true;
        $this->importedCount = 0;
        $this->skippedCount = 0;
        $this->progressPercent = 0;

        $workspaceId = auth()->user()->active_workspace_id;
        $path = $this->csvFile->getRealPath();
        $handle = fopen($path, 'r');

        // Skip header
        fgetcsv($handle);

        $batch = [];
        $batchSize = 100;
        $processedRows = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $contactData = ['workspace_id' => $workspaceId, 'status' => 'active'];

            foreach ($this->fieldMapping as $csvIndex => $field) {
                if ($field === '' || !isset($row[$csvIndex])) {
                    continue;
                }
                $contactData[$field] = $this->sanitizeCsvValue(trim($row[$csvIndex]));
            }

            // Skip rows without essential data
            if (empty($contactData['email']) && empty($contactData['first_name'])) {
                $this->skippedCount++;
                $processedRows++;
                continue;
            }

            // Validate email if provided
            if (!empty($contactData['email']) && !filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                $this->skippedCount++;
                $processedRows++;
                continue;
            }

            // FIX-019: Use insertOrIgnore to handle race conditions atomically
            // The unique constraint on (workspace_id, email) prevents duplicates at DB level
            $batch[] = $contactData;
            $processedRows++;

            if (count($batch) >= $batchSize) {
                $this->insertBatch($batch);
                $batch = [];
                // Update progress
                $this->progressPercent = $this->totalRows > 0
                    ? (int) round(($processedRows / $this->totalRows) * 100)
                    : 0;
            }
        }

        // Insert remaining
        if (!empty($batch)) {
            $this->insertBatch($batch);
        }

        fclose($handle);

        $this->progressPercent = 100;
        $this->importing = false;
        $this->importComplete = true;

        session()->flash('success', "Import complete: {$this->importedCount} imported, {$this->skippedCount} skipped.");
        $this->dispatch('import-completed');
    }

    public array $importErrors = [];

    /**
     * Batch insert contacts using insertOrIgnore for performance.
     * Falls back to individual inserts only for rows that fail.
     */
    private function insertBatch(array &$batch): void
    {
        // Add timestamps to all records for bulk insert
        $now = now();
        $records = array_map(function ($data) use ($now) {
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            return $data;
        }, $batch);

        try {
            $inserted = Contact::insertOrIgnore($records);
            $this->importedCount += $inserted;
            $this->skippedCount += (count($records) - $inserted);
        } catch (\Exception $e) {
            // Fall back to individual inserts on batch failure
            foreach ($batch as $data) {
                try {
                    Contact::create($data);
                    $this->importedCount++;
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    $this->skippedCount++;
                } catch (\Exception $e) {
                    $this->skippedCount++;
                    $identifier = $data['email'] ?? $data['first_name'] ?? 'Unknown';
                    $this->importErrors[] = "Row '{$identifier}': " . \Illuminate\Support\Str::limit($e->getMessage(), 100);
                }
            }
        }
    }

    /**
     * Sanitize a CSV value to prevent formula injection.
     * Spreadsheet applications execute formulas starting with =, +, -, @, tab, or CR.
     */
    private function sanitizeCsvValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            $value = "'" . $value;
        }

        return $value;
    }

    public function close(): void
    {
        $this->dispatch('import-completed');
    }

    public function render()
    {
        return view('livewire.contacts.contact-import');
    }
}

<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeliverabilityChecker
{
    /**
     * Common DKIM selectors used by major email providers and ESPs.
     * Ordered by likelihood: most common first to minimize DNS lookups.
     */
    private const DKIM_SELECTORS = [
        'default',
        'google',
        'selector1',  // Microsoft 365
        'selector2',  // Microsoft 365 fallback
        'dkim',
        'mail',
        'k1',          // Mailchimp
        's1',          // Generic
        's2',          // Generic fallback
        'mandrill',    // Mailchimp Transactional
        'smtp',
        'cm',          // Campaign Monitor
        'mxvault',
        'mailo',
    ];

    /**
     * Run a full deliverability check for a domain.
     * Results are cached per-domain per-workspace for 1 hour.
     *
     * @param string $domain  The domain to check (e.g., "example.com")
     * @param int    $workspaceId  Used for cache scoping
     * @return array  Full results with spf, dkim, dmarc, mx, score, and recommendations
     */
    public function checkDomain(string $domain, int $workspaceId): array
    {
        $domain = $this->sanitizeDomain($domain);

        $cacheKey = "deliverability:{$workspaceId}:{$domain}";

        return Cache::remember($cacheKey, 3600, function () use ($domain) {
            $results = [
                'domain' => $domain,
                'checked_at' => now()->toIso8601String(),
                'spf' => $this->checkSpf($domain),
                'dkim' => $this->checkDkim($domain),
                'dmarc' => $this->checkDmarc($domain),
                'mx' => $this->checkMx($domain),
            ];

            $results['overall_score'] = $this->calculateScore($results);
            $results['recommendations'] = $this->getRecommendations($results);

            return $results;
        });
    }

    /**
     * Check SPF (Sender Policy Framework) record for a domain.
     *
     * SPF tells receiving servers which IP addresses / hosts are authorized
     * to send email on behalf of this domain.
     */
    public function checkSpf(string $domain): array
    {
        try {
            $txtRecords = @dns_get_record($domain, DNS_TXT);

            if ($txtRecords === false || empty($txtRecords)) {
                return [
                    'status' => 'fail',
                    'record' => null,
                    'details' => 'No TXT records found for this domain. An SPF record is required to tell receiving servers which hosts are allowed to send email on your behalf.',
                ];
            }

            // Find SPF record (starts with "v=spf1")
            $spfRecord = null;
            $spfCount = 0;

            foreach ($txtRecords as $record) {
                $txt = $record['txt'] ?? '';
                if (str_starts_with(strtolower(trim($txt)), 'v=spf1')) {
                    $spfRecord = $txt;
                    $spfCount++;
                }
            }

            if ($spfCount > 1) {
                return [
                    'status' => 'fail',
                    'record' => $spfRecord,
                    'details' => 'Multiple SPF records found. RFC 7208 requires exactly one SPF record per domain. Having multiple records causes unpredictable behavior and can lead to delivery failures. Merge them into a single record.',
                ];
            }

            if ($spfRecord === null) {
                return [
                    'status' => 'fail',
                    'record' => null,
                    'details' => 'No SPF record found. Without SPF, receiving servers cannot verify that your emails are sent from authorized sources. Add a TXT record starting with "v=spf1" to your DNS.',
                ];
            }

            // Validate the SPF record contents
            return $this->analyzeSpfRecord($spfRecord);

        } catch (\Throwable $e) {
            Log::warning("DeliverabilityChecker: SPF lookup failed for {$domain}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'fail',
                'record' => null,
                'details' => 'Unable to perform DNS lookup for this domain. The domain may not exist or DNS is temporarily unavailable.',
            ];
        }
    }

    /**
     * Check DMARC (Domain-based Message Authentication, Reporting & Conformance) record.
     *
     * DMARC builds on SPF and DKIM to define what to do with emails that fail
     * authentication, and where to send reports.
     */
    public function checkDmarc(string $domain): array
    {
        try {
            $dmarcHost = "_dmarc.{$domain}";
            $txtRecords = @dns_get_record($dmarcHost, DNS_TXT);

            if ($txtRecords === false || empty($txtRecords)) {
                return [
                    'status' => 'fail',
                    'record' => null,
                    'policy' => null,
                    'details' => 'No DMARC record found. DMARC tells receiving servers what to do when SPF or DKIM checks fail. Without it, your emails are more likely to be flagged as spam.',
                ];
            }

            // Find the DMARC record
            $dmarcRecord = null;
            foreach ($txtRecords as $record) {
                $txt = $record['txt'] ?? '';
                if (str_starts_with(strtolower(trim($txt)), 'v=dmarc1')) {
                    $dmarcRecord = $txt;
                    break;
                }
            }

            if ($dmarcRecord === null) {
                return [
                    'status' => 'fail',
                    'record' => null,
                    'policy' => null,
                    'details' => 'TXT records exist at _dmarc.' . $domain . ' but none contain a valid DMARC record (must start with "v=DMARC1").',
                ];
            }

            return $this->analyzeDmarcRecord($dmarcRecord);

        } catch (\Throwable $e) {
            Log::warning("DeliverabilityChecker: DMARC lookup failed for {$domain}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'fail',
                'record' => null,
                'policy' => null,
                'details' => 'Unable to perform DNS lookup for DMARC. The domain may not exist or DNS is temporarily unavailable.',
            ];
        }
    }

    /**
     * Check DKIM (DomainKeys Identified Mail) by probing common selectors.
     *
     * DKIM uses a public key published in DNS to verify that an email was
     * actually sent by the domain owner and hasn't been tampered with in transit.
     */
    public function checkDkim(string $domain, string $selector = 'default'): array
    {
        $foundSelectors = [];

        try {
            foreach (self::DKIM_SELECTORS as $sel) {
                $dkimHost = "{$sel}._domainkey.{$domain}";
                $records = @dns_get_record($dkimHost, DNS_TXT);

                if ($records === false || empty($records)) {
                    // Also try CNAME — some providers use CNAME for DKIM delegation
                    $cnameRecords = @dns_get_record($dkimHost, DNS_CNAME);
                    if ($cnameRecords !== false && !empty($cnameRecords)) {
                        $foundSelectors[] = [
                            'selector' => $sel,
                            'type' => 'CNAME',
                            'target' => $cnameRecords[0]['target'] ?? 'unknown',
                        ];
                        continue;
                    }
                    continue;
                }

                foreach ($records as $record) {
                    $txt = $record['txt'] ?? '';
                    // DKIM records contain "v=DKIM1" or at minimum a "p=" public key tag
                    if (
                        stripos($txt, 'v=dkim1') !== false ||
                        preg_match('/\bp=\s*[A-Za-z0-9+\/=]+/', $txt)
                    ) {
                        $foundSelectors[] = [
                            'selector' => $sel,
                            'type' => 'TXT',
                            'record' => $txt,
                        ];
                        break;
                    }
                }
            }

            if (empty($foundSelectors)) {
                return [
                    'status' => 'fail',
                    'selectors_found' => [],
                    'selectors_checked' => self::DKIM_SELECTORS,
                    'details' => 'No DKIM records found for any common selector. DKIM signing proves your emails are authentic and unmodified. Configure DKIM in your email provider settings.',
                ];
            }

            $selectorNames = array_column($foundSelectors, 'selector');

            return [
                'status' => 'pass',
                'selectors_found' => $foundSelectors,
                'selectors_checked' => self::DKIM_SELECTORS,
                'details' => 'DKIM record(s) found for selector(s): ' . implode(', ', $selectorNames) . '. Your outgoing emails can be cryptographically verified as authentic.',
            ];

        } catch (\Throwable $e) {
            Log::warning("DeliverabilityChecker: DKIM lookup failed for {$domain}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'fail',
                'selectors_found' => [],
                'selectors_checked' => self::DKIM_SELECTORS,
                'details' => 'Unable to perform DNS lookup for DKIM records. The domain may not exist or DNS is temporarily unavailable.',
            ];
        }
    }

    /**
     * Check MX (Mail Exchange) records for the domain.
     *
     * MX records indicate which mail servers accept email for this domain.
     * Without them, the domain cannot receive email at all.
     */
    public function checkMx(string $domain): array
    {
        try {
            $mxRecords = @dns_get_record($domain, DNS_MX);

            if ($mxRecords === false || empty($mxRecords)) {
                return [
                    'status' => 'fail',
                    'records' => [],
                    'details' => 'No MX records found. Without MX records, this domain cannot receive email. This also signals to spam filters that the domain may not be legitimate.',
                ];
            }

            // Sort by priority (lower = higher priority)
            usort($mxRecords, fn ($a, $b) => ($a['pri'] ?? 0) <=> ($b['pri'] ?? 0));

            $parsed = array_map(fn ($r) => [
                'priority' => $r['pri'] ?? 0,
                'host' => $r['target'] ?? $r['host'] ?? 'unknown',
            ], $mxRecords);

            return [
                'status' => 'pass',
                'records' => $parsed,
                'details' => count($parsed) . ' MX record(s) found. '
                    . ($this->hasMxRedundancy($parsed) ? 'Multiple mail servers provide redundancy.' : 'Consider adding a backup MX server for redundancy.'),
            ];

        } catch (\Throwable $e) {
            Log::warning("DeliverabilityChecker: MX lookup failed for {$domain}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'fail',
                'records' => [],
                'details' => 'Unable to perform DNS lookup for MX records. The domain may not exist or DNS is temporarily unavailable.',
            ];
        }
    }

    /**
     * Calculate an overall deliverability score (0-100) based on check results.
     *
     * Weighting rationale:
     * - SPF (30): fundamental — most receivers check this first
     * - DKIM (30): increasingly mandatory (Google/Yahoo 2024 requirements)
     * - DMARC (25): completes the authentication trifecta
     * - MX (15): baseline infrastructure — usually present if you can send at all
     */
    public function calculateScore(array $results): int
    {
        $score = 0;

        // SPF: 30 points
        $spfStatus = $results['spf']['status'] ?? 'fail';
        if ($spfStatus === 'pass') {
            $score += 30;
        } elseif ($spfStatus === 'warning') {
            $score += 15;
        }

        // DKIM: 30 points
        $dkimStatus = $results['dkim']['status'] ?? 'fail';
        if ($dkimStatus === 'pass') {
            $score += 30;
        } elseif ($dkimStatus === 'warning') {
            $score += 15;
        }

        // DMARC: 25 points
        $dmarcStatus = $results['dmarc']['status'] ?? 'fail';
        if ($dmarcStatus === 'pass') {
            $score += 25;
        } elseif ($dmarcStatus === 'warning') {
            $score += 10;
        }

        // MX: 15 points
        $mxStatus = $results['mx']['status'] ?? 'fail';
        if ($mxStatus === 'pass') {
            $score += 15;
        }

        return min(100, max(0, $score));
    }

    /**
     * Generate human-readable, actionable recommendations for each failing check.
     *
     * @return array<int, array{area: string, severity: string, message: string, dns_record: ?string}>
     */
    public function getRecommendations(array $results): array
    {
        $recommendations = [];

        // SPF recommendations
        $spf = $results['spf'] ?? [];
        if (($spf['status'] ?? 'fail') === 'fail') {
            $recommendations[] = [
                'area' => 'SPF',
                'severity' => 'critical',
                'message' => 'Add an SPF record to authorize your mail servers. Without SPF, emails are likely to be rejected or marked as spam.',
                'dns_record' => 'v=spf1 include:_spf.google.com ~all',
            ];
        } elseif (($spf['status'] ?? '') === 'warning') {
            $details = $spf['details'] ?? '';

            if (stripos($details, '+all') !== false) {
                $recommendations[] = [
                    'area' => 'SPF',
                    'severity' => 'high',
                    'message' => 'Your SPF record ends with "+all" which allows any server to send as your domain. Change it to "~all" (soft fail) or "-all" (hard fail).',
                    'dns_record' => null,
                ];
            }

            if (stripos($details, '?all') !== false) {
                $recommendations[] = [
                    'area' => 'SPF',
                    'severity' => 'medium',
                    'message' => 'Your SPF record ends with "?all" (neutral). Tighten it to "~all" (soft fail) or "-all" (hard fail) for better protection.',
                    'dns_record' => null,
                ];
            }

            if (stripos($details, 'lookups') !== false) {
                $recommendations[] = [
                    'area' => 'SPF',
                    'severity' => 'high',
                    'message' => 'Your SPF record may exceed 10 DNS lookups. Flatten your SPF by replacing "include:" directives with their resolved IP addresses, or use an SPF flattening service.',
                    'dns_record' => null,
                ];
            }
        }

        // DKIM recommendations
        $dkim = $results['dkim'] ?? [];
        if (($dkim['status'] ?? 'fail') === 'fail') {
            $recommendations[] = [
                'area' => 'DKIM',
                'severity' => 'critical',
                'message' => 'No DKIM signing detected. Enable DKIM in your email provider (Gmail, Microsoft 365, etc.) to cryptographically sign outgoing emails. As of 2024, Google and Yahoo require DKIM for bulk senders.',
                'dns_record' => null,
            ];
        }

        // DMARC recommendations
        $dmarc = $results['dmarc'] ?? [];
        if (($dmarc['status'] ?? 'fail') === 'fail') {
            $recommendations[] = [
                'area' => 'DMARC',
                'severity' => 'critical',
                'message' => 'Add a DMARC record to tell receivers how to handle emails that fail SPF/DKIM. Start with a monitoring policy (p=none) to collect data, then gradually move to p=quarantine and p=reject.',
                'dns_record' => 'v=DMARC1; p=none; rua=mailto:dmarc-reports@' . ($results['domain'] ?? 'yourdomain.com'),
            ];
        } elseif (($dmarc['status'] ?? '') === 'warning') {
            $policy = $dmarc['policy'] ?? 'none';

            if ($policy === 'none') {
                $recommendations[] = [
                    'area' => 'DMARC',
                    'severity' => 'medium',
                    'message' => 'Your DMARC policy is set to "none" (monitoring only). After reviewing reports and confirming SPF/DKIM alignment, upgrade to "p=quarantine" and eventually "p=reject" for maximum protection.',
                    'dns_record' => null,
                ];
            }

            if (empty($dmarc['has_rua'] ?? false)) {
                $recommendations[] = [
                    'area' => 'DMARC',
                    'severity' => 'low',
                    'message' => 'Add a "rua=" tag to your DMARC record to receive aggregate reports about authentication failures. This helps you monitor and tune your email authentication.',
                    'dns_record' => null,
                ];
            }
        }

        // MX recommendations
        $mx = $results['mx'] ?? [];
        if (($mx['status'] ?? 'fail') === 'fail') {
            $recommendations[] = [
                'area' => 'MX',
                'severity' => 'critical',
                'message' => 'No MX records found. Add MX records pointing to your mail servers. Without MX, your domain cannot receive email and is flagged as suspicious by spam filters.',
                'dns_record' => null,
            ];
        } elseif (count($mx['records'] ?? []) === 1) {
            $recommendations[] = [
                'area' => 'MX',
                'severity' => 'low',
                'message' => 'Only one MX record found. Adding a backup MX server with a higher priority number ensures email delivery when your primary server is unavailable.',
                'dns_record' => null,
            ];
        }

        return $recommendations;
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Sanitize and normalize a domain input.
     */
    private function sanitizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        // Strip protocol if user pasted a URL
        $domain = preg_replace('#^https?://#', '', $domain);

        // Strip path, query, fragment
        $domain = strtok($domain, '/');
        $domain = strtok($domain, '?');
        $domain = strtok($domain, '#');

        // Strip www prefix
        $domain = preg_replace('/^www\./', '', $domain);

        // Remove trailing dot (FQDN notation)
        $domain = rtrim($domain, '.');

        return $domain;
    }

    /**
     * Analyze an SPF record for correctness and security.
     */
    private function analyzeSpfRecord(string $record): array
    {
        $issues = [];
        $lower = strtolower($record);

        // Check the "all" mechanism (how to treat senders not explicitly listed)
        if (str_contains($lower, '+all')) {
            $issues[] = 'Uses "+all" which allows ANY server to send as your domain. This defeats the purpose of SPF.';
            $status = 'warning';
        } elseif (str_contains($lower, '?all')) {
            $issues[] = 'Uses "?all" (neutral) which does not protect against spoofing. Use "~all" or "-all" instead.';
            $status = 'warning';
        } elseif (!str_contains($lower, 'all')) {
            $issues[] = 'No "all" mechanism found. Add "~all" or "-all" at the end to specify how to handle unauthorized senders.';
            $status = 'warning';
        } else {
            $status = 'pass';
        }

        // Estimate DNS lookup count (include, a, mx, exists, redirect each cost 1 lookup; max 10)
        $lookupMechanisms = ['include:', 'a:', 'a/', 'mx:', 'mx/', 'exists:', 'redirect='];
        $lookupCount = 0;
        foreach ($lookupMechanisms as $mechanism) {
            $lookupCount += substr_count($lower, $mechanism);
        }
        // Bare "a" and "mx" without ":" also cause lookups
        if (preg_match('/\ba\b(?!:)/', $lower)) {
            $lookupCount++;
        }
        if (preg_match('/\bmx\b(?!:)/', $lower)) {
            $lookupCount++;
        }

        if ($lookupCount > 10) {
            $issues[] = "Estimated {$lookupCount} DNS lookups (max 10 allowed by RFC 7208). Exceeding this limit will cause SPF to fail. Flatten your record or consolidate includes.";
            $status = 'warning';
        } elseif ($lookupCount > 7) {
            $issues[] = "Estimated {$lookupCount} of 10 allowed DNS lookups. Getting close to the limit. Consider consolidating includes.";
            // Don't downgrade to warning if everything else is fine
        }

        // Check for common misconfigurations
        if (!preg_match('/include:|ip4:|ip6:|a[:\s\/]|mx[:\s\/]/', $lower)) {
            $issues[] = 'SPF record has no include, ip4, ip6, a, or mx mechanisms. It may not authorize any sending sources.';
            $status = 'warning';
        }

        $detail = 'SPF record found';
        if (!empty($issues)) {
            $detail .= ': ' . implode(' ', $issues);
        } else {
            $detail .= ' and properly configured. Authorized senders are specified and a restrictive "all" policy is in place.';
        }

        return [
            'status' => $status,
            'record' => $record,
            'details' => $detail,
            'lookup_count' => $lookupCount,
        ];
    }

    /**
     * Analyze a DMARC record for policy strength and reporting configuration.
     */
    private function analyzeDmarcRecord(string $record): array
    {
        $lower = strtolower($record);
        $issues = [];

        // Parse policy
        $policy = 'none';
        if (preg_match('/;\s*p\s*=\s*(reject|quarantine|none)/i', $record, $matches)) {
            $policy = strtolower($matches[1]);
        } elseif (preg_match('/\bp\s*=\s*(reject|quarantine|none)/i', $record, $matches)) {
            $policy = strtolower($matches[1]);
        }

        // Determine status based on policy
        $status = match ($policy) {
            'reject' => 'pass',
            'quarantine' => 'pass',
            'none' => 'warning',
            default => 'warning',
        };

        // Check for reporting addresses
        $hasRua = (bool) preg_match('/rua\s*=\s*mailto:/i', $record);
        $hasRuf = (bool) preg_match('/ruf\s*=\s*mailto:/i', $record);

        // Build details
        $policyLabel = match ($policy) {
            'reject' => 'Reject (strongest protection)',
            'quarantine' => 'Quarantine (moderate protection)',
            'none' => 'None (monitoring only, no enforcement)',
            default => ucfirst($policy),
        };

        $details = "DMARC record found. Policy: {$policyLabel}.";

        if ($policy === 'none') {
            $issues[] = 'The "none" policy only monitors — it does not block spoofed emails. Move to "quarantine" after reviewing DMARC reports.';
        }

        if (!$hasRua) {
            $issues[] = 'No aggregate reporting address (rua=). Adding one lets you monitor authentication results.';
        }

        if (!$hasRuf) {
            // Not critical — many receivers don't send forensic reports
        }

        if ($hasRua) {
            $details .= ' Aggregate reporting is enabled.';
        }
        if ($hasRuf) {
            $details .= ' Forensic reporting is enabled.';
        }

        if (!empty($issues)) {
            $details .= ' ' . implode(' ', $issues);
        }

        // Check subdomain policy
        $subdomainPolicy = null;
        if (preg_match('/;\s*sp\s*=\s*(reject|quarantine|none)/i', $record, $spMatch)) {
            $subdomainPolicy = strtolower($spMatch[1]);
        }

        return [
            'status' => $status,
            'record' => $record,
            'policy' => $policy,
            'subdomain_policy' => $subdomainPolicy,
            'has_rua' => $hasRua,
            'has_ruf' => $hasRuf,
            'details' => $details,
        ];
    }

    /**
     * Check if MX records provide redundancy (multiple distinct mail servers).
     */
    private function hasMxRedundancy(array $parsed): bool
    {
        if (count($parsed) < 2) {
            return false;
        }

        // Check that there are at least 2 distinct hostnames
        $hosts = array_unique(array_column($parsed, 'host'));

        return count($hosts) >= 2;
    }

    /**
     * Clear the cached results for a domain in a workspace.
     */
    public function clearCache(string $domain, int $workspaceId): void
    {
        $domain = $this->sanitizeDomain($domain);
        Cache::forget("deliverability:{$workspaceId}:{$domain}");
    }
}

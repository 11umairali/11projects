<?php

namespace App\Services\Integrations;

use App\Models\ChannelIntegration;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class StripeIntegrationService
{
    protected Client $httpClient;

    protected string $baseUrl = 'https://api.stripe.com/v1';

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Resolve the active Stripe integration and return the secret key.
     */
    protected function getSecretKey(int $workspaceId): ?string
    {
        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'stripe')
            ->where('status', 'active')
            ->first();

        if (! $integration) {
            return null;
        }

        return $integration->credentials['secret_key'] ?? null;
    }

    /**
     * Build authorization headers for Stripe API calls.
     */
    protected function headers(string $key): array
    {
        return [
            'Authorization' => "Bearer {$key}",
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }

    /**
     * Search Stripe for a customer by email address.
     *
     * @param int    $workspaceId The workspace ID.
     * @param string $email       The email to search for.
     * @return array|null Customer data or null if not found.
     */
    public function getCustomerByEmail(int $workspaceId, string $email): ?array
    {
        try {
            $key = $this->getSecretKey($workspaceId);
            if (! $key) {
                return null;
            }

            $response = $this->httpClient->get("{$this->baseUrl}/customers/search", [
                'headers' => $this->headers($key),
                'query' => [
                    'query' => "email:'{$email}'",
                    'limit' => 1,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $customers = $data['data'] ?? [];

            if (empty($customers)) {
                return null;
            }

            $customer = $customers[0];

            return [
                'id' => $customer['id'],
                'email' => $customer['email'] ?? $email,
                'name' => $customer['name'] ?? null,
                'phone' => $customer['phone'] ?? null,
                'currency' => $customer['currency'] ?? null,
                'balance' => $customer['balance'] ?? 0,
                'created' => $customer['created'] ?? null,
                'delinquent' => $customer['delinquent'] ?? false,
                'description' => $customer['description'] ?? null,
                'metadata' => $customer['metadata'] ?? [],
            ];
        } catch (GuzzleException $e) {
            Log::error('StripeIntegration: Failed to search customer by email', [
                'workspace_id' => $workspaceId,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Fetch recent charges/invoices for a Stripe customer.
     *
     * @param int    $workspaceId The workspace ID.
     * @param string $customerId  The Stripe customer ID (cus_...).
     * @param int    $limit       Max number of payments to return.
     * @return array Array of payment records.
     */
    public function getCustomerPayments(int $workspaceId, string $customerId, int $limit = 10): array
    {
        try {
            $key = $this->getSecretKey($workspaceId);
            if (! $key) {
                return [];
            }

            $response = $this->httpClient->get("{$this->baseUrl}/charges", [
                'headers' => $this->headers($key),
                'query' => [
                    'customer' => $customerId,
                    'limit' => min($limit, 100),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $charges = $data['data'] ?? [];

            return array_map(function (array $charge) {
                return [
                    'id' => $charge['id'],
                    'amount' => $charge['amount'] / 100, // cents to dollars
                    'currency' => strtoupper($charge['currency'] ?? 'USD'),
                    'status' => $charge['status'],
                    'paid' => $charge['paid'] ?? false,
                    'refunded' => $charge['refunded'] ?? false,
                    'description' => $charge['description'] ?? null,
                    'created' => $charge['created'],
                    'created_human' => \Carbon\Carbon::createFromTimestamp($charge['created'])->diffForHumans(),
                    'receipt_url' => $charge['receipt_url'] ?? null,
                    'invoice_id' => $charge['invoice'] ?? null,
                ];
            }, $charges);
        } catch (GuzzleException $e) {
            Log::error('StripeIntegration: Failed to fetch customer payments', [
                'workspace_id' => $workspaceId,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Fetch active subscriptions for a Stripe customer.
     *
     * @param int    $workspaceId The workspace ID.
     * @param string $customerId  The Stripe customer ID (cus_...).
     * @return array Array of subscription records.
     */
    public function getCustomerSubscriptions(int $workspaceId, string $customerId): array
    {
        try {
            $key = $this->getSecretKey($workspaceId);
            if (! $key) {
                return [];
            }

            $response = $this->httpClient->get("{$this->baseUrl}/subscriptions", [
                'headers' => $this->headers($key),
                'query' => [
                    'customer' => $customerId,
                    'limit' => 20,
                    'status' => 'all',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $subscriptions = $data['data'] ?? [];

            return array_map(function (array $sub) {
                $items = $sub['items']['data'] ?? [];
                $planNames = array_map(function ($item) {
                    return $item['price']['nickname']
                        ?? $item['plan']['nickname']
                        ?? $item['price']['id']
                        ?? 'Unknown plan';
                }, $items);

                return [
                    'id' => $sub['id'],
                    'status' => $sub['status'],
                    'plan_names' => $planNames,
                    'current_period_start' => $sub['current_period_start'],
                    'current_period_end' => $sub['current_period_end'],
                    'cancel_at_period_end' => $sub['cancel_at_period_end'] ?? false,
                    'canceled_at' => $sub['canceled_at'] ?? null,
                    'created' => $sub['created'],
                    'trial_end' => $sub['trial_end'] ?? null,
                    'amount_total' => collect($items)->sum(function ($item) {
                        return ($item['price']['unit_amount'] ?? 0) / 100;
                    }),
                    'currency' => strtoupper($sub['currency'] ?? 'USD'),
                    'interval' => $items[0]['price']['recurring']['interval'] ?? 'month',
                ];
            }, $subscriptions);
        } catch (GuzzleException $e) {
            Log::error('StripeIntegration: Failed to fetch customer subscriptions', [
                'workspace_id' => $workspaceId,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get a consolidated customer profile: basic info + payments + subscriptions.
     * Used by ConversationDetail sidebar.
     *
     * @param int    $workspaceId The workspace ID.
     * @param string $email       The contact email.
     * @return array|null Consolidated Stripe data, or null if not found.
     */
    public function getCustomerProfile(int $workspaceId, string $email): ?array
    {
        $customer = $this->getCustomerByEmail($workspaceId, $email);
        if (! $customer) {
            return null;
        }

        $payments = $this->getCustomerPayments($workspaceId, $customer['id'], 5);
        $subscriptions = $this->getCustomerSubscriptions($workspaceId, $customer['id']);

        $totalSpent = collect($payments)
            ->where('paid', true)
            ->where('refunded', false)
            ->sum('amount');

        return [
            'customer' => $customer,
            'payments' => $payments,
            'subscriptions' => $subscriptions,
            'total_spent' => $totalSpent,
            'has_active_subscription' => collect($subscriptions)->contains('status', 'active'),
        ];
    }

    /**
     * Check if Stripe integration is active for a workspace.
     */
    public function isActive(int $workspaceId): bool
    {
        return $this->getSecretKey($workspaceId) !== null;
    }
}

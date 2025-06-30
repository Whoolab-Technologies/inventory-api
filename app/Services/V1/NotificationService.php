<?php
namespace App\Services\V1;


use App\Enums\RequestType;
use App\Models\V1\StockTransfer;
use Google_Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\V1\Storekeeper;
use App\Models\V1\Engineer;
use App\Jobs\PushNotificationJob;
class NotificationService
{
    protected $projectId;
    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id');
    }


    protected function getAccessToken(): string
    {
        $serviceAccountPath = storage_path('firebase/inventory-manager-57a62-firebase-adminsdk-fbsvc-4aef60ed37.json');

        $client = new Google_Client();
        $client->setAuthConfig($serviceAccountPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->setAccessType('offline');

        $token = $client->fetchAccessTokenWithAssertion();

        if (isset($token['access_token'])) {
            return $token['access_token'];
        }

        throw new \Exception('Failed to obtain Firebase access token');
    }

    /**
     * Send notification to multiple device tokens.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): bool
    {
        \Log::error('Start sending..');
        $accessToken = $this->getAccessToken();
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        foreach ($tokens as $token) {
            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                ],
            ];
            $response = Http::withToken($accessToken)
                ->post($url, $message);

            if (!$response->successful()) {
                \Log::error('FCM v1 send error', [
                    'token' => $token,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return false;
            }
        }

        return true;
    }


    /**
     * Send to a single token.
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        return $this->sendToTokens([$token], $title, $body, $data);
    }

    public function sendNotificationOnMaterialRequestCreate($materialRequest)
    {
        $engineerName = $materialRequest->engineer->name;
        $storeName = $materialRequest->store->name;
        $siteTokens = $this->getSiteStorekeeperTokens($materialRequest->store_id);
        $siteTitle = "Material Request Created";
        $siteMessage = "{$engineerName} raised material request {$materialRequest->request_number} for your store.";
        $this->notifyUsers($siteTokens, $siteTitle, $siteMessage, [
            'material_request_id' => $materialRequest->id,
        ]);

        // Notification to central storekeeper
        $centralTokens = $this->getCentralStorekeeperTokens();
        $centralTitle = "New Material Request from {$storeName}";
        $centralMessage = "{$engineerName} from {$storeName} raised material request {$materialRequest->request_number}.";
        $this->notifyUsers($centralTokens, $centralTitle, $centralMessage, [
            'material_request_id' => $materialRequest->id,
        ]);

    }
    public function sendNotificationOnMaterialIssued($materialRequest)
    {
        $transaction = StockTransfer::
            where('request_id', $materialRequest->id)
            ->where('request_type', RequestType::MR)
            ->latest('id')->first();

        $engineer = $materialRequest->engineer;
        $transactionNumber = $transaction->transaction_number;
        $dnNumber = $transaction->dn_number;

        // ----------------- Engineer Notification -----------------
        $engineerToken = $this->getEngineerToken($engineer->id);
        \Log::info('Engineer token fetched', [
            'engineer_id' => $engineer->id,
            'has_token' => $engineerToken ? true : false,

        ]);
        $engineerTitle = "Material Issued from Central Store";
        $engineerMessage = "Materials for your request {$materialRequest->request_number}  have been issued. Transaction: {$transactionNumber}, DN: {$dnNumber}.";

        if ($engineerToken) {
            $this->notifyUsers([$engineerToken], $engineerTitle, $engineerMessage, [
                'material_request_id' => (string) $materialRequest->id,
                'transaction_id' => (string) $transaction->id,
            ]);
        } else {
            \Log::warning("No FCM token found for engineer ID {$engineer->id}");
        }

        // ----------------- Site Storekeeper Notification -----------------
        $siteTokens = $this->getSiteStorekeeperTokens($materialRequest->store_id);
        $siteTitle = "Material Issued from Central Store";
        $siteMessage = "Materials for request {$materialRequest->request_number} raised by {$engineer->name} have been issued. Transaction: {$transactionNumber}, DN: {$dnNumber}.";
        \Log::info('Site token fetched', [
            'siteTokens' => count($siteTokens)
        ]);
        $this->notifyUsers($siteTokens, $siteTitle, $siteMessage, [
            'material_request_id' => (string) $materialRequest->id,
            'transaction_id' => (string) $transaction->id,
        ]);
    }

    public function sendNotificationOnMaterialRequestUpdate($materialRequest)
    {
        $status = $materialRequest->status;
        $engineer = $materialRequest->engineer;

        // ----------------- Engineer Notification -----------------
        $engineerToken = $this->getEngineerToken($engineer->id);
        $engineerTitle = "Material Request Status Updated";
        $engineerMessage = "Your material request, {$materialRequest->request_number}, is now '{$status->name}'.";

        if ($engineerToken) {
            $this->notifyUsers([$engineerToken], $engineerTitle, $engineerMessage, [
                'material_request_id' => (string) $materialRequest->id,
            ]);
        } else {
            \Log::warning("No FCM token found for engineer ID {$engineer->id}");
        }

        // ----------------- Site Storekeeper Notification -----------------
        $siteTokens = $this->getSiteStorekeeperTokens($materialRequest->store_id);
        $siteTitle = "Material Request Status Updated";
        $siteMessage = "Material request {$materialRequest->request_number} raised by {$engineer->name} has been updated to '{$status->name}'.";

        $this->notifyUsers($siteTokens, $siteTitle, $siteMessage, [
            'material_request_id' => (string) $materialRequest->id,
        ]);

    }

    public function sendNotificationOnMaterialReceived($transaction)
    {
        $materialRequest = $transaction->materialRequest;
        $engineer = $materialRequest->engineer;
        $statusName = $materialRequest->status->name ?? 'updated';
        $transactionNumber = $transaction->transaction_number;
        $dnNumber = $transaction->dn_number;

        // ----------------- Engineer Notification -----------------
        $engineerToken = $this->getEngineerToken($engineer->id);
        \Log::info('Engineer token fetched', [
            'engineer_id' => $engineer->id,
            'has_token' => $engineerToken ? true : false,
        ]);
        $engineerTitle = "Material Request Status Updated";
        $engineerMessage = "Your material request {$materialRequest->request_number} has been marked as '{$statusName}'. Transaction: {$transactionNumber}, DN: {$dnNumber}.";

        if ($engineerToken) {
            $this->notifyUsers([$engineerToken], $engineerTitle, $engineerMessage, [
                'material_request_id' => (string) $materialRequest->id,
                'transaction_id' => (string) $transaction->id,
            ]);
        } else {
            \Log::warning("No FCM token found for engineer ID {$engineer->id}");
        }

        // ----------------- Central Storekeeper Notification -----------------
        $centralTokens = $this->getCentralStorekeeperTokens(); // Assuming you have this method
        $centralTitle = "Material Received at Site";
        $centralMessage = "Materials for request {$materialRequest->request_number} raised by {$engineer->name} have been received at site '{$materialRequest->store->name}'. Transaction: {$transactionNumber}, DN: {$dnNumber}. Status: '{$statusName}'.";

        \Log::info('Central storekeeper tokens fetched', [
            'count' => count($centralTokens),
        ]);

        if (!empty($centralTokens)) {
            $this->notifyUsers($centralTokens, $centralTitle, $centralMessage, [
                'material_request_id' => (string) $materialRequest->id,
                'transaction_id' => (string) $transaction->id,
            ]);
        } else {
            \Log::warning("No tokens found for central storekeepers");
        }
    }
    public function sendNotificationOnMaterialPickup($pickup)
    {

        $engineer = $pickup->engineer;
        $storeName = $pickup->store->name;
        $foremanName = $pickup->representative;

        $engineerToken = $this->getEngineerToken($engineer->id);
        \Log::info('Engineer token fetched', [
            'engineer_id' => $engineer->id,
            'has_token' => $engineerToken ? true : false,
        ]);

        $title = "Material Picked Up by Foreman";
        $message = "Materials for your request {$pickup->dispatch_number} at {$storeName} have been picked up by foreman {$foremanName}.";

        if ($engineerToken) {
            $this->notifyUsers([$engineerToken], $title, $message, [
                'dispatch_number' => (string) $pickup->dispatch_number,
                'pickup_id' => (string) $pickup->id,
            ]);
        } else {
            \Log::warning("No FCM token found for engineer ID {$engineer->id}");
        }
    }


    public function sendNotificationOnMaterialReturnFromEngineer($materialReturn, $engineerId)
    {

        $storeName = $materialReturn->toStore->name;
        $returnNumber = $materialReturn->return_number ?? 'N/A';

        $engineerToken = $this->getEngineerToken($engineerId);
        \Log::info('Engineer token fetched for material return', [
            'engineer_id' => $engineerId,
            'has_token' => $engineerToken ? true : false,
        ]);

        $title = "Material Returned to Site Store";
        $message = "You have successfully returned materials (Return #: {$returnNumber}) to the site store {$storeName}.";

        if ($engineerToken) {
            $this->notifyUsers([$engineerToken], $title, $message, [
                'material_return_id' => (string) $materialReturn->id,
            ]);
        } else {
            \Log::warning("No FCM token found for engineer ID {$engineerId}");
        }
    }

    public function sendNotificationOnMaterialReturnToCentralStore($materialReturn, $engineerId)
    {
        $fromStoreName = $materialReturn->fromStore->name ?? 'Site Store';
        $returnNumber = $materialReturn->return_number ?? 'N/A';

        // ----------------- Engineer Notification -----------------
        $engineerToken = $this->getEngineerToken($engineerId);
        \Log::info('Engineer token fetched for material return initiated', [
            'engineer_id' => $engineerId,
            'has_token' => $engineerToken ? true : false,
        ]);

        $titleEngineer = "Material Return Initiated by Site Storekeeper";
        $messageEngineer = "The site storekeeper has initiated a material return (Return #: {$returnNumber}) from {$fromStoreName} under your project.";

        if ($engineerToken) {
            $this->notifyUsers([$engineerToken], $titleEngineer, $messageEngineer, [
                'material_return_id' => (string) $materialReturn->id,
            ]);
        } else {
            \Log::warning("No FCM token found for engineer ID {$engineerId}");
        }

        // ----------------- Central Storekeeper Notification -----------------
        $centralStoreTokens = $this->getCentralStorekeeperTokens();
        $titleCentral = "Material Return Initiated from Site Store";
        $messageCentral = "A material return (Return #: {$returnNumber}) has been initiated from {$fromStoreName} to the central store.";

        $this->notifyUsers($centralStoreTokens, $titleCentral, $messageCentral, [
            'material_return_id' => (string) $materialReturn->id,
        ]);
    }


    protected function notifyUsers(array $tokens, string $title, string $message, array $data = []): void
    {
        if (!empty($tokens)) {
            $data = collect($data)->map(fn($value) => (string) $value)->toArray();
            PushNotificationJob::dispatch($tokens, $title, $message, $data)->afterCommit();

        } else {
            \Log::warning("No tokens found to notify");
        }
    }

    protected function getSiteStorekeeperTokens(int $storeId): array
    {
        return Storekeeper::with('token')
            ->where('store_id', $storeId)
            ->get()
            ->pluck('token.fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    protected function getCentralStorekeeperTokens(): array
    {
        return Storekeeper::with('token')
            ->whereHas('store', function ($q) {
                $q->where('type', 'central');
            })
            ->get()
            ->pluck('token.fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    protected function getEngineerToken(int $engineerId): ?string
    {
        return optional(
            Engineer::with('token')
                ->where('id', $engineerId)
                ->first()
                    ?->token
        )->fcm_token;
    }
}

<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\V1\NotificationService;
use Illuminate\Foundation\Bus\Dispatchable;
class PushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tokens;
    protected $title;
    protected $body;
    protected $data;

    /**
     * Create a new job instance.
     */
    public function __construct($tokens, $title, $body, $data = [])
    {
        $this->tokens = is_array($tokens) ? $tokens : [$tokens];
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */

    public function handle(NotificationService $notificationService)
    {
        $notificationService->sendToTokens($this->tokens, $this->title, $this->body, $this->data);
    }

}

<?php

namespace App\Notifications;

use App\Models\Job;
use Illuminate\Notifications\Notification;

class JobAssigned extends Notification
{
    public function __construct(public Job $job) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'job_assigned',
            'job_id' => $this->job->id,
            'number' => $this->job->number,
            'title' => $this->job->title,
            'message' => 'Job '.$this->job->number.' ('.$this->job->title.') was assigned to you.',
        ];
    }
}

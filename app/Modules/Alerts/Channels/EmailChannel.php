<?php

namespace App\Modules\Alerts\Channels;

use App\Modules\Alerts\Interfaces\ChannelInterface;
use App\Models\Alert;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Mail;

class EmailChannel implements ChannelInterface
{
    /**
     * @param Alert $alert
     * @return bool
     */
    public function send(Alert $alert): bool
    {
        try {
            $template = NotificationTemplate::where('key', $alert->type)
                ->where('channel_id', $alert->channel_id)
                ->first();

            if (!$template) {
                return false;
            }

            $data = $alert->data ?? [];
            $subject = $this->parseTemplate($template->subject, $data);
            $body = $this->parseTemplate($template->body, $data);

            Mail::raw($body, function ($message) use ($alert, $subject) {
                $message->to($alert->user->email)
                    ->subject($subject);
            });

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string $template
     * @param array $data
     * @return string
     */
    protected function parseTemplate(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }
}

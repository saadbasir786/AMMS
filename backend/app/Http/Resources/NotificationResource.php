<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = (array) ($this->data ?? []);
        $actionUrl = null;

        if (!empty($data['meeting_request_id'])) {
            $actionUrl = '/meeting-requests/' . $data['meeting_request_id'];
        } elseif (!empty($data['meeting_id'])) {
            $actionUrl = '/meetings/' . $data['meeting_id'];
        } elseif (!empty($data['action_item_id'])) {
            $actionUrl = '/action-items/' . $data['action_item_id'];
        } elseif (!empty($data['action_url'])) {
            $actionUrl = $data['action_url'];
        } elseif (!empty($data['link'])) {
            $actionUrl = $data['link'];
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'action_url' => $actionUrl,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
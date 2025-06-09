<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'model_name' => $this->model_name,
            'model_provider' => $this->model_provider,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'messages_count' => $this->whenCounted('messages'),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'latest_message' => new MessageResource($this->whenLoaded('latestMessage')),
        ];
    }
}

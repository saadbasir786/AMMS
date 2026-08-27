<?php

namespace App\Http\Controllers\Api\MeetingRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeetingRequests\StoreAttachmentRequest;
use App\Http\Resources\MeetingRequestAttachmentResource;
use App\Models\MeetingRequest;
use App\Models\MeetingRequestAttachment;
use Illuminate\Support\Facades\Storage;

class MeetingRequestAttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request, MeetingRequest $meetingRequest)
    {
        abort_unless($request->user()->isActive() && $request->user()->can('meeting-request.attach'), 403, 'FORBIDDEN');
        $meetingRequest->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY'
            ? $this->authorize('flow04RequesterEdit', $meetingRequest)
            : $this->authorize('update', $meetingRequest);
        $file = $request->file('attachment');
        $storedPath = $file->store('meeting-requests/'.$meetingRequest->id, 'local');
        $attachment = $meetingRequest->attachments()->create(['original_name'=>$file->getClientOriginalName(),'stored_path'=>$storedPath,'mime_type'=>$file->getMimeType(),'size'=>$file->getSize(),'uploaded_by'=>$request->user()->id]);
        return (new MeetingRequestAttachmentResource($attachment))->additional(['success'=>true,'message'=>'ضمیمه با موفقیت بارگذاری شد.'])->response()->setStatusCode(201);
    }

    public function download(MeetingRequestAttachment $attachment)
    {
        $this->authorize('view', $attachment);
        abort_unless(Storage::disk('local')->exists($attachment->stored_path), 404, 'ATTACHMENT_NOT_FOUND');
        return Storage::disk('local')->download($attachment->stored_path, $attachment->original_name, ['Content-Type'=>$attachment->mime_type]);
    }

    public function destroy(MeetingRequestAttachment $attachment)
    {
        $this->authorize('delete', $attachment);
        Storage::disk('local')->delete($attachment->stored_path);
        $attachment->delete();
        return response()->json(['success'=>true,'data'=>null,'message'=>'ضمیمه حذف شد.']);
    }
}

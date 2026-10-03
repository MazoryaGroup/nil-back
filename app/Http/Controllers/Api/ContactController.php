<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactReceivedMail;
use App\Models\Contact;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * ثبت پیام تماس - عمومی
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => [
                'required',
                'string',
                'max:255',
            ],

            'last_name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:15',
            ],

            'message' => [
                'required',
                'string',
                'max:5000',
            ],
        ]);

        try {
            /*
            |--------------------------------------------------------------------------
            | Clean Inputs
            |--------------------------------------------------------------------------
            */

            $validated['first_name'] = strip_tags(
                trim($validated['first_name'])
            );

            $validated['last_name'] = strip_tags(
                trim($validated['last_name'])
            );

            $validated['email'] = strtolower(
                trim($validated['email'])
            );

            if (!empty($validated['phone'])) {
                $validated['phone'] = strip_tags(
                    trim($validated['phone'])
                );
            } else {
                $validated['phone'] = null;
            }

            $validated['message'] = strip_tags(
                trim($validated['message'])
            );

            /*
            |--------------------------------------------------------------------------
            | Save Contact
            |--------------------------------------------------------------------------
            */

            $contact = Contact::create($validated);

            /*
            |--------------------------------------------------------------------------
            | Send Confirmation Email
            |--------------------------------------------------------------------------
            */

//            Mail::to($contact->email)->send(
//                new ContactReceivedMail($contact)
//            );

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'status' => true,
                'message' => 'Message received successfully.',
                'data' => [
                    'id' => $contact->id,
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                ],
            ], 201);

        } catch (Exception $e) {
            Log::error(
                'Contact Store Error: ' . $e->getMessage(),
                [
                    'exception' => $e,
                ]
            );

            return response()->json([
                'status' => false,
                'message' => 'Server Error.',
            ], 500);
        }
    }

    /**
     * لیست تماس‌ها - فقط ادمین
     */
    public function index()
    {
        $contacts = Contact::latest()->paginate(15);

        return response()->json([
            'status' => true,
            'data' => $contacts,
        ]);
    }

    /**
     * مشاهده یک تماس - فقط ادمین
     */
    public function show(int $id)
    {
        $contact = Contact::find($id);

        if (!$contact) {
            return response()->json([
                'status' => false,
                'message' => 'Contact not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $contact,
        ]);
    }

    /**
     * حذف تماس - فقط ادمین
     */
    public function destroy(int $id)
    {
        $contact = Contact::find($id);

        if (!$contact) {
            return response()->json([
                'status' => false,
                'message' => 'Contact not found.',
            ], 404);
        }

        $contact->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted successfully.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaitingList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\WaitingListMail;
use Illuminate\Support\Facades\Log;
use Exception;

class WaitingListController extends Controller
{
    /**
     * لیست تمام ایمیل‌ها برای پنل مدیریت (امنیت Sanctum)
     */
    public function index()
    {
        return response()->json(
            WaitingList::latest()->paginate(50)
        );
    }

    /**
     * ثبت‌نام در لیست انتظار + مدیریت خطای SMTP
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255|unique:waiting_lists,email',
        ]);

        try {
            $waiting = WaitingList::create([
                'email' => strtolower(trim($request->email)),

                'ip'    => $request->ip(),
            ]);

            try {
                Mail::to($waiting->email)->send(new WaitingListMail($waiting));
            } catch (Exception $mailError) {
                Log::warning("WaitingList Mail Error for {$waiting->email}: " . $mailError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => $request->lang === 'fa' ? 'با موفقیت ثبت شد.' : 'Registered successfully.',
                'data'    => [
                    'email' => $waiting->email,

                    'created_at' => $waiting->created_at->format('Y-m-d H:i')
                ]
            ], 201);

        } catch (Exception $e) {
            Log::error('WaitingList Critical Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $request->lang === 'fa' ? 'خطای سرور، دوباره تلاش کنید.' : 'Server error, please try again.',
            ], 500);
        }
    }
    /**
     * حذف ایمیل از لیست
     */
    public function destroy($id)
    {
        try {
            $waiting = WaitingList::findOrFail($id);
            $waiting->delete();

            return response()->json([
                'success' => true,
                'message' => 'Record deleted successfully.'
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }
    }
}

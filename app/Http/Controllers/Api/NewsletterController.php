<?php
// app/Http/Controllers/Api/NewsletterController.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Newsletter API
//
// subscribe()    — saves an email from the homepage "Stay Updated"
//                  form. firstOrCreate means a returning visitor
//                  is never duplicated; if they had unsubscribed
//                  earlier we simply flip is_subscribed back on.
//
// unsubscribe()  — called from the unsubscribe link in the email
//                  footer. We look the subscriber up by their
//                  random token (no login needed), then set
//                  is_subscribed = false. We keep the row so their
//                  token stays valid in case they re-subscribe.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\{JsonResponse, Request};

class NewsletterController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email      = strtolower($validated['email']);
        $subscriber = NewsletterSubscriber::firstOrCreate(
            ['email' => $email],
            ['token' => NewsletterSubscriber::generateToken()]
        );

        if ($subscriber->wasRecentlyCreated) {
            return response()->json([
                'success' => true,
                'message' => "You're subscribed! Welcome to KNL Atelier.",
                'data'    => ['email' => $subscriber->email],
            ], 201);
        }

        if (!$subscriber->is_subscribed) {
            $subscriber->update(['is_subscribed' => true]);

            return response()->json([
                'success' => true,
                'message' => "You're back! Subscription re-activated.",
                'data'    => ['email' => $subscriber->email],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "You're already subscribed.",
            'data'    => ['email' => $subscriber->email],
        ]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|max:255',
        ]);

        $subscriber = NewsletterSubscriber::where('token', $validated['token'])->first();

        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid unsubscribe link.',
            ], 404);
        }

        if ($subscriber->is_subscribed) {
            $subscriber->update(['is_subscribed' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => "You've been unsubscribed. No more newsletters from KNL.",
            'data'    => ['email' => $subscriber->email],
        ]);
    }
}

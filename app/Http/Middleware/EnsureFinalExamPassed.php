<?php

namespace App\Http\Middleware;

use App\Services\FinalExamService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinalExamPassed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! app(FinalExamService::class)->requiresExam($user) || $this->isAllowedRoute($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Please pass the Final Examination before accessing the prop firm section.',
            ], 423);
        }

        return redirect()
            ->route('final_exam.intro')
            ->with([
                'message' => 'Please pass the Final Examination before accessing the prop firm section.',
                'alert-type' => 'warning',
            ]);
    }

    private function isAllowedRoute(Request $request): bool
    {
        return $request->routeIs(
            'final_exam.*',
            'admin.final_exam.*',
            'logout',
            'admin.logout',
            'verification.*',
            'password.confirm',
            'password.update'
        );
    }
}

<x-app-layout>
    @php
        $levelProfile = data_get($traderLevelSnapshot ?? [], 'level', []);
        $summaryProfile = data_get($traderLevelSnapshot ?? [], 'summary', []);
        $behaviorProfile = data_get($traderLevelSnapshot ?? [], 'behavior', []);
        $toneClass = match (data_get($levelProfile, 'tone', 'secondary')) {
            'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
            'primary' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
            'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
            'danger' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        };
    @endphp

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-2xl">
                        <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Trader Level</div>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <div class="text-4xl font-black text-gray-900 dark:text-gray-100">L{{ data_get($levelProfile, 'level_number', 1) }}</div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold uppercase {{ $toneClass }}">
                                {{ data_get($levelProfile, 'sample_label', 'No Data') }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase text-slate-800 dark:bg-slate-700 dark:text-slate-100">
                                Grade {{ data_get($levelProfile, 'grade', 'G') }}
                            </span>
                        </div>
                        <h3 class="mt-2 text-xl font-bold text-gray-900 dark:text-gray-100">
                            {{ data_get($levelProfile, 'label', 'No Data') }}
                        </h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            {{ data_get($levelProfile, 'description', 'Add trading journal records to activate trader level evaluation.') }}
                        </p>
                        <button type="button" data-trader-level-modal-open="profile-trader-level-modal" class="mt-4 inline-flex items-center rounded-md bg-teal-700 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                            View Level Details
                        </button>

                        <dialog id="profile-trader-level-modal" data-trader-level-modal class="w-[min(92vw,760px)] rounded-xl border-0 bg-white p-0 text-left shadow-2xl backdrop:bg-black/50 dark:bg-gray-800">
                            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Trader Level Details</div>
                                        <h3 class="mt-1 text-lg font-black text-gray-900 dark:text-gray-100">
                                            L{{ data_get($levelProfile, 'level_number', 1) }} - {{ data_get($levelProfile, 'label', 'No Data') }}
                                        </h3>
                                    </div>
                                    <button type="button" data-trader-level-modal-close class="rounded-md border border-gray-300 px-3 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                        Close
                                    </button>
                                </div>
                            </div>
                            <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
                                <div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                                    <p><strong>Grade {{ data_get($levelProfile, 'grade', 'G') }}:</strong> {{ data_get($levelProfile, 'grade_description') }}</p>
                                    <p><strong>Level reason:</strong> {{ data_get($levelProfile, 'level_explanation') }}</p>
                                    @if(data_get($levelProfile, 'cap_active'))
                                        <p class="text-amber-700 dark:text-amber-300"><strong>Active cap:</strong> {{ data_get($levelProfile, 'cap_reason') }}</p>
                                        <div class="space-y-2">
                                            @foreach(collect(data_get($levelProfile, 'cap_details', [])) as $capDetail)
                                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-100">
                                                    <strong>L{{ data_get($capDetail, 'cap_level_number') }} {{ data_get($capDetail, 'label') }}</strong>
                                                    @if(data_get($capDetail, 'metric'))
                                                        <span> - {{ data_get($capDetail, 'metric') }}: {{ data_get($capDetail, 'value') }}</span>
                                                    @endif
                                                    <div class="mt-1">{{ data_get($capDetail, 'reason') }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if(data_get($levelProfile, 'next_level.requirement'))
                                        <p><strong>Next requirement:</strong> {{ data_get($levelProfile, 'next_level.requirement') }}</p>
                                    @endif
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach(collect(data_get($levelProfile, 'grade_scale', [])) as $gradeRow)
                                            <div class="rounded-md border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
                                                <strong>Grade {{ data_get($gradeRow, 'grade') }}</strong>
                                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ data_get($gradeRow, 'levels') }}</span>
                                                <span class="block">{{ data_get($gradeRow, 'description') }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </dialog>
                    </div>

                    <div class="grid w-full gap-3 sm:grid-cols-2 lg:w-[420px]">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <div class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Score</div>
                            <div class="mt-1 text-2xl font-black text-gray-900 dark:text-gray-100">{{ number_format((float) data_get($levelProfile, 'score', 0), 2) }}/100</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <div class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Trades</div>
                            <div class="mt-1 text-2xl font-black text-gray-900 dark:text-gray-100">{{ number_format((int) data_get($summaryProfile, 'total_trades', 0)) }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <div class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Net P/L</div>
                            <div class="mt-1 text-2xl font-black text-gray-900 dark:text-gray-100">{{ number_format((float) data_get($summaryProfile, 'net_profit_loss', 0), 2) }}u</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <div class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Behavior Risk</div>
                            <div class="mt-1 text-2xl font-black text-gray-900 dark:text-gray-100">{{ number_format((float) data_get($behaviorProfile, 'final_score', data_get($behaviorProfile, 'risk_score', 0)), 2) }}/100</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-trader-level-modal-open]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const modal = document.getElementById(button.getAttribute('data-trader-level-modal-open'));

                    if (modal && typeof modal.showModal === 'function') {
                        modal.showModal();
                    } else if (modal) {
                        modal.setAttribute('open', 'open');
                    }
                });
            });

            document.querySelectorAll('[data-trader-level-modal-close]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const modal = button.closest('dialog');

                    if (modal && typeof modal.close === 'function') {
                        modal.close();
                    } else if (modal) {
                        modal.removeAttribute('open');
                    }
                });
            });

            document.querySelectorAll('dialog[data-trader-level-modal]').forEach(function (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal && typeof modal.close === 'function') {
                        modal.close();
                    }
                });
            });
        });
    </script>
</x-app-layout>

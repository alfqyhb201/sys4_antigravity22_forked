<x-filament-panels::page>
   <h2 class="text-xl font-bold mb-6">توزيع العملاء حسب المصممين</h2>

    <div class="space-y-4">
        @foreach($this->getDesignersWithClients() as $index => $designer)
            <div x-data="{ open: false }" class="border rounded-xl shadow">
                <!-- رأس المصمم -->
                <button
                    @click="open = !open"
                    class="w-full flex justify-between items-center px-4 py-3 text-right bg-gray-100 hover:bg-gray-200 rounded-t-xl">
                    <div>
                        <span class="font-semibold text-lg text-gray-800">
                            المصمم: {{ $designer['name'] }}
                        </span>
                        <span class="text-sm text-gray-600 ml-2">
                            ({{ $designer['clients']->count() }} عميل - {{ $designer['total_designs'] }} من {{ $designer['max_capacity'] }} تصميم)
                        </span>
                    </div>
                    <svg x-show="!open" class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <svg x-show="open" class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                    </svg>
                </button>

                <!-- جدول العملاء -->
                <div x-show="open" x-transition class="overflow-x-auto">
                    <table class="min-w-full text-sm text-right bg-white">
                        <thead class="bg-gray-50 border-b text-black font-semibold">
                            <tr>
                                <th class="px-4 py-2 text-black">العميل</th>
                                <th class="px-4 py-2">عدد التصاميم المطلوبة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($designer['clients'] as $client)
                                <tr class="border-t ">
                                    <td class="px-4 py-2">{{ $client['company'] }}</td>
                                    <td class="px-4 py-2">{{ $client['design_limit'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-gray-500 py-4">لا يوجد عملاء</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
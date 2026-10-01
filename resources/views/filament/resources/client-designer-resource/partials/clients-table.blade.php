<table class="w-full text-sm text-right">
    <thead class="bg-gray-100 text-gray-700 font-semibold">
        <tr>
            <th class="px-3 py-2">العميل</th>
            <th class="px-3 py-2">عدد التصاميم المطلوبة</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($clients as $client)
            <tr class="border-b">
                <td class="px-3 py-2 text-black">{{ $client['company'] }}</td>
                <td class="px-3 py-2">{{ $client['design_limit'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" class="text-center text-gray-400 py-4">لا يوجد عملاء</td>
            </tr>
        @endforelse
    </tbody>
</table>

<x-filament::page>
    <div class="space-y-12">
        @foreach($tags as $tag)
        <div class="border-b border-gray-200 pb-8">
            <h2 class="text-3xl font-bold tracking-tight text-gray-900 mb-6">{{ $tag->name }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($tag->ideas as $idea)
                {{-- Start of the card design based on the image --}}
                <div class="p-6 bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300 border border-gray-100">
                    <div class="mb-4">
                        {{-- The 'COMPLETE' badge can be a Filament badge or a simple span with styling --}}
                        <x-filament::badge color="success" class="bg-green-100 text-green-800">
                            COMPLETE
                        </x-filament::badge>
                    </div>

                    <h3 class="text-2xl font-bold text-gray-900 mb-1">{{ $idea->name }}</h3>
                    <p class="text-sm text-gray-500 mb-4">{{ $idea->scheduled_at ? \Carbon\Carbon::parse($idea->scheduled_at)->format('Y-m-d H:i') : 'غير محدد' }}</p>

                    <p class="text-base text-gray-700 mb-6 whitespace-pre-line">{{ $idea->content }}</p>

                    {{-- LIST/Product Growth section --}}
                    <div class="mb-6">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">LIST</span>
                        <p class="text-base font-semibold text-gray-800">{{ $idea->description }}</p>
                    </div>

                    {{-- KEYWORDS/Tags section --}}
                    <div>
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">KEYWORDS</span>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach($idea->tags as $tag)
                            <x-filament::badge color="info" class="bg-blue-100 text-blue-800 rounded-full py-1 px-3">
                                {{ $tag->name }}
                            </x-filament::badge>
                            @endforeach
                        </div>
                    </div>
                </div>
                {{-- End of the card design --}}
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
</x-filament::page>
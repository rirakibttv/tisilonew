<section class="storefront-shell pt-2" aria-label="{{ $sliderGroup->name }}">
    <div
        class="group/managed-slider relative overflow-hidden rounded-2xl bg-white shadow-sm"
        data-managed-slider
        data-slider-name="{{ $sliderGroup->slug }}"
    >
        @foreach($sliderGroup->slides as $slide)
            @if($slide->destination_href)
                <a
                    href="{{ $slide->destination_href }}"
                    class="managed-slider-slide {{ $loop->first ? 'block' : 'hidden' }}"
                    data-managed-slider-slide
                    aria-label="{{ $slide->name }}"
                >
                    <img
                        src="{{ $slide->image_url }}"
                        alt="{{ $slide->name }}"
                        class="block h-auto w-full"
                        loading="lazy"
                    >
                </a>
            @else
                <div
                    class="managed-slider-slide {{ $loop->first ? 'block' : 'hidden' }}"
                    data-managed-slider-slide
                >
                    <img
                        src="{{ $slide->image_url }}"
                        alt="{{ $slide->name }}"
                        class="block h-auto w-full"
                        loading="lazy"
                    >
                </div>
            @endif
        @endforeach

        @if($sliderGroup->slides->count() > 1)
            <button
                type="button"
                class="absolute left-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-purple-950/65 text-white transition hover:bg-purple-950"
                data-managed-slider-prev
                aria-label="Previous slide"
            >
                @svg('heroicon-o-chevron-left', 'size-5')
            </button>
            <button
                type="button"
                class="absolute right-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-purple-950/65 text-white transition hover:bg-purple-950"
                data-managed-slider-next
                aria-label="Next slide"
            >
                @svg('heroicon-o-chevron-right', 'size-5')
            </button>

            <div class="absolute bottom-3 left-1/2 z-20 flex -translate-x-1/2 items-center gap-2" data-managed-slider-indicators>
                @foreach($sliderGroup->slides as $indicator)
                    <button
                        type="button"
                        class="size-2.5 rounded-full transition-all {{ $loop->first ? 'w-6 bg-white' : 'bg-white/50' }}"
                        data-managed-slider-indicator="{{ $loop->index }}"
                        aria-label="Slide {{ $loop->iteration }}"
                    ></button>
                @endforeach
            </div>
        @endif
    </div>
</section>

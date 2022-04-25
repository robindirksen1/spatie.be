<section id="breadcrumb" class="hidden md:block py-4 md:py-6 lg:py-8">
    <div class="wrap">
        <p class="mt-4 links-underline links-blue">
            <a href="{{ route('products.index')}}">Products</a>
            <span class="icon mx-2 opacity-50 fill-current text-blue">{{ svg('icons/far-angle-right') }}</span>
            <a href="{{ route('products.show', $product)}}">{{ $product->title }}</a>
            <span class="icon mx-2 opacity-50 fill-current text-blue">{{ svg('icons/far-angle-right') }}</span>
            <span>Buy</span>
        </p>
    </div>
</section>

<section id="banner" class="md:pt-0 banner" role="banner">
    <div class="wrap">
        <h1 class="banner-slogan">
            {{ $product->title }}
        </h1>
        <div class="banner-intro">
            {{ $product->formattedDescription }}
        </div>
    </div>
</section>

<div class="section pt-0 wrap grid sm:gap-6 md:gap-8 grid-cols-9 sm:grid-flow-col-dense">
    <div class="col-span-9 sm:col-start-5 sm:col-span-5">
        <section class="mb-16 pt-0">
            <div class="md:-mx-2 md:grid md:grid-flow-col items-stretch justify-start">
                @include('front.pages.products.partials.priceCard', [
                    'payLink' => $payLink,
                ])
            </div>
        </section>

        <div class="section md:-mt-12 pt-0 pb-16">
            <div class="flex-0 text-xs text-gray md:mt-6">
                Includes a 10% coupon for a follow-up purchase within the next 24 hours.
                <br/>
                VAT will be calculated during checkout by
                <a class="underline" target="_blank" href="https://www.paddle.com/help/sell/tax/how-paddle-handles-vat-on-your-behalf">Paddle</a>.
            </div>
        </div>
    </div>
    <div class="col-span-9 sm:col-start-1 sm:col-span-4" style="bottom: -1rem">
        <div class="illustration is-left mb-12" title="Project">
            {{ $product->getFirstMedia('product-image') }}
        </div>

        <div class="markup markup-titles markup-lists links-blue links-underline | sm:grid-text-right">
            {{ $product->formattedLongDescription }}

            <p class="mt-4 flex items-center space-x-4">
                @if($product->action_url)
                    <a target="_blank" class="no-underline" rel="nofollow noreferrer noopener"
                       href="{{ $product->action_url }}">
                        <x-button>{{ $product->action_label }}</x-button>
                    </a>
                @elseif ($product->url)
                    <a target="_blank" rel="nofollow noreferrer noopener" href="{{ $product->url }}">
                        <span class="icon fill-current text-pink-dark">
                            {{ svg('icons/far-angle-right') }}
                        </span>
                        {{ Str::after($product->url, 'https://') }}
                    </a>
                @endif
            </p>
        </div>
    </div>
</div>


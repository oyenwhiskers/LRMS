@php
    $authMode = $authMode ?? 'login';
@endphp

<section class="auth-brand relative isolate flex h-full min-h-[22rem] flex-col overflow-hidden text-stone-100 lg:min-h-screen">
    <div
        class="absolute inset-0 bg-cover bg-center"
        style="background-image: url('{{ asset('images/login_background.png') }}')"
        aria-hidden="true"
    ></div>
    <div class="absolute inset-0 bg-gradient-to-br from-black/68 via-black/58 to-black/70" aria-hidden="true"></div>

    <div class="relative z-10 flex flex-1 flex-col justify-between gap-12 px-8 py-10 sm:px-12 sm:py-12 lg:px-14 lg:py-14 xl:px-16">
        <div>
            <img
                src="{{ asset('images/tsangco-banner.png') }}"
                alt="Tsang & Co. Advocates & Solicitors"
                class="h-16 w-auto max-w-full object-contain object-left sm:h-[4.5rem]"
            >
        </div>

        <div class="auth-brand-copy-stack max-w-lg">
            <div
                class="auth-brand-copy {{ $authMode === 'login' ? 'is-active' : '' }}"
                data-auth-brand="login"
                @if($authMode !== 'login') hidden @endif
            >
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-400">Secure access</p>
                <h1 class="mt-5 font-display text-3xl font-semibold leading-tight text-white sm:text-4xl lg:text-[2.65rem]">Legal work, managed with clarity.</h1>
                <p class="mt-5 max-w-md text-sm leading-7 text-stone-100/88">Sign in to access approved LRMS resources, workflows, and administrative controls.</p>
            </div>

            <div
                class="auth-brand-copy {{ $authMode === 'register' ? 'is-active' : '' }}"
                data-auth-brand="register"
                @if($authMode !== 'register') hidden @endif
            >
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-400">Staff registration</p>
                <h1 class="mt-5 font-display text-3xl font-semibold leading-tight text-white sm:text-4xl lg:text-[2.65rem]">Request access with clarity.</h1>
                <p class="mt-5 max-w-md text-sm leading-7 text-stone-100/88">Use your assigned staff number and select the position you are requesting. Your submission will be reviewed before access is granted.</p>
            </div>
        </div>

        <div class="flex max-w-md items-center gap-3 border-t border-white/15 pt-6">
            <span class="grid size-8 shrink-0 place-items-center border border-amber-400/40 bg-black/25 text-amber-300" aria-hidden="true">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V7.5a4.5 4.5 0 10-9 0v3m-.75 0h10.5A1.75 1.75 0 0118.5 12.25v6A1.75 1.75 0 0116.75 20H7.25A1.75 1.75 0 015.5 18.25v-6a1.75 1.75 0 011.75-1.75z"/>
                </svg>
            </span>
            <p class="text-xs leading-5 text-stone-200/95">Access is restricted to approved personnel. Activity may be audited.</p>
        </div>
    </div>
</section>

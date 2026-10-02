@props([
    'hasMore' => false,
])

@once
    <script>
        document.addEventListener('alpine:init', () => {
            if (window.__deskListScrollRegistered) {
                return;
            }
            window.__deskListScrollRegistered = true;

            Alpine.data('deskListScroll', () => ({
                loading: false,
                io: null,
                onScroll: null,
                scrollRoot: null,

                boot(el) {
                    const self = this;

                    const canLoad = () => el.getAttribute('data-has-more') === '1' && ! self.loading;

                    const load = async () => {
                        if (! canLoad()) {
                            return;
                        }
                        self.loading = true;
                        try {
                            await self.$wire.loadMoreList();
                        } catch (e) {
                            // ignore — button fallback still works
                        } finally {
                            self.loading = false;
                            queueMicrotask(() => self.fillIfNeeded(el));
                        }
                    };

                    self.fillIfNeeded = (root) => {
                        if (! canLoad()) {
                            return;
                        }
                        // Rows don't fill the pane yet — keep fetching until scrollable or done.
                        if (root.scrollHeight <= root.clientHeight + 8) {
                            load();
                        }
                    };

                    const nearBottom = (root) =>
                        root.scrollTop + root.clientHeight >= root.scrollHeight - 160;

                    self.onScroll = () => {
                        if (! canLoad()) {
                            return;
                        }
                        const root = self.scrollRoot && self.scrollRoot !== el ? self.scrollRoot : el;
                        if (nearBottom(root) || nearBottom(el)) {
                            load();
                        }
                    };

                    el.addEventListener('scroll', self.onScroll, { passive: true });

                    let p = el.parentElement;
                    while (p && p !== document.body) {
                        const style = window.getComputedStyle(p);
                        const oy = style.overflowY;
                        if ((oy === 'auto' || oy === 'scroll') && p.scrollHeight > p.clientHeight + 1) {
                            self.scrollRoot = p;
                            break;
                        }
                        p = p.parentElement;
                    }
                    if (! self.scrollRoot) {
                        self.scrollRoot = el;
                    }
                    if (self.scrollRoot !== el) {
                        self.scrollRoot.addEventListener('scroll', self.onScroll, { passive: true });
                    }

                    if (self.$refs.sentinel && 'IntersectionObserver' in window) {
                        self.io = new IntersectionObserver(
                            (entries) => {
                                if (entries.some((entry) => entry.isIntersecting)) {
                                    load();
                                }
                            },
                            {
                                root: self.scrollRoot === document.documentElement ? null : self.scrollRoot,
                                rootMargin: '180px 0px',
                                threshold: 0,
                            }
                        );
                        self.io.observe(self.$refs.sentinel);
                    }

                    queueMicrotask(() => self.fillIfNeeded(el));
                },

                destroy() {
                    if (this.onScroll) {
                        this.$el?.removeEventListener('scroll', this.onScroll);
                        if (this.scrollRoot && this.scrollRoot !== this.$el) {
                            this.scrollRoot.removeEventListener('scroll', this.onScroll);
                        }
                    }
                    this.io?.disconnect();
                },
            }));
        });
    </script>
@endonce

<div
    {{ $attributes->merge(['class' => 'desk-grid']) }}
    data-has-more="{{ $hasMore ? '1' : '0' }}"
    x-data="deskListScroll"
    x-init="boot($el)"
>
    {{ $slot }}

    <div
        x-ref="sentinel"
        class="desk-scroll-sentinel"
        aria-hidden="true"
    ></div>
</div>

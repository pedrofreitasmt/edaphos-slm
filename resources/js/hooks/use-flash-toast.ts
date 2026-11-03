import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

interface SharedFlash {
    toast?: FlashToast;
    success?: string | null;
    error?: string | null;
    warning?: string | null;
    info?: string | null;
}

interface PagePropsWithFlash {
    [key: string]: unknown;
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
    flash?: SharedFlash;
}

export function useFlashToast(): void {
    const lastMessageRef = useRef<string | null>(null);

    useEffect(() => {
        const showToast = (
            type: 'success' | 'error' | 'warning' | 'info',
            message?: string | null,
        ) => {
            if (!message) {
                return;
            }

            const key = `${type}:${message}`;
            if (lastMessageRef.current === key) {
                return;
            }

            lastMessageRef.current = key;
            toast[type](message);
        };

        const processProps = (props?: PagePropsWithFlash) => {
            if (!props) return;

            // Direct props from ->with('success', '...')
            if (props.success) showToast('success', props.success);
            if (props.error) showToast('error', props.error);
            if (props.warning) showToast('warning', props.warning);
            if (props.info) showToast('info', props.info);

            // Flash object from session or Inertia::flash
            if (props.flash?.toast) {
                showToast(
                    props.flash.toast.type,
                    props.flash.toast.message,
                );
            }
            if (props.flash?.success) showToast('success', props.flash.success);
            if (props.flash?.error) showToast('error', props.flash.error);
            if (props.flash?.warning) showToast('warning', props.flash.warning);
            if (props.flash?.info) showToast('info', props.flash.info);
        };

        // 1. Process initial page props from the DOM dataset on mount
        try {
            const appEl =
                typeof document !== 'undefined'
                    ? document.getElementById('app')
                    : null;
            if (appEl?.dataset?.page) {
                const initialPage = JSON.parse(appEl.dataset.page);
                processProps(initialPage?.props as PagePropsWithFlash);
            }
        } catch {
            // dataset may be missing or already consumed
        }

        // 2. Listen to Inertia router events on client-side visits
        const unregisterFlash = router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail
                ?.flash as SharedFlash | undefined;
            if (!flash) return;

            if (flash.toast) {
                showToast(flash.toast.type, flash.toast.message);
            }
            if (flash.success) showToast('success', flash.success);
            if (flash.error) showToast('error', flash.error);
            if (flash.warning) showToast('warning', flash.warning);
            if (flash.info) showToast('info', flash.info);
        });

        const unregisterSuccess = router.on('success', (event) => {
            const pageProps = event.detail?.page?.props as PagePropsWithFlash | undefined;
            processProps(pageProps);
        });

        const unregisterNavigate = router.on('navigate', (event) => {
            const pageProps = event.detail?.page?.props as PagePropsWithFlash | undefined;
            processProps(pageProps);
        });

        return () => {
            unregisterFlash();
            unregisterSuccess();
            unregisterNavigate();
        };
    }, []);
}

import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

const warning = 'Hay cambios sin guardar. ¿Querés salir igualmente?';

export function useUnsavedChangesWarning(active: boolean) {
    const allowNextVisit = useRef(false);

    useEffect(() => {
        const stopListening = router.on('before', () => {
            if (allowNextVisit.current) {
                allowNextVisit.current = false;

                return;
            }

            if (active) {
                return window.confirm(warning);
            }
        });
        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!active) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            stopListening();
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, [active]);

    return () => {
        allowNextVisit.current = true;
    };
}

/**
 * Prints one HTML document through a hidden iframe. Started with --kiosk-printing, Chrome and
 * Edge send it to the default printer without a dialog; otherwise the print dialog opens and
 * print() returns once it closes.
 */
export function printHtml(html: string): Promise<void> {
    return new Promise((resolve, reject) => {
        const frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.tabIndex = -1;
        frame.style.cssText =
            'position:fixed;right:0;bottom:0;width:80mm;height:0;border:0;visibility:hidden';

        const cleanup = () => window.setTimeout(() => frame.remove(), 1000);

        frame.onload = () => {
            try {
                const view = frame.contentWindow;

                if (!view) {
                    throw new Error('El documento de impresión no cargó.');
                }

                view.focus();
                view.print();
                resolve();
            } catch (error) {
                reject(error);
            } finally {
                cleanup();
            }
        };
        frame.srcdoc = html;
        document.body.appendChild(frame);
    });
}

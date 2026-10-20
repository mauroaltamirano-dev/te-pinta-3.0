import { formatMoney } from '@/lib/format';
import type { BillPayload, KitchenTicketPayload, PrintJob } from './types';

function escape(value: string | number | null | undefined): string {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function shortDate(date: string): string {
    const [, month, day] = date.split('-');

    return `${day}/${month}`;
}

/* 80 mm paper, about 72 mm printable. Black only, no images: thermal printers print text best. */
const styles = `
@page { size: 80mm auto; margin: 0; }
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #fff; color: #000; }
body { width: 72mm; padding: 2mm 1mm 6mm; font-family: Arial, Helvetica, sans-serif; font-size: 12pt; line-height: 1.25; }
h1 { margin: 0 0 1mm; font-size: 22pt; text-align: center; text-transform: uppercase; }
.meta { text-align: center; font-size: 11pt; }
.banner { margin: 2mm 0; padding: 1mm; border: 2px solid #000; text-align: center; font-size: 15pt; font-weight: 700; }
.rule { border-top: 2px dashed #000; margin: 2mm 0; }
table { width: 100%; border-collapse: collapse; }
td { vertical-align: top; padding: 1mm 0; }
.qty { width: 14mm; font-size: 20pt; font-weight: 700; }
.item { font-size: 16pt; font-weight: 700; }
.note { font-size: 13pt; font-weight: 700; }
.bill td { font-size: 11pt; }
.price { font-size: 10pt; }
.right { text-align: right; white-space: nowrap; }
.total td { font-size: 16pt; font-weight: 700; }
.legend { margin-top: 3mm; text-align: center; font-size: 10pt; }
`;

function document(title: string, body: string): string {
    return `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>${escape(title)}</title><style>${styles}</style></head><body>${body}</body></html>`;
}

export function kitchenTicketHtml(
    ticket: KitchenTicketPayload,
    reprint: boolean,
): string {
    const meta = [
        `${shortDate(ticket.date)} ${ticket.time}`,
        ticket.waiter ? `Mozo: ${ticket.waiter}` : null,
    ].filter(Boolean);
    const details = [
        ticket.customer ? `Cliente: ${ticket.customer}` : null,
        ticket.schedule
            ? `${ticket.delivery_date ? `${shortDate(ticket.delivery_date)} ` : ''}${ticket.schedule}`
            : null,
    ].filter(Boolean);

    return document(
        `${ticket.title} ${ticket.order}`,
        `<h1>${escape(ticket.title)}</h1>
<div class="meta">${escape(ticket.order)}${ticket.round ? ` · Ronda ${escape(ticket.round)}` : ''}</div>
<div class="meta">${meta.map(escape).join(' · ')}</div>
${ticket.added_by ? `<div class="meta">Agregado por ${escape(ticket.added_by)}</div>` : ''}
${details.length ? `<div class="meta">${details.map(escape).join(' · ')}</div>` : ''}
${ticket.void ? '<div class="banner">ANULACIÓN — NO PREPARAR</div>' : ''}
${ticket.condition ? `<div class="banner">${escape(ticket.condition)}</div>` : ''}
${reprint ? '<div class="banner">REIMPRESIÓN</div>' : ''}
<div class="rule"></div>
<table>${ticket.lines
            .map(
                (line) =>
                    `<tr><td class="qty">${ticket.void ? '−' : ''}${escape(line.quantity)}</td><td class="item">${escape(line.name)}</td></tr>`,
            )
            .join('')}</table>
<div class="rule"></div>
${ticket.note ? `<div class="note">Nota: ${escape(ticket.note)}</div>` : ''}`,
    );
}

export function billHtml(
    bill: BillPayload,
    reprint: boolean,
    restaurant: string,
): string {
    const rows = bill.lines
        .map(
            (line) =>
                `<tr><td>${escape(line.quantity)} × ${escape(line.name)}<br><span class="price">${escape(line.detail ? `${line.detail} × ${formatMoney(line.unit_price)}` : `${formatMoney(line.unit_price)} c/u`)}</span></td><td class="right">${escape(formatMoney(line.total))}</td></tr>`,
        )
        .join('');
    const extra = [
        bill.discount > 0 ? ['Descuento', -bill.discount] : null,
        bill.rounding !== 0 ? ['Redondeo', bill.rounding] : null,
    ].filter((row): row is [string, number] => row !== null);

    return document(
        `Cuenta ${bill.title}`,
        `<h1>${escape(restaurant)}</h1>
<div class="meta">${escape(bill.title)} · ${escape(bill.order)}</div>
<div class="meta">${escape(`${shortDate(bill.date)} ${bill.time}`)} · Mozo: ${escape(bill.waiter)}</div>
${reprint ? '<div class="banner">REIMPRESIÓN</div>' : ''}
<div class="rule"></div>
<table class="bill">${rows}</table>
<div class="rule"></div>
<table class="bill">
<tr><td>Subtotal</td><td class="right">${escape(formatMoney(bill.subtotal))}</td></tr>
${extra.map(([label, value]) => `<tr><td>${escape(label)}</td><td class="right">${escape(formatMoney(value))}</td></tr>`).join('')}
</table>
<table class="total"><tr><td>TOTAL</td><td class="right">${escape(formatMoney(bill.total))}</td></tr></table>
<div class="legend">${escape(bill.legend)}</div>`,
    );
}

export function jobHtml(job: PrintJob, restaurant: string): string {
    return job.kind === 'bill'
        ? billHtml(job.payload, job.reprint, restaurant)
        : kitchenTicketHtml(job.payload, job.reprint);
}

export function testTicketHtml(now: Date): string {
    return document(
        'Prueba',
        `<h1>Prueba</h1><div class="meta">${escape(now.toLocaleString('es-AR'))}</div><div class="rule"></div>
<table><tr><td class="qty">12</td><td class="item">Empanadas de prueba</td></tr></table><div class="rule"></div>
<div class="note">Si ves este texto completo y el papel se cortó, la impresora está lista.</div>`,
    );
}

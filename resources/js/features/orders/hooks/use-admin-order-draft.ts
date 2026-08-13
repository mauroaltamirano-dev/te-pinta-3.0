import { useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type {
    AdminCreatedOrder,
    AdminCustomerOption,
    AdminOrderForm,
} from '../admin-order-types';
import { postJson } from '../http';
import type { PriceQuote } from '../types';

const createInitialDraft = (today: string): AdminOrderForm => ({
    items: [],
    addons: [],
    condition: 'raw',
    customer_id: null,
    customer: { name: '', phone: '', address: '' },
    delivery_type: 'pickup',
    delivery_date: today,
    delivery_period: 'night',
    delivery_time: '',
    delivery_address: '',
    notes: '',
    payment_status: 'unpaid',
});

export function useAdminOrderDraft(
    customers: AdminCustomerOption[],
    today: string,
) {
    const form = useForm<AdminOrderForm>(createInitialDraft(today));
    const [quote, setQuote] = useState<PriceQuote | null>(null);
    const [quotedPreviewKey, setQuotedPreviewKey] = useState('');
    const [quoteLoading, setQuoteLoading] = useState(false);
    const [quoteError, setQuoteError] = useState('');
    const [submittingQuickOrder, setSubmittingQuickOrder] = useState(false);
    const [createdOrder, setCreatedOrder] = useState<AdminCreatedOrder | null>(
        null,
    );
    const previewSequence = useRef(0);
    const quickSubmitLocked = useRef(false);
    const preview = useMemo(
        () => ({
            items: form.data.items,
            addons: form.data.addons,
            condition: form.data.condition,
            delivery_type: form.data.delivery_type,
        }),
        [
            form.data.items,
            form.data.addons,
            form.data.condition,
            form.data.delivery_type,
        ],
    );
    const previewKey = JSON.stringify(preview);
    const currentQuote = quotedPreviewKey === previewKey ? quote : null;
    const currentQuoteLoading =
        preview.items.length > 0 &&
        (quoteLoading || quotedPreviewKey !== previewKey);

    useEffect(() => {
        const sequence = ++previewSequence.current;

        if (!preview.items.length) {
            return;
        }

        const timer = window.setTimeout(() => {
            setQuoteLoading(true);
            postJson<{ data: PriceQuote }>(
                '/admin/orders/price-preview',
                preview,
            )
                .then(({ data }) => {
                    if (sequence !== previewSequence.current) {
                        return;
                    }

                    setQuote(data);
                    setQuotedPreviewKey(previewKey);
                    setQuoteError('');
                })
                .catch((error: Error) => {
                    if (sequence !== previewSequence.current) {
                        return;
                    }

                    setQuote(null);
                    setQuoteError(error.message);
                })
                .finally(() => {
                    if (sequence === previewSequence.current) {
                        setQuoteLoading(false);
                    }
                });
        }, 250);

        return () => window.clearTimeout(timer);
    }, [preview, previewKey]);

    const changeProduct = (id: number, increment: number) => {
        const found = form.data.items.find((item) => item.product_id === id);
        const quantity = Math.min(120, (found?.quantity ?? 0) + increment);
        const items =
            quantity <= 0
                ? form.data.items.filter((item) => item.product_id !== id)
                : found
                  ? form.data.items.map((item) =>
                        item.product_id === id ? { ...item, quantity } : item,
                    )
                  : [...form.data.items, { product_id: id, quantity }];

        form.setData('items', items);

        if (items.length === 0) {
            previewSequence.current += 1;
            setQuote(null);
            setQuotedPreviewKey('');
            setQuoteError('');
            setQuoteLoading(false);
        }
    };

    const removeProduct = (id: number) => {
        const items = form.data.items.filter((item) => item.product_id !== id);

        form.setData('items', items);

        if (items.length === 0) {
            previewSequence.current += 1;
            setQuote(null);
            setQuotedPreviewKey('');
            setQuoteError('');
            setQuoteLoading(false);
        }
    };

    const changeAddon = (id: number, increment: number) => {
        const found = form.data.addons.find((item) => item.addon_id === id);
        const quantity = Math.min(100, (found?.quantity ?? 0) + increment);
        form.setData(
            'addons',
            quantity <= 0
                ? form.data.addons.filter((item) => item.addon_id !== id)
                : found
                  ? form.data.addons.map((item) =>
                        item.addon_id === id ? { ...item, quantity } : item,
                    )
                  : [...form.data.addons, { addon_id: id, quantity }],
        );
    };

    const selectCustomer = (value: string) => {
        if (value === 'new') {
            form.setData((data) => ({
                ...data,
                customer_id: null,
                customer: { name: '', phone: '', address: '' },
                delivery_address: '',
            }));

            return;
        }

        const customer = customers.find((item) => item.id === Number(value));

        if (!customer) {
            return;
        }

        form.setData((data) => ({
            ...data,
            customer_id: customer.id,
            customer: { name: '', phone: '', address: '' },
            delivery_address: customer.address ?? '',
        }));
    };

    const discard = () => {
        previewSequence.current += 1;
        form.setData(createInitialDraft(today));
        form.clearErrors();
        setQuote(null);
        setQuotedPreviewKey('');
        setQuoteError('');
        setQuoteLoading(false);
        setCreatedOrder(null);
    };

    const submitQuickOrder = async () => {
        if (quickSubmitLocked.current || !currentQuote || currentQuoteLoading) {
            return null;
        }

        quickSubmitLocked.current = true;
        setSubmittingQuickOrder(true);
        form.clearErrors();

        try {
            const { data } = await postJson<{ data: AdminCreatedOrder }>(
                '/admin/orders',
                form.data,
            );
            setCreatedOrder(data);
            form.setData(createInitialDraft(today));
            form.clearErrors();
            setQuote(null);
            setQuotedPreviewKey('');
            setQuoteError('');
            setQuoteLoading(false);

            return data;
        } catch (error) {
            form.setError(
                'items',
                error instanceof Error
                    ? error.message
                    : 'No pudimos crear el pedido.',
            );

            return null;
        } finally {
            quickSubmitLocked.current = false;
            setSubmittingQuickOrder(false);
        }
    };

    const submitFullOrder = () => form.post('/admin/orders');

    return {
        form,
        quote: currentQuote,
        quoteLoading: currentQuoteLoading,
        quoteError,
        changeProduct,
        removeProduct,
        changeAddon,
        selectCustomer,
        discard,
        submitFullOrder,
        submitQuickOrder,
        submittingQuickOrder,
        createdOrder,
        today,
        clearCreatedOrder: () => setCreatedOrder(null),
    };
}

export type AdminOrderDraft = ReturnType<typeof useAdminOrderDraft>;

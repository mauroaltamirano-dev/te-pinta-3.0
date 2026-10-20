import { useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { notifySubmissionFailure } from '@/lib/submission-failure';
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
    condition: 'cooked',
    customer_id: null,
    customer: { name: '', phone: '', address: '' },
    delivery_type: 'pickup',
    delivery_date: today,
    delivery_period: 'night',
    delivery_time: '',
    delivery_address: '',
    notes: '',
    payment_status: 'unpaid',
    payment_method: null,
    discount_type: null,
    discount_value: null,
});

interface AdminOrderDraftOptions {
    initialDraft?: AdminOrderForm;
    initialQuote?: PriceQuote;
    submitUrl?: string;
    submitMethod?: 'post' | 'put';
}

export function useAdminOrderDraft(
    customers: AdminCustomerOption[],
    today: string,
    options: AdminOrderDraftOptions = {},
) {
    const initialDraft = options.initialDraft ?? createInitialDraft(today);
    const form = useForm<AdminOrderForm>(initialDraft);
    const initialPreviewKey = JSON.stringify({
        items: initialDraft.items,
        addons: initialDraft.addons,
        condition: initialDraft.condition,
        delivery_type: initialDraft.delivery_type,
        discount_type: initialDraft.discount_type,
        discount_value: initialDraft.discount_value,
    });
    const [quote, setQuote] = useState<PriceQuote | null>(
        options.initialQuote ?? null,
    );
    const [quotedPreviewKey, setQuotedPreviewKey] = useState(
        options.initialQuote ? initialPreviewKey : '',
    );
    const [quoteLoading, setQuoteLoading] = useState(false);
    const [quoteError, setQuoteError] = useState('');
    const [submittingQuickOrder, setSubmittingQuickOrder] = useState(false);
    const [createdOrder, setCreatedOrder] = useState<AdminCreatedOrder | null>(
        null,
    );
    const previewSequence = useRef(0);
    const quickSubmitLocked = useRef(false);
    const autoFilledDeliveryAddress = useRef<string | null>(null);
    const preview = useMemo(
        () => ({
            items: form.data.items,
            addons: form.data.addons,
            condition: form.data.condition,
            delivery_type: form.data.delivery_type,
            discount_type: form.data.discount_type,
            discount_value: form.data.discount_value,
        }),
        [
            form.data.items,
            form.data.addons,
            form.data.condition,
            form.data.delivery_type,
            form.data.discount_type,
            form.data.discount_value,
        ],
    );
    const previewKey = JSON.stringify(preview);
    const isInitialEconomicDraft = previewKey === initialPreviewKey;
    const currentQuote =
        isInitialEconomicDraft && options.initialQuote
            ? options.initialQuote
            : quotedPreviewKey === previewKey
              ? quote
              : null;
    const currentQuoteLoading =
        preview.items.length > 0 &&
        !isInitialEconomicDraft &&
        (quoteLoading || quotedPreviewKey !== previewKey);
    const paymentReady =
        currentQuote?.total === 0 ||
        form.data.payment_status !== 'paid' ||
        form.data.payment_method !== null;

    useEffect(() => {
        const sequence = ++previewSequence.current;

        if (!preview.items.length) {
            return;
        }

        if (options.initialQuote && previewKey === initialPreviewKey) {
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
    }, [initialPreviewKey, options.initialQuote, preview, previewKey]);

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
                delivery_address:
                    data.delivery_address === autoFilledDeliveryAddress.current
                        ? ''
                        : data.delivery_address,
            }));
            autoFilledDeliveryAddress.current = null;

            return;
        }

        const customer = customers.find((item) => item.id === Number(value));

        if (!customer) {
            return;
        }

        form.setData((data) => {
            const wasAutoFilled =
                Boolean(data.delivery_address) &&
                data.delivery_address === autoFilledDeliveryAddress.current;
            const canAutoFill =
                data.delivery_type === 'delivery' &&
                (!data.delivery_address || wasAutoFilled);
            const deliveryAddress = canAutoFill
                ? (customer.address ?? '')
                : wasAutoFilled
                  ? ''
                  : data.delivery_address;

            autoFilledDeliveryAddress.current = canAutoFill
                ? deliveryAddress || null
                : null;

            return {
                ...data,
                customer_id: customer.id,
                customer: { name: '', phone: '', address: '' },
                delivery_address: deliveryAddress,
            };
        });
    };

    const selectDeliveryType = (
        deliveryType: 'pickup' | 'delivery' | 'dine_in',
    ) => {
        form.setData((data) => {
            if (deliveryType !== 'delivery' || data.delivery_address) {
                return { ...data, delivery_type: deliveryType };
            }

            const customer = customers.find(
                (item) => item.id === data.customer_id,
            );
            const deliveryAddress = customer?.address ?? '';
            autoFilledDeliveryAddress.current = deliveryAddress || null;

            return {
                ...data,
                delivery_type: deliveryType,
                delivery_address: deliveryAddress,
            };
        });
    };

    const setDeliveryAddress = (deliveryAddress: string) => {
        autoFilledDeliveryAddress.current = null;
        form.setData('delivery_address', deliveryAddress);
    };

    const setCustomerAddress = (customerAddress: string) => {
        form.setData((data) => {
            const shouldSyncDeliveryAddress =
                data.delivery_type === 'delivery' &&
                (!data.delivery_address ||
                    data.delivery_address ===
                        autoFilledDeliveryAddress.current);

            if (shouldSyncDeliveryAddress) {
                autoFilledDeliveryAddress.current = customerAddress || null;
            }

            return {
                ...data,
                customer: { ...data.customer, address: customerAddress },
                delivery_address: shouldSyncDeliveryAddress
                    ? customerAddress
                    : data.delivery_address,
            };
        });
    };

    const discard = () => {
        previewSequence.current += 1;
        autoFilledDeliveryAddress.current = null;
        form.setData(initialDraft);
        form.clearErrors();
        setQuote(null);
        setQuotedPreviewKey('');
        setQuoteError('');
        setQuoteLoading(false);
        setCreatedOrder(null);
    };

    const submitQuickOrder = async () => {
        if (
            quickSubmitLocked.current ||
            !currentQuote ||
            currentQuoteLoading ||
            !paymentReady
        ) {
            if (!paymentReady) {
                form.setError('payment_method', 'Seleccioná un medio de pago.');
            }

            return null;
        }

        quickSubmitLocked.current = true;
        setSubmittingQuickOrder(true);
        form.clearErrors();

        try {
            const { data } = await postJson<{ data: AdminCreatedOrder }>(
                '/admin/orders',
                form.data.delivery_type === 'dine_in'
                    ? // A counter order is immediate and anonymous: no schedule, address or customer.
                      {
                          ...form.data,
                          customer_id: null,
                          customer: null,
                          delivery_date: null,
                          delivery_period: null,
                          delivery_time: null,
                          delivery_address: null,
                      }
                    : form.data,
            );
            setCreatedOrder(data);
            autoFilledDeliveryAddress.current = null;
            form.setData(createInitialDraft(today));
            form.clearErrors();
            setQuote(null);
            setQuotedPreviewKey('');
            setQuoteError('');
            setQuoteLoading(false);

            return data;
        } catch (error) {
            const message =
                error instanceof Error
                    ? error.message
                    : 'No pudimos crear el pedido.';
            form.setError('items', message);
            notifySubmissionFailure(message);

            return null;
        } finally {
            quickSubmitLocked.current = false;
            setSubmittingQuickOrder(false);
        }
    };

    const submitFullOrder = () => {
        if (!paymentReady) {
            form.setError('payment_method', 'Seleccioná un medio de pago.');

            return;
        }

        const url = options.submitUrl ?? '/admin/orders';

        if (options.submitMethod === 'put') {
            form.put(url);

            return;
        }

        form.post(url);
    };

    return {
        form,
        quote: currentQuote,
        quoteLoading: currentQuoteLoading,
        quoteError,
        paymentReady,
        changeProduct,
        removeProduct,
        changeAddon,
        selectCustomer,
        selectDeliveryType,
        setDeliveryAddress,
        setCustomerAddress,
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

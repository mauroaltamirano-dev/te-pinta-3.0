import { mkdir, rm, stat, utimes } from 'node:fs/promises';
import { expect, test } from '@playwright/test';
import type { Locator, Page, TestInfo } from '@playwright/test';

async function logIn(page: Page) {
    await page.goto('/login');
    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);
    await page.waitForLoadState('load');
}

function moneyPattern(amount: string) {
    return new RegExp(`\\$\\s*${amount}`);
}

function summaryCard(page: Page, label: string) {
    return page
        .getByRole('region', { name: 'Resumen financiero' })
        .locator('article')
        .filter({ hasText: label });
}

async function summaryAmount(page: Page, label: string) {
    const text = (await summaryCard(page, label).textContent()) ?? '';
    const amount = Number(text.match(/\$\s*([\d.]+)/)?.[1].replaceAll('.', ''));

    expect(Number.isFinite(amount)).toBe(true);

    return amount;
}

function historyRow(page: Page, testInfo: TestInfo, concept: string): Locator {
    return testInfo.project.name === 'mobile-chromium'
        ? page.locator('section article').filter({ hasText: concept })
        : page.locator('tbody tr').filter({ hasText: concept });
}

async function registerMovement(
    page: Page,
    walletName: string,
    type: string,
    amount: string,
    concept: string,
    effectiveDate?: string,
) {
    await page
        .getByRole('button', {
            name: 'Movimiento manual / Más acciones',
            exact: true,
        })
        .click();
    const dialog = page.getByRole('dialog');

    await dialog.getByLabel('Billetera').click();
    await page.getByRole('option', { name: walletName, exact: true }).click();
    await dialog.getByLabel('Tipo').click();
    await page.getByRole('option', { name: type, exact: true }).click();
    await dialog.getByLabel('Importe').fill(amount);
    await dialog.getByLabel('Concepto').fill(concept);

    if (effectiveDate) {
        await dialog.getByLabel('Fecha').fill(effectiveDate);
    }

    await dialog
        .getByRole('button', { name: 'Registrar movimiento', exact: true })
        .click();
    await expect(dialog).toHaveCount(0);
    await page.waitForLoadState('networkidle');
}

async function registerOutflow(
    page: Page,
    triggerName: string,
    submitName: string,
    walletName: string,
    categoryName: string,
    amount: string,
    concept: string,
    counterparty?: string,
) {
    await page.getByRole('button', { name: triggerName, exact: true }).click();
    const dialog = page.getByRole('dialog');

    await dialog.getByLabel('Categoría').click();
    await page.getByRole('option', { name: categoryName, exact: true }).click();
    await dialog.getByLabel('Billetera').click();
    await page.getByRole('option', { name: walletName, exact: true }).click();
    await dialog.getByLabel('Descripción / concepto').fill(concept);

    if (counterparty) {
        await dialog.getByLabel(/Comercio o proveedor/).fill(counterparty);
    }

    await dialog.getByLabel('Importe').fill(amount);
    await dialog.getByRole('button', { name: submitName, exact: true }).click();
    await expect(dialog).toHaveCount(0);
}

async function createWallet(
    page: Page,
    walletName: string,
    receivesOrderPayments = false,
) {
    await page
        .getByRole('button', { name: 'Nueva billetera', exact: true })
        .click();
    const dialog = page.getByRole('dialog');

    await dialog.getByLabel('Nombre').fill(walletName);

    if (receivesOrderPayments) {
        await dialog.getByLabel('Recibir cobros de pedidos').check();
    }

    await dialog
        .getByRole('button', { name: 'Crear billetera', exact: true })
        .click();
    await expect(dialog).toHaveCount(0);
}

async function createOrderForPayment(page: Page, customerName: string) {
    await page.goto('/admin/orders/create');
    await page
        .getByRole('button', { name: 'Agregar una docena de Salteña' })
        .click();
    await page.getByLabel('Nombre').fill(customerName);

    const formattedTotal = await page.getByTestId('order-total').textContent();
    const amount = Number((formattedTotal ?? '').replace(/\D/g, ''));

    expect(amount).toBeGreaterThan(0);
    await page.getByRole('button', { name: 'Crear pedido' }).click();
    await expect(page).toHaveURL(/\/admin\/orders\/TP-\d+$/);

    return {
        amount,
        publicId: page.url().split('/').at(-1) as string,
    };
}

async function confirmOrderPayment(page: Page, publicId: string) {
    await page.goto(`/admin/orders?search=${publicId}`);
    const orderCard = page.getByTestId('order-card').filter({
        has: page.getByRole('button', { name: publicId, exact: true }),
    });

    await orderCard
        .getByRole('switch', { name: `Marcar como pagado ${publicId}` })
        .click();
    await page.getByLabel(`Medio de pago de ${publicId}`).click();
    await page.getByRole('option', { name: 'Efectivo', exact: true }).click();
    await page.getByRole('button', { name: 'Confirmar pago' }).click();
    await expect(
        orderCard.getByRole('switch', {
            name: `Marcar como no pagado ${publicId}`,
        }),
    ).toHaveAttribute('aria-checked', 'true');
}

async function markOrderUnpaid(page: Page, publicId: string) {
    const orderCard = page.getByTestId('order-card').filter({
        has: page.getByRole('button', { name: publicId, exact: true }),
    });

    await orderCard
        .getByRole('switch', { name: `Marcar como no pagado ${publicId}` })
        .click();
    await expect(
        orderCard.getByRole('switch', {
            name: `Marcar como pagado ${publicId}`,
        }),
    ).toHaveAttribute('aria-checked', 'false');
}

async function acquireDistributionFlowLock() {
    const lockPath = 'storage/app/playwright-management-distribution.lock';
    const deadline = Date.now() + 150_000;

    while (Date.now() < deadline) {
        try {
            await mkdir(lockPath);
            const heartbeat = setInterval(() => {
                const now = new Date();

                void utimes(lockPath, now, now).catch(() => undefined);
            }, 5_000);

            return async () => {
                clearInterval(heartbeat);
                await rm(lockPath, { recursive: true, force: true });
            };
        } catch (error) {
            const code = (error as NodeJS.ErrnoException).code;

            if (code !== 'EEXIST') {
                throw error;
            }

            const lockStat = await stat(lockPath).catch(() => null);

            if (lockStat && Date.now() - lockStat.mtimeMs > 30_000) {
                await rm(lockPath, { recursive: true, force: true });
                continue;
            }

            await new Promise((resolve) => setTimeout(resolve, 150));
        }
    }

    throw new Error(
        'Timed out waiting for the Management distribution E2E lock.',
    );
}

async function createDistributionRule(
    page: Page,
    ruleName: string,
    allocations: Array<{ wallet: string; percentage: string }>,
) {
    await page
        .getByRole('button', { name: 'Crear regla', exact: true })
        .click();
    const dialog = page.getByRole('dialog');

    await dialog.getByLabel('Nombre').fill(ruleName);

    for (const [index, allocation] of allocations.entries()) {
        await dialog.getByLabel(`Billetera ${index + 1}`).click();
        await page
            .getByRole('option', { name: allocation.wallet, exact: true })
            .click();
        await dialog
            .getByLabel('Porcentaje', { exact: true })
            .nth(index)
            .fill(allocation.percentage);
    }

    await expect(dialog.getByRole('status')).toContainText('100%');
    await dialog
        .getByRole('button', { name: 'Crear regla', exact: true })
        .click();
    await expect(dialog).toHaveCount(0);
}

function walletCard(page: Page, walletName: string) {
    return page.locator('article').filter({
        hasText: walletName,
        has: page.getByRole('button', { name: 'Editar', exact: true }),
    });
}

test('Gestión conserva comprobantes privados al revertir una compra', async ({
    page,
}, testInfo) => {
    test.setTimeout(120_000);
    const suffix = `${testInfo.project.name}-${testInfo.parallelIndex}-${testInfo.retry}`;
    const walletName = `Comprobantes E2E ${suffix}`;
    const openingConcept = `Saldo comprobantes E2E ${suffix}`;
    const purchaseConcept = `Compra documentada E2E ${suffix}`;
    const reversalConcept = `Reversión documentada E2E ${suffix}`;

    await logIn(page);
    await page.goto('/admin/management');
    await createWallet(page, walletName);
    await registerMovement(
        page,
        walletName,
        'Apertura',
        '30000',
        openingConcept,
    );
    await registerOutflow(
        page,
        'Registrar compra',
        'Registrar compra',
        walletName,
        'Materia prima',
        '5000',
        purchaseConcept,
        'Proveedor documentado E2E',
    );

    await expect(walletCard(page, walletName)).toContainText(
        moneyPattern('25\\.000'),
    );
    const purchaseRow = historyRow(page, testInfo, purchaseConcept);

    await expect(purchaseRow).toHaveCount(1);
    await purchaseRow.getByRole('button', { name: /Ver detalle/ }).click();

    let detail = page.getByRole('dialog');
    let attachments = detail.getByRole('region', {
        name: /Comprobantes/,
    });
    const fileInput = attachments.getByLabel('Seleccionar comprobante');

    await expect(attachments).toContainText('Sin comprobantes adjuntos');
    await fileInput.setInputFiles('tests/e2e/fixtures/management-receipt.pdf');
    await expect(attachments).toContainText('management-receipt.pdf');
    await expect(attachments).toContainText('PDF');
    await expect(attachments).toContainText('Comprobantes (1)');

    const previewLink = attachments.getByRole('link', {
        name: 'Ver management-receipt.pdf',
    });
    await expect(previewLink).toHaveAttribute('href', /disposition=inline/);
    const previewHref = await previewLink.getAttribute('href');

    expect(previewHref).not.toBeNull();
    const preview = await page.request.get(previewHref as string);

    expect(preview.ok()).toBe(true);
    expect(preview.headers()['content-type']).toContain('application/pdf');
    expect((await preview.body()).length).toBeGreaterThan(0);

    const downloadPromise = page.waitForEvent('download');
    await attachments
        .getByRole('link', {
            name: 'Descargar management-receipt.pdf',
        })
        .click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toBe('management-receipt.pdf');
    const downloadPath = await download.path();

    expect(downloadPath).not.toBeNull();
    expect((await stat(downloadPath as string)).size).toBeGreaterThan(0);

    await fileInput.setInputFiles('tests/e2e/fixtures/management-receipt.png');
    await expect(attachments).toContainText('management-receipt.png');
    await expect(attachments).toContainText('PNG');
    await expect(attachments).toContainText('Comprobantes (2)');

    await detail.getByRole('button', { name: 'Cerrar' }).click();
    await expect(detail).toHaveCount(0);
    await expect(historyRow(page, testInfo, purchaseConcept)).toHaveCount(1);
    await expect(historyRow(page, testInfo, purchaseConcept)).toContainText(
        '2 comprobantes',
    );
    await expect(walletCard(page, walletName)).toContainText(
        moneyPattern('25\\.000'),
    );

    await historyRow(page, testInfo, purchaseConcept)
        .getByRole('button', { name: /Revertir movimiento/ })
        .click();
    const reversalDialog = page.getByRole('dialog');

    await reversalDialog.getByLabel('Concepto').fill(reversalConcept);
    await reversalDialog
        .getByRole('button', { name: 'Crear reversión', exact: true })
        .click();
    await expect(reversalDialog).toHaveCount(0);
    await expect(walletCard(page, walletName)).toContainText(
        moneyPattern('30\\.000'),
    );

    const reversedPurchase = historyRow(page, testInfo, purchaseConcept);

    await expect(reversedPurchase).toHaveCount(1);
    await expect(reversedPurchase).toContainText('Revertido');
    await reversedPurchase.getByRole('button', { name: /Ver detalle/ }).click();

    detail = page.getByRole('dialog');
    attachments = detail.getByRole('region', { name: /Comprobantes/ });
    await expect(attachments).toContainText('management-receipt.pdf');
    await expect(attachments).toContainText('management-receipt.png');
    await expect(attachments).toContainText('Comprobantes (2)');

    if (testInfo.project.name === 'mobile-chromium') {
        expect(
            await page.evaluate(
                () =>
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
            ),
        ).toBeLessThanOrEqual(1);
    }
});

test('Gestión registra un ledger manual, deriva saldos y archiva la billetera', async ({
    page,
}, testInfo) => {
    test.setTimeout(120_000);
    const suffix = `${testInfo.project.name}-${testInfo.parallelIndex}-${testInfo.retry}`;
    const walletName = `Caja E2E ${suffix}`;
    const openingConcept = `Apertura E2E ${suffix}`;
    const incomeConcept = `Ingreso E2E ${suffix}`;
    const expenseConcept = `Egreso E2E ${suffix}`;
    const purchaseConcept = `Compra E2E ${suffix}`;
    const operatingExpenseConcept = `Servicio E2E ${suffix}`;
    const withdrawalConcept = `Retiro E2E ${suffix}`;
    const reversalConcept = `Corrección E2E ${suffix}`;

    await logIn(page);
    await page.goto('/admin/management');
    await expect(
        page.getByRole('heading', { name: 'Gestión', exact: true }),
    ).toBeVisible();

    await page
        .getByRole('button', { name: 'Nueva billetera', exact: true })
        .click();
    const walletDialog = page.getByRole('dialog');
    await walletDialog.getByLabel('Nombre').fill(walletName);
    await walletDialog
        .getByRole('button', { name: 'Crear billetera', exact: true })
        .click();
    await expect(walletDialog).toHaveCount(0);

    let walletCard = page.locator('article').filter({
        hasText: walletName,
        has: page.getByRole('button', { name: 'Editar', exact: true }),
    });
    await expect(walletCard).toHaveCount(1);
    await expect(walletCard).toContainText(moneyPattern('0'));

    await registerMovement(
        page,
        walletName,
        'Apertura',
        '50000',
        openingConcept,
    );
    await expect(walletCard).toContainText(moneyPattern('50\\.000'));

    await registerMovement(
        page,
        walletName,
        'Ingreso manual',
        '7000',
        incomeConcept,
    );
    await registerMovement(
        page,
        walletName,
        'Egreso manual',
        '12000',
        expenseConcept,
    );

    walletCard = page.locator('article').filter({
        hasText: walletName,
        has: page.getByRole('button', { name: 'Editar', exact: true }),
    });
    await expect(walletCard).toContainText(moneyPattern('45\\.000'));
    // Desktop and mobile projects intentionally share the isolated Playwright
    // database, so period-wide metrics may include the sibling project's
    // namespaced movements. Exact aggregate semantics are covered in Feature.
    await expect(summaryCard(page, 'Ingresos del período')).toContainText(
        /\$\s*\d/,
    );
    await expect(summaryCard(page, 'Egresos operativos')).toContainText(
        /\$\s*\d/,
    );

    await expect(historyRow(page, testInfo, openingConcept)).toContainText(
        moneyPattern('50\\.000'),
    );
    await expect(historyRow(page, testInfo, incomeConcept)).toContainText(
        moneyPattern('7\\.000'),
    );
    await expect(historyRow(page, testInfo, expenseConcept)).toContainText(
        moneyPattern('12\\.000'),
    );

    await registerOutflow(
        page,
        'Registrar compra',
        'Registrar compra',
        walletName,
        'Materia prima',
        '5000',
        purchaseConcept,
        'Distribuidora E2E',
    );
    await registerOutflow(
        page,
        'Registrar gasto',
        'Registrar gasto',
        walletName,
        'Servicios',
        '3000',
        operatingExpenseConcept,
        'Proveedor E2E',
    );
    await registerOutflow(
        page,
        'Retiro personal',
        'Registrar retiro personal',
        walletName,
        'Personal',
        '2000',
        withdrawalConcept,
    );

    walletCard = page.locator('article').filter({
        hasText: walletName,
        has: page.getByRole('button', { name: 'Editar', exact: true }),
    });
    await expect(walletCard).toContainText(moneyPattern('35\\.000'));

    const purchaseRow = historyRow(page, testInfo, purchaseConcept);
    await expect(purchaseRow).toContainText('Compra');
    await expect(purchaseRow).toContainText('Materia prima');
    await expect(purchaseRow).toContainText(moneyPattern('5\\.000'));
    await expect(
        historyRow(page, testInfo, operatingExpenseConcept),
    ).toContainText('Gasto');
    await expect(historyRow(page, testInfo, withdrawalConcept)).toContainText(
        'Retiro personal',
    );

    await purchaseRow.getByRole('button', { name: /Ver detalle/ }).click();
    const detail = page.getByRole('dialog');
    await expect(detail).toContainText(purchaseConcept);
    await expect(detail).toContainText('Distribuidora E2E');
    await expect(detail).toContainText('Materia prima');
    await expect(detail).toContainText(walletName);
    await detail.getByRole('button', { name: 'Cerrar' }).click();
    await expect(detail).toHaveCount(0);

    await purchaseRow
        .getByRole('button', { name: /Revertir movimiento/ })
        .click();
    const reversalDialog = page.getByRole('dialog');
    await reversalDialog.getByLabel('Concepto').fill(reversalConcept);
    await reversalDialog
        .getByRole('button', { name: 'Crear reversión', exact: true })
        .click();
    await expect(reversalDialog).toHaveCount(0);

    walletCard = page.locator('article').filter({
        hasText: walletName,
        has: page.getByRole('button', { name: 'Editar', exact: true }),
    });
    await expect(walletCard).toContainText(moneyPattern('40\\.000'));
    await expect(historyRow(page, testInfo, purchaseConcept)).toContainText(
        'Revertido',
    );
    await expect(historyRow(page, testInfo, reversalConcept)).toContainText(
        'Reversión',
    );

    if (testInfo.project.name === 'mobile-chromium') {
        await expect(page.locator('table')).toBeHidden();
        expect(
            await page.evaluate(
                () =>
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
            ),
        ).toBeLessThanOrEqual(1);
    } else {
        await expect(page.locator('table')).toBeVisible();
    }

    await page.screenshot({
        path: `storage/app/management-${testInfo.project.name === 'mobile-chromium' ? 'mobile' : 'desktop'}.png`,
        fullPage: true,
        animations: 'disabled',
    });

    await walletCard
        .getByRole('button', { name: 'Archivar', exact: true })
        .click();
    const archiveDialog = page.getByRole('dialog');
    await archiveDialog
        .getByRole('button', { name: 'Archivar billetera', exact: true })
        .click();
    await expect(archiveDialog).toHaveCount(0);
    await expect(
        page.locator('article').filter({
            hasText: walletName,
            has: page.getByRole('button', { name: 'Reactivar', exact: true }),
        }),
    ).toHaveCount(1);
    await expect(historyRow(page, testInfo, openingConcept)).toContainText(
        `${walletName} (archivada)`,
    );
});

test('Gestión transfiere entre billeteras sin alterar el saldo global ni las métricas', async ({
    page,
}, testInfo) => {
    test.setTimeout(120_000);
    const suffix = `${testInfo.project.name}-${testInfo.parallelIndex}-${testInfo.retry}`;
    const sourceName = `Banco transferencia ${suffix}`;
    const destinationName = `Caja transferencia ${suffix}`;
    const sourceOpening = `Apertura banco transferencia ${suffix}`;
    const destinationOpening = `Apertura caja transferencia ${suffix}`;
    const transferConcept = `Movimiento interno ${suffix}`;
    const reversalConcept = `Corrección transferencia ${suffix}`;
    const effectiveDate =
        testInfo.project.name === 'mobile-chromium'
            ? '2026-06-15'
            : '2026-07-15';

    await logIn(page);
    await page.goto('/admin/management');
    await createWallet(page, sourceName);
    await createWallet(page, destinationName);

    await registerMovement(
        page,
        sourceName,
        'Apertura',
        '50000',
        sourceOpening,
        effectiveDate,
    );
    await registerMovement(
        page,
        destinationName,
        'Apertura',
        '10000',
        destinationOpening,
        effectiveDate,
    );

    await page.goto(
        `/admin/management?from=${effectiveDate}&to=${effectiveDate}`,
    );

    const incomeBefore = await summaryCard(
        page,
        'Ingresos del período',
    ).textContent();
    const operatingBefore = await summaryCard(
        page,
        'Egresos operativos',
    ).textContent();
    const withdrawalsBefore = await summaryCard(
        page,
        'Retiros personales',
    ).textContent();
    const transferButton = page.getByRole('button', {
        name: 'Transferir',
        exact: true,
    });
    const transferButtonBox = await transferButton.boundingBox();

    expect(transferButtonBox?.height).toBeGreaterThanOrEqual(44);
    await transferButton.click();
    const dialog = page.getByRole('dialog');

    await dialog.getByLabel('Desde').click();
    await page.getByRole('option', { name: new RegExp(sourceName) }).click();
    await dialog.getByLabel('Hacia').click();
    await expect(
        page.getByRole('option', { name: new RegExp(`^${sourceName}`) }),
    ).toHaveCount(0);
    await page
        .getByRole('option', { name: new RegExp(destinationName) })
        .click();
    await dialog.getByLabel('Importe').fill('20000');
    await dialog.getByLabel('Fecha').fill(effectiveDate);
    await dialog.getByLabel(/Descripción/).fill(transferConcept);
    await dialog
        .getByRole('button', {
            name: 'Confirmar transferencia',
            exact: true,
        })
        .click();
    await expect(dialog).toHaveCount(0);

    await expect(walletCard(page, sourceName)).toContainText(
        moneyPattern('30\\.000'),
    );
    await expect(walletCard(page, destinationName)).toContainText(
        moneyPattern('30\\.000'),
    );
    await expect(summaryCard(page, 'Ingresos del período')).toHaveText(
        incomeBefore ?? '',
    );
    await expect(summaryCard(page, 'Egresos operativos')).toHaveText(
        operatingBefore ?? '',
    );
    await expect(summaryCard(page, 'Retiros personales')).toHaveText(
        withdrawalsBefore ?? '',
    );

    const transferRow = historyRow(page, testInfo, transferConcept);

    await expect(transferRow).toHaveCount(1);
    await expect(transferRow).toContainText('Transferencia');
    await expect(transferRow).toContainText(
        `${sourceName} → ${destinationName}`,
    );
    await expect(transferRow).toContainText(moneyPattern('20\\.000'));

    await transferRow.getByRole('button', { name: /Ver detalle/ }).click();
    const detail = page.getByRole('dialog');

    await expect(detail).toContainText('Origen');
    await expect(detail).toContainText('Destino');
    await expect(detail).toContainText(sourceName);
    await expect(detail).toContainText(destinationName);
    await expect(detail).toContainText('Impacto financiero');
    await detail.getByRole('button', { name: 'Cerrar' }).click();
    await expect(detail).toHaveCount(0);

    await transferRow
        .getByRole('button', { name: /Revertir movimiento/ })
        .click();
    const reversalDialog = page.getByRole('dialog');

    await expect(
        reversalDialog.getByRole('heading', {
            name: 'Revertir transferencia',
            exact: true,
        }),
    ).toBeVisible();
    await reversalDialog.getByLabel('Concepto').fill(reversalConcept);
    await reversalDialog.getByLabel('Fecha').fill(effectiveDate);
    await reversalDialog
        .getByRole('button', { name: 'Crear reversión', exact: true })
        .click();
    await expect(reversalDialog).toHaveCount(0);

    await expect(walletCard(page, sourceName)).toContainText(
        moneyPattern('50\\.000'),
    );
    await expect(walletCard(page, destinationName)).toContainText(
        moneyPattern('10\\.000'),
    );
    await expect(historyRow(page, testInfo, transferConcept)).toContainText(
        'Revertido',
    );
    await expect(historyRow(page, testInfo, reversalConcept)).toContainText(
        'Reversión de transferencia',
    );

    if (testInfo.project.name === 'mobile-chromium') {
        await expect(page.locator('table')).toBeHidden();
        expect(
            await page.evaluate(
                () =>
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
            ),
        ).toBeLessThanOrEqual(1);
    }
});

test('Gestión versiona una regla y conserva el snapshot de la distribución', async ({
    page,
}, testInfo) => {
    test.setTimeout(300_000);
    const suffix = `${testInfo.project.name}-${testInfo.parallelIndex}-${testInfo.retry}`;
    const sourceWallet = `Caja General distribución ${suffix}`;
    const operationWallet = `Operación distribución ${suffix}`;
    const profitWallet = `Ganancia distribución ${suffix}`;
    const reserveWallet = `Reserva distribución ${suffix}`;
    const ruleName = `Negocio distribución ${suffix}`;
    const concept = `Asignación E2E ${suffix}`;
    const reversalConcept = `Corrección distribución E2E ${suffix}`;
    const effectiveDate =
        testInfo.project.name === 'mobile-chromium'
            ? '2026-08-27'
            : '2026-08-28';

    await logIn(page);
    await page.goto('/admin/management');
    const releaseLock = await acquireDistributionFlowLock();

    try {
        await createWallet(page, sourceWallet);
        await createWallet(page, operationWallet);
        await createWallet(page, profitWallet);
        await createWallet(page, reserveWallet);

        await registerMovement(
            page,
            sourceWallet,
            'Apertura',
            '50000',
            `Apertura distribución ${suffix}`,
            effectiveDate,
        );

        await createDistributionRule(page, ruleName, [
            { wallet: operationWallet, percentage: '50' },
            { wallet: profitWallet, percentage: '30' },
            { wallet: reserveWallet, percentage: '20' },
        ]);

        const ruleCard = page
            .getByRole('region', { name: 'Reglas de distribución' })
            .locator('article')
            .filter({ hasText: ruleName });

        await expect(ruleCard).toContainText('Versión 1');
        await expect(ruleCard).toContainText('50%');
        const totalBefore = await summaryCard(
            page,
            'Saldo total',
        ).textContent();
        await page
            .getByRole('button', { name: 'Distribuir dinero', exact: true })
            .click();
        const distributionDialog = page.getByRole('dialog');

        await distributionDialog.getByLabel('Regla').click();
        await page
            .getByRole('option', { name: `${ruleName} · v1`, exact: true })
            .click();
        await distributionDialog.getByLabel('Desde wallet').click();
        await expect(
            page.getByRole('option', {
                name: new RegExp(`^${operationWallet}`),
            }),
        ).toHaveCount(0);
        await page
            .getByRole('option', { name: new RegExp(`^${sourceWallet}`) })
            .click();
        await distributionDialog.getByLabel('Monto').fill('10000');
        await distributionDialog
            .getByLabel('Fecha efectiva')
            .fill(effectiveDate);
        await distributionDialog.getByLabel(/Descripción/).fill(concept);

        const preview = distributionDialog.getByRole('region', {
            name: 'Vista previa',
        });
        await expect(preview).toContainText(sourceWallet);
        await expect(preview).toContainText(/−\s*\$\s*10\.000/);
        await expect(preview).toContainText(operationWallet);
        await expect(preview).toContainText(moneyPattern('5\\.000'));
        await expect(preview).toContainText(moneyPattern('3\\.000'));
        await expect(preview).toContainText(moneyPattern('2\\.000'));
        await distributionDialog
            .getByRole('button', {
                name: 'Confirmar distribución',
                exact: true,
            })
            .click();
        await expect(distributionDialog).toHaveCount(0);

        await expect(walletCard(page, sourceWallet)).toContainText(
            moneyPattern('40\\.000'),
        );
        await expect(walletCard(page, operationWallet)).toContainText(
            moneyPattern('5\\.000'),
        );
        await expect(walletCard(page, profitWallet)).toContainText(
            moneyPattern('3\\.000'),
        );
        await expect(walletCard(page, reserveWallet)).toContainText(
            moneyPattern('2\\.000'),
        );
        await expect(summaryCard(page, 'Saldo total')).toHaveText(
            totalBefore ?? '',
        );

        const distributionRow = historyRow(page, testInfo, concept);

        await expect(distributionRow).toHaveCount(1);
        await expect(distributionRow).toContainText('Distribución');
        await expect(distributionRow).toContainText(
            `${sourceWallet} → ${operationWallet} / ${profitWallet} / ${reserveWallet}`,
        );
        await expect(distributionRow).toContainText(`${ruleName} v1`);
        await distributionRow
            .getByRole('button', { name: /Ver detalle/ })
            .click();
        const detail = page.getByRole('dialog');

        await expect(detail).toContainText('Impacto financiero');
        await expect(detail).toContainText(sourceWallet);
        await expect(detail).toContainText(/−\s*\$\s*10\.000/);
        await expect(detail).toContainText(operationWallet);
        await expect(detail).toContainText('50%');
        await expect(detail).toContainText(moneyPattern('5\\.000'));
        await detail
            .getByRole('button', {
                name:
                    testInfo.project.name === 'mobile-chromium'
                        ? 'Cerrar detalle'
                        : 'Cerrar',
                exact: true,
            })
            .click();
        await expect(detail).toHaveCount(0);

        await distributionRow
            .getByRole('button', { name: /Revertir movimiento/ })
            .click();
        const reversalDialog = page.getByRole('dialog');

        await reversalDialog.getByLabel('Concepto').fill(reversalConcept);
        await reversalDialog.getByLabel('Fecha').fill(effectiveDate);
        await reversalDialog
            .getByRole('button', { name: 'Crear reversión', exact: true })
            .click();
        await expect(reversalDialog).toHaveCount(0);

        await expect(walletCard(page, sourceWallet)).toContainText(
            moneyPattern('50\\.000'),
        );
        await expect(walletCard(page, operationWallet)).toContainText(
            moneyPattern('0'),
        );
        await expect(walletCard(page, profitWallet)).toContainText(
            moneyPattern('0'),
        );
        await expect(walletCard(page, reserveWallet)).toContainText(
            moneyPattern('0'),
        );
        await expect(summaryCard(page, 'Saldo total')).toHaveText(
            totalBefore ?? '',
        );
        await expect(historyRow(page, testInfo, concept)).toContainText(
            'Revertido',
        );
        await expect(historyRow(page, testInfo, reversalConcept)).toContainText(
            'Reversión de distribución',
        );

        await ruleCard
            .getByRole('button', { name: 'Nueva versión', exact: true })
            .click();
        const versionDialog = page.getByRole('dialog');
        const percentageInputs = versionDialog.getByLabel('Porcentaje', {
            exact: true,
        });

        await percentageInputs.nth(0).fill('60');
        await percentageInputs.nth(1).fill('25');
        await percentageInputs.nth(2).fill('15');

        if (testInfo.project.name === 'mobile-chromium') {
            await percentageInputs.nth(2).press('Enter');
        } else {
            await versionDialog
                .getByRole('button', {
                    name: 'Crear nueva versión',
                    exact: true,
                })
                .click();
        }

        await expect(versionDialog).toHaveCount(0);
        await expect(ruleCard).toContainText('Versión 2');
        await ruleCard
            .getByText('Historial de versiones', { exact: true })
            .click();
        await expect(ruleCard).toContainText('Versión 1');
        await expect(historyRow(page, testInfo, concept)).toContainText(
            `${ruleName} v1`,
        );

        if (testInfo.project.name === 'mobile-chromium') {
            expect(
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth -
                        document.documentElement.clientWidth,
                ),
            ).toBeLessThanOrEqual(1);
        }
    } finally {
        await releaseLock();
    }
});

test('los pagos de pedidos se distribuyen una vez y conservan su versión', async ({
    page,
}, testInfo) => {
    test.setTimeout(420_000);
    const suffix = `${testInfo.project.name}-${testInfo.parallelIndex}-${testInfo.retry}`;
    const receivingWallet = `Caja cobros automáticos ${suffix}`;
    const operationWallet = `Operación cobros ${suffix}`;
    const profitWallet = `Ganancia cobros ${suffix}`;
    const reserveWallet = `Reserva cobros ${suffix}`;
    const ruleName = `Negocio cobros ${suffix}`;
    const firstCustomer = `Cliente cobro v1 ${suffix}`;
    const secondCustomer = `Cliente cobro v2 ${suffix}`;
    const pendingCustomer = `Cliente cobro pendiente ${suffix}`;
    const distributionReversal = `Reversión distribución cobro ${suffix}`;
    const paymentReversal = `Reversión pago cobro ${suffix}`;

    await logIn(page);
    await page.goto('/admin/management');

    const releaseLock = await acquireDistributionFlowLock();

    try {
        await createWallet(page, receivingWallet, true);
        await createWallet(page, operationWallet);
        await createWallet(page, profitWallet);
        await createWallet(page, reserveWallet);
        await createDistributionRule(page, ruleName, [
            { wallet: operationWallet, percentage: '50' },
            { wallet: profitWallet, percentage: '30' },
            { wallet: reserveWallet, percentage: '20' },
        ]);

        const incomeBefore = await summaryAmount(page, 'Ingresos del período');
        const distributionsBefore = await summaryAmount(
            page,
            'Distribuciones internas',
        );
        const outflowsBefore = await summaryAmount(page, 'Egresos operativos');
        const firstOrder = await createOrderForPayment(page, firstCustomer);
        const firstAllocation = distributionParts(
            firstOrder.amount,
            [50, 30, 20],
        );

        await confirmOrderPayment(page, firstOrder.publicId);
        await page.goto('/admin/management');

        await expect(walletCard(page, receivingWallet)).toContainText(
            moneyPattern('0'),
        );
        await expect(walletCard(page, operationWallet)).toContainText(
            moneyPattern(firstAllocation[0].toLocaleString('es-AR')),
        );
        await expect(walletCard(page, profitWallet)).toContainText(
            moneyPattern(firstAllocation[1].toLocaleString('es-AR')),
        );
        await expect(walletCard(page, reserveWallet)).toContainText(
            moneyPattern(firstAllocation[2].toLocaleString('es-AR')),
        );
        expect(firstAllocation.reduce((sum, amount) => sum + amount, 0)).toBe(
            firstOrder.amount,
        );
        await expect
            .poll(() => summaryAmount(page, 'Ingresos del período'))
            .toBe(incomeBefore + firstOrder.amount);
        await expect
            .poll(() => summaryAmount(page, 'Distribuciones internas'))
            .toBe(distributionsBefore + firstOrder.amount);
        await expect
            .poll(() => summaryAmount(page, 'Egresos operativos'))
            .toBe(outflowsBefore);

        let firstPaymentRow = historyRow(
            page,
            testInfo,
            `Pago de pedido ${firstOrder.publicId}`,
        );
        const firstDistributionRow = historyRow(
            page,
            testInfo,
            `Distribución de pedido ${firstOrder.publicId}`,
        );

        await expect(firstPaymentRow).toHaveCount(1);
        await expect(firstPaymentRow).toContainText('Distribuido');
        await expect(firstDistributionRow).toHaveCount(1);
        await expect(firstDistributionRow).toContainText(`${ruleName} v1`);
        await firstPaymentRow
            .getByRole('button', { name: /Ver detalle/ })
            .click();

        let detail = page.getByRole('dialog');

        await expect(detail).toContainText('Distribuido');
        await expect(detail).toContainText(`${ruleName} v1`);
        await expect(detail).toContainText(
            moneyPattern(firstOrder.amount.toLocaleString('es-AR')),
        );
        const relatedTarget = detail.getByRole('button', {
            name: 'Ver distribución',
            exact: true,
        });
        const relatedTargetBox = await relatedTarget.boundingBox();

        expect(relatedTargetBox?.height).toBeGreaterThanOrEqual(44);
        await relatedTarget.click();
        detail = page.getByRole('dialog');
        await expect(detail).toContainText(
            `Pago de pedido ${firstOrder.publicId}`,
        );
        await expect(detail).toContainText('Origen del negocio');
        await detail
            .getByRole('button', {
                name:
                    testInfo.project.name === 'mobile-chromium'
                        ? 'Cerrar detalle'
                        : 'Cerrar',
                exact: true,
            })
            .click();

        const ruleCard = page
            .getByRole('region', { name: 'Reglas de distribución' })
            .locator('article')
            .filter({ hasText: ruleName });

        await ruleCard
            .getByRole('button', { name: 'Nueva versión', exact: true })
            .click();
        const versionDialog = page.getByRole('dialog');
        const percentages = versionDialog.getByLabel('Porcentaje', {
            exact: true,
        });

        await percentages.nth(0).fill('60');
        await percentages.nth(1).fill('25');
        await percentages.nth(2).fill('15');
        await versionDialog
            .getByRole('button', { name: 'Crear nueva versión', exact: true })
            .click();
        await expect(versionDialog).toHaveCount(0);

        const secondOrder = await createOrderForPayment(page, secondCustomer);
        const secondAllocation = distributionParts(
            secondOrder.amount,
            [60, 25, 15],
        );

        await confirmOrderPayment(page, secondOrder.publicId);
        await page.goto('/admin/management');

        const secondPaymentRow = historyRow(
            page,
            testInfo,
            `Pago de pedido ${secondOrder.publicId}`,
        );
        const secondDistributionRow = historyRow(
            page,
            testInfo,
            `Distribución de pedido ${secondOrder.publicId}`,
        );

        await expect(secondPaymentRow).toHaveCount(1);
        await expect(secondDistributionRow).toHaveCount(1);
        await expect(secondDistributionRow).toContainText(`${ruleName} v2`);
        await expect(firstDistributionRow).toContainText(`${ruleName} v1`);
        await expect(walletCard(page, receivingWallet)).toContainText(
            moneyPattern('0'),
        );
        await expect(walletCard(page, operationWallet)).toContainText(
            moneyPattern(
                (firstAllocation[0] + secondAllocation[0]).toLocaleString(
                    'es-AR',
                ),
            ),
        );
        await expect(walletCard(page, profitWallet)).toContainText(
            moneyPattern(
                (firstAllocation[1] + secondAllocation[1]).toLocaleString(
                    'es-AR',
                ),
            ),
        );
        await expect(walletCard(page, reserveWallet)).toContainText(
            moneyPattern(
                (firstAllocation[2] + secondAllocation[2]).toLocaleString(
                    'es-AR',
                ),
            ),
        );

        await page.goto(`/admin/orders?search=${firstOrder.publicId}`);
        await markOrderUnpaid(page, firstOrder.publicId);
        await confirmOrderPayment(page, firstOrder.publicId);
        await page.goto('/admin/management');
        firstPaymentRow = historyRow(
            page,
            testInfo,
            `Pago de pedido ${firstOrder.publicId}`,
        );
        await expect(firstPaymentRow).toHaveCount(1);
        await expect(
            historyRow(
                page,
                testInfo,
                `Distribución de pedido ${firstOrder.publicId}`,
            ),
        ).toHaveCount(1);

        await expect(
            secondPaymentRow.getByRole('button', {
                name: /Revertir movimiento/,
            }),
        ).toHaveCount(0);
        await secondDistributionRow
            .getByRole('button', { name: /Revertir movimiento/ })
            .click();
        let reversalDialog = page.getByRole('dialog');

        await reversalDialog.getByLabel('Concepto').fill(distributionReversal);
        await reversalDialog
            .getByRole('button', { name: 'Crear reversión', exact: true })
            .click();
        await expect(reversalDialog).toHaveCount(0);
        await expect(secondPaymentRow).toContainText('Distribución revertida');
        await secondPaymentRow
            .getByRole('button', { name: /Ver detalle/ })
            .click();
        detail = page.getByRole('dialog');
        await expect(detail).toContainText('Distribución revertida');
        await detail
            .getByRole('button', {
                name:
                    testInfo.project.name === 'mobile-chromium'
                        ? 'Cerrar detalle'
                        : 'Cerrar',
                exact: true,
            })
            .click();

        await secondPaymentRow
            .getByRole('button', { name: /Revertir movimiento/ })
            .click();
        reversalDialog = page.getByRole('dialog');
        await reversalDialog.getByLabel('Concepto').fill(paymentReversal);
        await reversalDialog
            .getByRole('button', { name: 'Crear reversión', exact: true })
            .click();
        await expect(reversalDialog).toHaveCount(0);

        await expect(walletCard(page, receivingWallet)).toContainText(
            moneyPattern('0'),
        );
        await expect(walletCard(page, operationWallet)).toContainText(
            moneyPattern(firstAllocation[0].toLocaleString('es-AR')),
        );
        await expect(walletCard(page, profitWallet)).toContainText(
            moneyPattern(firstAllocation[1].toLocaleString('es-AR')),
        );
        await expect(walletCard(page, reserveWallet)).toContainText(
            moneyPattern(firstAllocation[2].toLocaleString('es-AR')),
        );

        await ruleCard
            .getByRole('button', { name: 'Desactivar', exact: true })
            .click();
        await expect(ruleCard).toContainText('Inactiva');

        const pendingOrder = await createOrderForPayment(page, pendingCustomer);

        await confirmOrderPayment(page, pendingOrder.publicId);
        await page.goto('/admin/management');
        await page
            .getByRole('link', { name: 'Auditoría', exact: true })
            .click();
        await expect(
            page.getByRole('heading', { name: 'Auditoría', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('region', { name: 'Resumen de auditoría' }),
        ).toContainText('Pendientes');
        const walletBalances = page
            .getByRole('heading', {
                name: 'Saldos por billetera',
                exact: true,
            })
            .locator('xpath=ancestor::section[1]');

        await expect(
            walletBalances.getByText(receivingWallet, { exact: true }),
        ).toBeVisible();

        const pendingFinding = page.locator('article').filter({
            hasText: pendingOrder.publicId,
        });

        await expect(pendingFinding).toHaveCount(1);
        await expect(pendingFinding).toContainText('Pendiente de distribución');
        const distributeLink = pendingFinding.getByRole('link', {
            name: 'Distribuir',
            exact: true,
        });
        const distributeLinkBox = await distributeLink.boundingBox();

        expect(distributeLinkBox?.height).toBeGreaterThanOrEqual(44);
        await distributeLink.click();

        await ruleCard
            .getByRole('button', { name: 'Activar', exact: true })
            .click();
        await expect(ruleCard).toContainText('Activa');

        const pendingPaymentRow = historyRow(
            page,
            testInfo,
            `Pago de pedido ${pendingOrder.publicId}`,
        );

        await pendingPaymentRow
            .getByRole('button', { name: /Ver detalle/ })
            .click();
        detail = page.getByRole('dialog');
        await expect(detail).toContainText('Pendiente de distribución');
        await detail
            .getByRole('button', { name: 'Distribuir ahora', exact: true })
            .click();
        await expect(detail).toHaveCount(0);

        await page.goto('/admin/management/audit');
        await expect(
            page.locator('article').filter({ hasText: pendingOrder.publicId }),
        ).toHaveCount(0);

        if (testInfo.project.name === 'mobile-chromium') {
            expect(
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth -
                        document.documentElement.clientWidth,
                ),
            ).toBeLessThanOrEqual(1);
        }
    } finally {
        await releaseLock();
    }
});

function distributionParts(amount: number, percentages: number[]) {
    const parts = percentages.map((percentage) =>
        Math.floor((amount * percentage) / 100),
    );
    parts[parts.length - 1] +=
        amount - parts.reduce((sum, part) => sum + part, 0);

    return parts;
}

import { AdminEmptyState } from '@/components/shared/admin-empty-state';
import { AdminPageShell } from '@/components/shared/admin-page-shell';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardContent,
    CardFooter,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

export default function AdminPrimitivesFixture() {
    return (
        <div
            className="admin-shell"
            style={{
                display: 'grid',
                gridTemplateColumns: 'var(--rail-width, 0px) minmax(0, 1fr)',
            }}
        >
            <aside aria-label="Sidebar host" />
            <main className="min-w-0 p-5 sm:p-6">
                <AdminPageShell
                    title="Primitivas administrativas"
                    description="Composición compartida"
                    breadcrumbs={[
                        { title: 'Actual', href: '/admin/inventory' },
                    ]}
                    actions={<Button>Guardar</Button>}
                >
                    <Card className="admin-ui admin-panel">
                        <CardHeader>
                            <h2 className="admin-section-title">
                                Panel de formulario
                            </h2>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="admin-form-content"
                                aria-label="Formulario de ejemplo"
                            >
                                <label htmlFor="example-name">Nombre</label>
                                <Input id="example-name" />
                            </form>
                        </CardContent>
                        <CardFooter>
                            <Button variant="secondary">Secundario</Button>
                            <Button variant="outline">Contorno</Button>
                            <Button variant="ghost">Navegación</Button>
                            <Button variant="destructive">Eliminar</Button>
                            <Button disabled aria-busy="true">
                                Guardando…
                            </Button>
                        </CardFooter>
                    </Card>
                    <Card className="admin-ui admin-panel">
                        <CardContent className="admin-panel-table">
                            <table>
                                <caption>Ejemplo tabular</caption>
                                <thead>
                                    <tr>
                                        <th>Concepto</th>
                                        <th>Importe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Ejemplo</td>
                                        <td>$ 12.500</td>
                                    </tr>
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                    <AdminEmptyState
                        title="Sin producciones"
                        description="Necesitas una receta vigente para empezar."
                        action={
                            <Button asChild>
                                <a href="/admin/inventory/recipes">
                                    Ver recetas
                                </a>
                            </Button>
                        }
                        secondaryAction={
                            <Button asChild variant="ghost">
                                <a href="/admin/inventory">Ver insumos</a>
                            </Button>
                        }
                    />
                </AdminPageShell>
                <Button>Fuera de alcance</Button>
            </main>
        </div>
    );
}

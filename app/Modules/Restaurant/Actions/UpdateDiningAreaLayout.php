<?php

namespace App\Modules\Restaurant\Actions;

use App\Modules\Restaurant\Models\DiningArea;
use App\Modules\Restaurant\Support\FloorGeometry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateDiningAreaLayout
{
    /** @param array<string, mixed> $data */
    public function execute(DiningArea $area, array $data): DiningArea
    {
        return DB::transaction(function () use ($area, $data): DiningArea {
            $locked = DiningArea::query()->lockForUpdate()->findOrFail($area->id);
            if ($locked->layout_version !== (int) $data['expected_version']) {
                throw new ConflictHttpException('El plano cambió. Conservá tus cambios y recargá para reconciliar.');
            }
            $existing = $locked->tables()->get()->keyBy('id');
            $ids = array_map('intval', array_column($data['tables'], 'id'));
            if (count($ids) !== count(array_unique($ids)) || count($ids) !== $existing->count() || array_diff($ids, $existing->keys()->all()) !== []) {
                throw ValidationException::withMessages(['tables' => 'Incluye todas las mesas existentes de esta área; desactivá en lugar de eliminar.']);
            }
            $obstacles = array_map(function (array $obstacle): array {
                foreach (['x', 'y', 'width', 'height'] as $key) {
                    $obstacle[$key] = FloorGeometry::decimal($obstacle[$key]);
                }

                return $obstacle;
            }, $data['obstacles']);
            $locked->update(['name' => $data['name'], 'width' => $data['width'], 'height' => $data['height'], 'is_active' => $data['is_active'], 'obstacles' => $obstacles, 'layout_version' => $locked->layout_version + 1]);
            foreach ($data['tables'] as $table) {
                $id = $table['id'] ?? null;
                unset($table['id']);
                foreach (['position_x', 'position_y', 'width', 'height'] as $key) {
                    $table[$key] = FloorGeometry::decimal($table[$key]);
                }
                if ($id !== null) {
                    $existing->get($id)->update($table);
                } else {
                    $locked->tables()->create($table);
                }
            }

            return $locked->refresh();
        });
    }
}
